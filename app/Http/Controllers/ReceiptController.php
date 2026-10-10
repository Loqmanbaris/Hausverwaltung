<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\Property;
use App\Models\Receipt;
use App\Models\ReceiptFile;
use App\Models\RecurringExpense;
use App\Models\Vendor;
use App\Support\Audit;
use App\Support\Documents\DocumentStore;
use App\Support\Documents\UploadInspector;
use App\Support\Documents\UploadRejected;
use App\Support\Receipts\ReceiptRules;
use App\Support\Receipts\ReceiptService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Belege: Foto oder PDF mit Angaben zu Eigentümer, Objekt, Betrag, Datum und Kostenart.
 * Mitarbeiter sehen und bearbeiten nur Belege der für sie freigegebenen Eigentümer.
 * Die Dateien eines Belegs werden nie geändert, archiviert werden kann nur der Administrator.
 */
class ReceiptController extends Controller
{
    use ScopesOwners;

    public function index(Request $request)
    {
        $user = $this->me();
        $ownerIds = $this->visibleOwnerIds();

        $ownerFilter = (int) $request->query('eigentuemer');
        $ownerFilter = in_array($ownerFilter, $ownerIds, true) ? $ownerFilter : 0;
        $status = in_array($request->query('status'), ['unvollstaendig', 'vollstaendig'], true) ? $request->query('status') : '';
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        $query = Receipt::query()
            ->visibleTo($user)
            ->with(['owner', 'property', 'unit'])
            ->withCount('files');

        if ($ownerFilter) {
            $query->where('receipts.owner_id', $ownerFilter);
        }
        if ($status === 'unvollstaendig') {
            $query->incomplete();
        } elseif ($status === 'vollstaendig') {
            $query->complete();
        }
        if ($q !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';
            $query->where(function ($w) use ($like) {
                $w->where('receipts.vendor_name', 'like', $like)
                    ->orWhere('receipts.invoice_number', 'like', $like)
                    ->orWhere('receipts.note', 'like', $like);
            });
        }

        $receipts = $query->orderByDesc('receipts.id')->simplePaginate(25)->withQueryString();

        return view('receipts.index', [
            'receipts' => $receipts,
            'owners' => $this->visibleOwners()->get(['owners.id', 'owners.name']),
            'ownerFilter' => $ownerFilter,
            'status' => $status,
            'q' => $q,
            'incompleteCount' => Receipt::incompleteCountFor($user),
        ]);
    }

    public function create(Request $request)
    {
        $receipt = new Receipt([
            'owner_id' => (int) $request->query('eigentuemer') ?: null,
            'property_id' => (int) $request->query('objekt') ?: null,
        ]);

        // Vorbelegung aus einer wiederkehrenden Ausgabe (nur für Eigentümer, die der Benutzer sehen darf)
        $expenseId = (int) $request->query('wiederkehrend');
        if ($expenseId > 0) {
            $expense = RecurringExpense::query()->visibleTo($this->me())->find($expenseId);
            if ($expense !== null) {
                $due = (string) $request->query('faellig');
                $receipt->fill([
                    'owner_id' => $expense->owner_id,
                    'property_id' => $expense->property_id,
                    'unit_id' => $expense->unit_id,
                    'owner_level' => $expense->owner_level,
                    'cost_category' => $expense->cost_category,
                    'vendor_name' => $expense->vendor_name,
                    'amount_gross' => $expense->amount_gross,
                    'receipt_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $due) === 1 ? $due : null,
                ]);
            }
        }

        // Vorbelegung aus einer Abbuchung im Kontoauszug (Paket 9)
        $bankTx = null;
        $txId = (int) $request->query('umsatz');
        if ($txId > 0) {
            $bankTx = \App\Models\BankTransaction::query()->visibleTo($this->me())->where('amount', '<', 0)->find($txId);
            if ($bankTx !== null) {
                $receipt->owner_id = $bankTx->owner_id;
                $receipt->receipt_date ??= $bankTx->booking_date;
                $receipt->amount_gross = number_format(abs((float) $bankTx->amount), 2, '.', '');
                $receipt->vendor_name ??= $bankTx->counterparty_name;
            }
        }

        return view('receipts.form', $this->formData($receipt) + ['bankTx' => $bankTx]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(
            ReceiptRules::rules($this->visibleOwnerIds(), true),
            ReceiptRules::messages(),
            ReceiptRules::attributes()
        );
        $fields = ReceiptRules::normalize($data);

        $inspected = [];
        foreach ((array) $request->file('files', []) as $file) {
            try {
                $inspected[] = UploadInspector::inspect((string) $file->getRealPath(), (string) $file->getClientOriginalName());
            } catch (UploadRejected $e) {
                throw ValidationException::withMessages(['files' => $e->getMessage()]);
            }
        }

        try {
            [$receipt, $duplicate] = ReceiptService::create($fields, $inspected, $this->me());
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['files' => $e->getMessage()]);
        }

        // Aus einer Abbuchung heraus erfasst: gleich verknüpfen und zurück zum Abgleich
        $txId = (int) $request->input('bank_tx');
        if ($txId > 0) {
            $tx = \App\Models\BankTransaction::query()->visibleTo($this->me())->find($txId);
            if ($tx !== null) {
                $note = 'Beleg Nr. '.$receipt->id.' gespeichert';
                try {
                    $remaining = \App\Support\Bank\ExpenseMatcher::remainingCents($tx);
                    $cents = $receipt->amount_gross !== null ? min($remaining, (int) round((float) $receipt->amount_gross * 100)) : $remaining;
                    \App\Support\Bank\ExpenseMatcher::link($tx, $receipt, $cents, $this->me());
                    $note .= ' und mit der Abbuchung vom '.$tx->booking_date->format('d.m.Y').' verknüpft.';
                } catch (RuntimeException $e) {
                    $note .= '. Verknüpfen mit der Abbuchung ging nicht: '.$e->getMessage();
                }

                return redirect()->to(route('bank.expenses').'#abbuchung-'.$tx->id)->with('status', $note);
            }
        }

        $redirect = $request->boolean('again')
            ? redirect()->route('receipts.create', array_filter([
                'eigentuemer' => $receipt->owner_id,
                'objekt' => $receipt->property_id,
            ]))
            : redirect()->route('receipts.show', $receipt);

        $redirect->with('status', $request->boolean('again')
            ? 'Beleg Nr. '.$receipt->id.' gespeichert. Hier der nächste:'
            : 'Beleg wurde gespeichert.');

        if ($duplicate !== null) {
            $redirect->with('warning', 'Hinweis: Eine der Dateien wurde schon bei Beleg Nr. '.$duplicate->id.' erfasst. Bitte prüfen, ob der Beleg doppelt ist.');
        }

        return $redirect;
    }

    public function show(Receipt $receipt)
    {
        $this->assertOwnerAccess($receipt->owner_id);
        $receipt->load(['owner', 'property', 'unit', 'files', 'creator']);

        return view('receipts.show', compact('receipt'));
    }

    public function edit(Receipt $receipt)
    {
        $this->assertOwnerAccess($receipt->owner_id);

        return view('receipts.form', $this->formData($receipt));
    }

    public function update(Request $request, Receipt $receipt)
    {
        $this->assertOwnerAccess($receipt->owner_id);

        // Der Eigentümer eines Belegs bleibt gleich.
        $request->merge(['owner_id' => $receipt->owner_id]);

        $data = $request->validate(
            ReceiptRules::rules([(int) $receipt->owner_id], false, $receipt->cost_category),
            ReceiptRules::messages(),
            ReceiptRules::attributes()
        );
        $fields = ReceiptRules::normalize($data);
        unset($fields['owner_id']);

        ReceiptService::update($receipt, $fields);

        return redirect()->route('receipts.show', $receipt)->with('status', 'Änderungen wurden gespeichert.');
    }

    public function destroy(Receipt $receipt)
    {
        ReceiptService::archive($receipt);

        return redirect()->route('receipts.index')->with('status', 'Beleg wurde archiviert. Die Dateien bleiben aufbewahrt.');
    }

    /** Liefert eine Datei des Belegs aus dem geschützten Speicher. Mit ?herunterladen=1 als Download. */
    public function file(Request $request, Receipt $receipt, ReceiptFile $receiptFile)
    {
        $this->assertOwnerAccess($receipt->owner_id);
        abort_unless((int) $receiptFile->receipt_id === (int) $receipt->id, 404);

        try {
            $plain = DocumentStore::get($receiptFile->storage_path);
        } catch (RuntimeException $e) {
            Audit::log('document.failed', $receipt, 'Datei von '.$receipt->title().' nicht lesbar: '.$e->getMessage());
            abort(500, 'Die Datei kann nicht gelesen werden. Bitte den Administrator informieren.');
        }

        if (! hash_equals((string) $receiptFile->sha256, hash('sha256', $plain))) {
            Audit::log('document.failed', $receipt, 'Prüfsumme stimmt nicht: Seite '.$receiptFile->position.' von '.$receipt->title());
            abort(500, 'Die Datei ist verändert worden und wird nicht angezeigt.');
        }

        $download = $request->boolean('herunterladen');
        if ($download) {
            Audit::log('document.downloaded', $receipt, 'Beleg-Datei heruntergeladen: Seite '.$receiptFile->position.' von '.$receipt->title());
        }

        $disposition = ($download || ! ($receiptFile->isImage() || $receiptFile->isPdf())) ? 'attachment' : 'inline';

        $headers = [
            'Content-Type' => $receiptFile->mime,
            'Content-Length' => (string) strlen($plain),
            'Content-Disposition' => $disposition.'; filename="'.$receiptFile->downloadName($receipt).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
        ];
        if ($receiptFile->isImage()) {
            // Bilder dürfen nichts nachladen oder ausführen (der PDF-Betrachter des Browsers verträgt das nicht).
            $headers['Content-Security-Policy'] = "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'";
        }

        return response($plain, 200, $headers);
    }

    private function formData(Receipt $receipt): array
    {
        $owners = $this->visibleOwners()->get(['owners.id', 'owners.name']);
        $ownerIds = $owners->pluck('id')->map(fn ($id) => (int) $id)->all();

        $properties = Property::query()
            ->whereIn('owner_id', $ownerIds)
            ->with(['units' => fn ($q) => $q->orderBy('label')])
            ->orderBy('name')
            ->get();

        // Bei genau einem Eigentümer ist die Auswahl überflüssig.
        if (! $receipt->exists && ! $receipt->owner_id && count($ownerIds) === 1) {
            $receipt->owner_id = $ownerIds[0];
        }

        $vendors = Vendor::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'default_cost_category']);

        return compact('receipt', 'owners', 'properties', 'vendors');
    }
}
