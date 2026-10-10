<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\BankImport;
use App\Models\BankTransaction;
use App\Models\Lease;
use App\Models\LeasePayerAccount;
use App\Models\OwnerBankAccount;
use App\Models\RentPayment;
use App\Support\Audit;
use App\Support\Bank\BankImportException;
use App\Support\Bank\BankMatcher;
use App\Support\Bank\BankService;
use App\Support\Documents\DocumentStore;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Kontoauszug-Import und Zuordnung der Gutschriften zu Mietverträgen (Paket 5b).
 * Mitarbeiter nur für freigegebene Eigentümer. Einen Import löschen kann nur der Administrator.
 */
class BankController extends Controller
{
    use ScopesOwners;

    public function index()
    {
        $ownerIds = $this->visibleOwnerIds();
        $accounts = OwnerBankAccount::query()->whereIn('owner_id', $ownerIds)->with('owner')->get()
            ->sortBy(fn ($a) => [$a->owner?->name, $a->label])->values();

        $imports = BankImport::query()->whereIn('owner_id', $ownerIds)->with(['owner', 'account', 'creator'])
            ->orderByDesc('id')->limit(30)->get();

        $open = BankTransaction::query()->whereIn('owner_id', $ownerIds)->whereIn('status', ['offen', 'teilweise'])
            ->selectRaw('owner_bank_account_id, count(*) as c')->groupBy('owner_bank_account_id')->pluck('c', 'owner_bank_account_id');

        $last = BankTransaction::query()->whereIn('owner_id', $ownerIds)
            ->selectRaw('owner_bank_account_id, max(booking_date) as d')->groupBy('owner_bank_account_id')->pluck('d', 'owner_bank_account_id');

        return view('bank.index', compact('accounts', 'imports', 'open', 'last'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'datei' => ['required', 'file', 'max:5120'],
            'account_id' => ['nullable', 'integer'],
        ], [
            'datei.required' => 'Bitte eine Datei auswählen.',
            'datei.file' => 'Die Datei konnte nicht hochgeladen werden.',
            'datei.max' => 'Die Datei ist zu groß (höchstens 5 MB).',
        ]);

        $file = $request->file('datei');
        try {
            $import = BankService::import(
                (string) file_get_contents($file->getRealPath()),
                (string) $file->getClientOriginalName(),
                $request->integer('account_id') ?: null,
                $this->me()
            );
        } catch (BankImportException $e) {
            return back()->withErrors(['datei' => $e->getMessage()]);
        }

        $open = BankTransaction::query()->where('bank_import_id', $import->id)->where('status', 'offen')->count();

        return redirect()->route('bank.transactions', ['konto' => $import->owner_bank_account_id])
            ->with('status', 'Kontoauszug eingelesen: '.$import->rows_new.' neue Umsätze'
                .($import->rows_duplicate > 0 ? ', '.$import->rows_duplicate.' waren schon vorhanden' : '')
                .($import->rows_skipped > 0 ? ', '.$import->rows_skipped.' vorgemerkte übersprungen' : '')
                .'. '.$open.' Gutschrift'.($open === 1 ? '' : 'en').' zum Zuordnen.');
    }

    public function transactions(Request $request)
    {
        $ownerIds = $this->visibleOwnerIds();
        $state = (string) $request->query('stand', 'offen');
        $accountId = (int) $request->query('konto');

        $query = BankTransaction::query()->whereIn('owner_id', $ownerIds)
            ->when($accountId > 0, fn ($q) => $q->where('owner_bank_account_id', $accountId))
            ->with(['payments.lease.unit', 'payments.lease.tenant', 'account', 'owner']);

        match ($state) {
            'zugeordnet' => $query->whereIn('status', ['zugeordnet', 'teilweise']),
            'ignoriert' => $query->where('status', 'ignoriert'),
            'ausgaben' => $query->where('status', 'ausgabe'),
            'alle' => $query,
            default => $query->whereIn('status', ['offen', 'teilweise']),
        };

        $txs = $query->orderByDesc('booking_date')->orderByDesc('id')->limit(300)->get();

        // Vorschläge und Auswahl der Verträge je Eigentümer
        $rows = [];
        $leaseOptions = [];
        $dataCache = [];
        foreach ($txs as $tx) {
            $suggestions = [];
            $sure = false;
            if (in_array($tx->status, ['offen', 'teilweise'], true)) {
                $key = $tx->owner_id.'|'.$tx->booking_date->format('Y-m');
                $dataCache[$key] ??= BankMatcher::leaseData((int) $tx->owner_id, $tx->booking_date->format('Y-m-15'));
                $suggestions = array_values(array_filter(BankMatcher::suggestions($tx, $dataCache[$key]), fn ($s) => $s['score'] >= BankMatcher::MIN));
                $sure = $suggestions !== []
                    && $suggestions[0]['score'] >= BankMatcher::SURE
                    && (count($suggestions) === 1 || $suggestions[1]['score'] < $suggestions[0]['score']);

                $leaseOptions[$tx->owner_id] ??= Lease::query()->where('owner_id', $tx->owner_id)->with(['unit.property', 'tenant'])->get()
                    ->sortBy(fn ($l) => [$l->unit?->property?->name, $l->unit?->label, $l->start_date->format('Y-m-d')])->values();
            }
            $rows[] = ['tx' => $tx, 'suggestions' => $suggestions, 'sure' => $sure];
        }

        $sureCount = count(array_filter($rows, fn ($r) => $r['sure'] && $r['tx']->status === 'offen'));

        $accounts = OwnerBankAccount::query()->whereIn('owner_id', $ownerIds)->with('owner')->get();
        $counts = BankTransaction::query()->whereIn('owner_id', $ownerIds)
            ->when($accountId > 0, fn ($q) => $q->where('owner_bank_account_id', $accountId))
            ->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return view('bank.transactions', compact('rows', 'leaseOptions', 'state', 'accountId', 'accounts', 'counts', 'sureCount'));
    }

    public function assign(Request $request, BankTransaction $tx)
    {
        $this->assertOwnerAccess((int) $tx->owner_id);
        $data = $request->validate([
            'lease_id' => ['required', 'integer'],
            'amount' => ['required', 'string', 'max:20'],
        ], ['lease_id.required' => 'Bitte einen Mietvertrag auswählen.', 'amount.required' => 'Bitte einen Betrag eintragen.']);

        $lease = Lease::query()->find((int) $data['lease_id']);
        $amount = Money::parse($data['amount']);
        if ($lease === null || $amount === null) {
            return $this->back($request, $tx)->withErrors(['tx' => 'Bitte Mietvertrag und Betrag prüfen.']);
        }
        $this->assertOwnerAccess((int) $lease->owner_id);

        try {
            BankService::assign($tx, $lease, $amount, $request->boolean('merken'), $this->me());
        } catch (BankImportException $e) {
            return $this->back($request, $tx)->withErrors(['tx' => $e->getMessage()]);
        }

        $tx->refresh();
        $msg = Money::format($amount).' vom '.$tx->booking_date->format('d.m.Y').' wurde '.$lease->load(['unit', 'tenant'])->unit?->label.' – '.$lease->tenant?->display_name.' zugeordnet.';
        if ($tx->status === 'teilweise') {
            $msg .= ' Rest '.Money::format($tx->remaining()).' noch zuordnen.';
        }

        return $this->back($request, $tx)->with('status', $msg);
    }

    /** Alle sicheren Vorschläge der Seite auf einmal übernehmen (nur ganze, noch offene Gutschriften). */
    public function acceptSure(Request $request)
    {
        $ids = array_map('intval', (array) $request->input('ids', []));
        $ownerIds = $this->visibleOwnerIds();
        $done = 0;
        $sum = 0.0;

        foreach (BankTransaction::query()->whereIn('id', $ids)->whereIn('owner_id', $ownerIds)->where('status', 'offen')->get() as $tx) {
            $s = BankMatcher::suggestions($tx);
            if ($s === [] || $s[0]['score'] < BankMatcher::SURE || (isset($s[1]) && $s[1]['score'] >= $s[0]['score'])) {
                continue;
            }
            try {
                BankService::assign($tx, $s[0]['lease'], number_format((float) $tx->amount, 2, '.', ''), true, $this->me());
                $done++;
                $sum += (float) $tx->amount;
            } catch (BankImportException) {
                continue;
            }
        }

        return redirect()->route('bank.transactions', array_filter(['konto' => $request->integer('konto') ?: null]))
            ->with('status', $done.' Gutschrift'.($done === 1 ? '' : 'en').' über zusammen '.Money::format($sum).' zugeordnet.');
    }

    public function ignore(Request $request, BankTransaction $tx)
    {
        $this->assertOwnerAccess((int) $tx->owner_id);
        try {
            BankService::ignore($tx, trim((string) $request->input('note')));
        } catch (BankImportException $e) {
            return $this->back($request, $tx)->withErrors(['tx' => $e->getMessage()]);
        }

        return $this->back($request, $tx)->with('status', 'Umsatz vom '.$tx->booking_date->format('d.m.Y').' über '.Money::format($tx->amount).' als „keine Miete“ markiert.');
    }

    public function reset(Request $request, BankTransaction $tx)
    {
        $this->assertOwnerAccess((int) $tx->owner_id);
        BankService::reset($tx);

        return $this->back($request, $tx)->with('status', 'Umsatz vom '.$tx->booking_date->format('d.m.Y').' ist wieder offen. Zugeordnete Zahlungen wurden storniert.');
    }

    public function destroyImport(BankImport $import)
    {
        $hasPayments = RentPayment::withTrashed()
            ->whereIn('bank_transaction_id', BankTransaction::query()->where('bank_import_id', $import->id)->select('id'))
            ->whereNull('deleted_at')->exists();
        if ($hasPayments) {
            return back()->withErrors(['import' => 'Aus diesem Kontoauszug sind schon Zahlungen zugeordnet. Bitte diese zuerst zurücksetzen.']);
        }

        DB::transaction(function () use ($import) {
            Audit::log('bank.import_deleted', $import, 'Kontoauszug gelöscht: '.$import->filename.' ('.$import->rows_new.' Umsätze)');
            DB::table('bank_transactions')->where('bank_import_id', $import->id)->delete();
            $import->delete();
        });
        if ($import->storage_path) {
            DocumentStore::forget($import->storage_path);
        }

        return redirect()->route('bank.index')->with('status', 'Kontoauszug „'.$import->filename.'“ wurde gelöscht.');
    }

    public function payerDestroy(Lease $lease, LeasePayerAccount $payer)
    {
        $this->assertOwnerAccess((int) $lease->owner_id);
        abort_unless((int) $payer->lease_id === (int) $lease->id, 404);

        Audit::log('bank.payer_removed', $lease, 'Zahlerkonto entfernt: '.$payer->maskedIban().' ('.($payer->name ?? '–').')');
        $payer->delete();

        return redirect()->route('leases.show', $lease)->with('status', 'Zahlerkonto wurde entfernt. Es wird nicht mehr automatisch vorgeschlagen.');
    }

    private function back(Request $request, BankTransaction $tx)
    {
        $params = array_filter([
            'stand' => $request->input('stand') ?: null,
            'konto' => $request->input('konto') ?: null,
        ]);

        return redirect()->to(route('bank.transactions', $params).'#umsatz-'.$tx->id);
    }
}
