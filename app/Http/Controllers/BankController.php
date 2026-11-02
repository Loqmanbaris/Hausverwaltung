<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\BankImport;
use App\Models\BankTransaction;
use App\Models\BankTransactionItem;
use App\Models\Lease;
use App\Models\LeasePayerAccount;
use App\Models\OwnerBankAccount;
use App\Models\RentPayment;
use App\Support\Audit;
use App\Support\Bank\BankImportException;
use App\Support\Bank\BankMatcher;
use App\Support\Bank\BankService;
use App\Support\Bank\Monatsfilter;
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

        // Alle Monate zeigen, damit die neuen Umsätze nicht von einer gespeicherten Monatsauswahl verdeckt werden
        return redirect()->route('bank.transactions', ['konto' => $import->owner_bank_account_id, 'monat' => ''])
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
        $month = Monatsfilter::current($request);

        $query = Monatsfilter::apply(BankTransaction::query()->whereIn('owner_id', $ownerIds), $month)
            ->when($accountId > 0, fn ($q) => $q->where('owner_bank_account_id', $accountId))
            ->with(['payments.lease.unit', 'payments.lease.tenant', 'account', 'owner']);
        if (\Illuminate\Support\Facades\Schema::hasTable('bank_transaction_items')) {
            $query->with(['items.lease.unit', 'items.lease.tenant']);
        }

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
        $counts = Monatsfilter::apply(BankTransaction::query()->whereIn('owner_id', $ownerIds), $month)
            ->when($accountId > 0, fn ($q) => $q->where('owner_bank_account_id', $accountId))
            ->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $months = Monatsfilter::options($ownerIds, $month);

        return view('bank.transactions', compact('rows', 'leaseOptions', 'state', 'accountId', 'accounts', 'counts', 'sureCount', 'month', 'months'));
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

    /** Gutschrift aufteilen (Fenster „Aufteilen“): mehrere Zeilen mit Einnahmeart, Vertrag, Monat, Betrag, Notiz. */
    public function split(Request $request, BankTransaction $tx)
    {
        $this->assertOwnerAccess((int) $tx->owner_id);
        try {
            $r = BankService::split($tx, array_values((array) $request->input('rows', [])), $request->boolean('merken'), $this->me());
        } catch (BankImportException $e) {
            return $this->back($request, $tx)->withErrors(['tx' => $e->getMessage()])->withInput();
        }
        $tx->refresh();
        $msg = Money::format($r['sum']).' vom '.$tx->booking_date->format('d.m.Y').' in '.$r['count'].' Posten aufgeteilt.';
        if ($tx->status === 'teilweise') {
            $msg .= ' Rest '.Money::format($tx->remaining()).' noch zuordnen.';
        }

        return $this->back($request, $tx)->with('status', $msg);
    }

    /** Einen Teil ohne Mietzahlung (Kaution, Sonstiges) entfernen. */
    public function itemRemove(Request $request, BankTransaction $tx, BankTransactionItem $item)
    {
        $this->assertOwnerAccess((int) $tx->owner_id);
        try {
            BankService::removeItem($tx, $item, $this->me());
        } catch (BankImportException $e) {
            return $this->back($request, $tx)->withErrors(['tx' => $e->getMessage()]);
        }

        return $this->back($request, $tx)->with('status', $item->kindLabel().' über '.Money::format($item->amount).' entfernt.');
    }

    public function reset(Request $request, BankTransaction $tx)
    {
        $this->assertOwnerAccess((int) $tx->owner_id);
        try {
            BankService::reset($tx);
        } catch (BankImportException $e) {
            return $this->back($request, $tx)->withErrors(['tx' => $e->getMessage()]);
        }

        return $this->back($request, $tx)->with('status', 'Umsatz vom '.$tx->booking_date->format('d.m.Y').' ist wieder offen. Zugeordnete Zahlungen wurden storniert.');
    }

    /** Umsatz bearbeiten: Angaben korrigieren und Zuordnungen einzeln ändern. */
    public function edit(Request $request, BankTransaction $tx)
    {
        $this->assertOwnerAccess((int) $tx->owner_id);
        $tx->load(['payments.lease.unit.property', 'payments.lease.tenant', 'account', 'owner', 'import']);
        if (\Illuminate\Support\Facades\Schema::hasTable('bank_transaction_items')) {
            $tx->load(['items.lease.unit.property', 'items.lease.tenant']);
        }

        $leases = Lease::query()->where('owner_id', $tx->owner_id)->with(['unit.property', 'tenant'])->get()
            ->sortBy(fn ($l) => [$l->unit?->property?->name, $l->unit?->label, $l->start_date->format('Y-m-d')])->values();
        $suggestions = [];
        if (in_array($tx->status, ['offen', 'teilweise'], true)) {
            $suggestions = array_values(array_filter(BankMatcher::suggestions($tx), fn ($s) => $s['score'] >= BankMatcher::MIN));
        }
        $exported = \Illuminate\Support\Facades\Schema::hasTable('datev_export_rent_items')
            ? DB::table('datev_export_rent_items')->whereIn('rent_payment_id', $tx->payments->pluck('id'))->distinct()->pluck('rent_payment_id')->flip()->all()
            : [];
        $receipts = (float) DB::table('bank_transaction_receipts')->where('bank_transaction_id', $tx->id)->sum('amount');
        $loan = \Illuminate\Support\Facades\Schema::hasTable('loan_events') && DB::table('loan_events')->where('bank_transaction_id', $tx->id)->exists();

        return view('bank.edit', [
            'tx' => $tx,
            'leases' => $leases,
            'suggestions' => $suggestions,
            'exported' => $exported,
            'receipts' => $receipts,
            'loan' => $loan,
            'backUrl' => $this->listUrl($request, $tx),
            'stand' => (string) $request->query('stand', ''),
            'konto' => (string) $request->query('konto', ''),
        ]);
    }

    public function update(Request $request, BankTransaction $tx)
    {
        $this->assertOwnerAccess((int) $tx->owner_id);
        $data = $request->validate([
            'booking_date' => ['required', 'date_format:Y-m-d'],
            'value_date' => ['nullable', 'date_format:Y-m-d'],
            'amount' => ['required', 'string', 'max:20'],
            'counterparty_name' => ['nullable', 'string', 'max:190'],
            'purpose' => ['nullable', 'string', 'max:2000'],
            'booking_text' => ['nullable', 'string', 'max:190'],
        ], [
            'booking_date.required' => 'Bitte den Buchungstag eintragen.',
            'booking_date.date_format' => 'Bitte einen gültigen Buchungstag eintragen.',
            'value_date.date_format' => 'Bitte eine gültige Wertstellung eintragen.',
            'amount.required' => 'Bitte einen Betrag eintragen.',
        ]);
        $amount = Money::parse($data['amount']);
        if ($amount === null) {
            return back()->withInput()->withErrors(['amount' => 'Bitte einen gültigen Betrag eintragen, z. B. 750,00 oder -120,50.']);
        }
        $data['amount'] = $amount;
        $data += ['value_date' => null, 'counterparty_name' => null, 'purpose' => null, 'booking_text' => null];

        try {
            BankService::updateDetails($tx, $data, $this->me());
        } catch (BankImportException $e) {
            return back()->withInput()->withErrors(['tx' => $e->getMessage()]);
        }

        return $this->toEdit($request, $tx)->with('status', 'Umsatz gespeichert.');
    }

    public function paymentUpdate(Request $request, BankTransaction $tx, RentPayment $payment)
    {
        $this->assertOwnerAccess((int) $tx->owner_id);
        $data = $request->validate([
            'lease_id' => ['required', 'integer'],
            'amount' => ['required', 'string', 'max:20'],
        ], ['lease_id.required' => 'Bitte einen Mietvertrag auswählen.', 'amount.required' => 'Bitte einen Betrag eintragen.']);
        $lease = Lease::query()->find((int) $data['lease_id']);
        $amount = Money::parse($data['amount']);
        if ($lease === null || $amount === null) {
            return $this->toEdit($request, $tx)->withErrors(['tx' => 'Bitte Mietvertrag und Betrag prüfen.']);
        }
        $this->assertOwnerAccess((int) $lease->owner_id);

        try {
            BankService::changePayment($tx, $payment, $lease, $amount, $this->me());
        } catch (BankImportException $e) {
            return $this->toEdit($request, $tx)->withErrors(['tx' => $e->getMessage()]);
        }

        return $this->toEdit($request, $tx)->with('status', 'Zuordnung geändert: '.Money::format($amount).' an '.$lease->title().'.');
    }

    public function paymentRemove(Request $request, BankTransaction $tx, RentPayment $payment)
    {
        $this->assertOwnerAccess((int) $tx->owner_id);
        try {
            BankService::removePayment($tx, $payment, $this->me());
        } catch (BankImportException $e) {
            return $this->toEdit($request, $tx)->withErrors(['tx' => $e->getMessage()]);
        }

        return $this->toEdit($request, $tx)->with('status', 'Zuordnung über '.Money::format($payment->amount).' entfernt. Der Betrag ist wieder offen.');
    }

    private function toEdit(Request $request, BankTransaction $tx)
    {
        return redirect()->route('bank.edit', array_filter([
            'tx' => $tx->id,
            'stand' => $request->input('stand') ?: null,
            'konto' => $request->input('konto') ?: null,
        ]));
    }

    private function listUrl(Request $request, BankTransaction $tx): string
    {
        if ($tx->status === 'ausgabe' || (float) $tx->amount < 0) {
            return route('bank.expenses', array_filter(['konto' => $request->input('konto') ?: null])).'#abbuchung-'.$tx->id;
        }

        return route('bank.transactions', array_filter(['stand' => $request->input('stand') ?: null, 'konto' => $request->input('konto') ?: null])).'#umsatz-'.$tx->id;
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
        // Aktion kam von „Umsatz bearbeiten“: dorthin zurück
        if ($request->input('ziel') === 'bearbeiten') {
            return $this->toEdit($request, $tx);
        }
        $params = array_filter([
            'stand' => $request->input('stand') ?: null,
            'konto' => $request->input('konto') ?: null,
        ]);

        return redirect()->to(route('bank.transactions', $params).'#umsatz-'.$tx->id);
    }
}
