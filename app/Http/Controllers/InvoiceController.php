<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Owner;
use App\Models\Tenant;
use App\Support\Money;
use App\Support\Rechnung\Aussteller;
use App\Support\Rechnung\Berechnung;
use App\Support\Rechnung\Fakturierung;
use App\Support\Rechnung\Pflicht;
use App\Support\Settings;
use Illuminate\Http\Request;
use RuntimeException;

/** Rechnungen schreiben (Paket 45). Nur für Administratoren. */
class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $invoices = Invoice::query()->orderByRaw('case when number is null then 0 else 1 end')->orderByDesc('issued_on')->orderByDesc('id')->limit(300)->get();
        $open = $invoices->filter(fn ($i) => $i->type === '380' && in_array($i->status, ['festgeschrieben', 'versendet'], true) && $i->paid_on === null);

        return view('invoices.index', [
            'invoices' => $invoices, 'issues' => Aussteller::issues(), 'openSum' => (float) $open->sum('gross'), 'openCount' => $open->count(),
        ]);
    }

    public function create()
    {
        $d = Aussteller::data();

        return view('invoices.form', $this->formData(null) + ['defaults' => ['due_days' => (int) $d['inv_due_days']]]);
    }

    public function store(Request $request)
    {
        $errors = [];
        $data = $this->parse($request, $errors);
        if ($errors !== []) {
            return back()->withInput()->withErrors($errors);
        }
        $inv = Fakturierung::save(null, $data, $request->user());

        return redirect()->route('invoices.show', $inv)->with('status', 'Der Entwurf ist gespeichert. Prüfe ihn und schreibe ihn dann fest.');
    }

    public function edit(Invoice $invoice)
    {
        abort_unless($invoice->isDraft(), 404);

        return view('invoices.form', $this->formData($invoice) + ['defaults' => ['due_days' => (int) Aussteller::data()['inv_due_days']]]);
    }

    public function update(Request $request, Invoice $invoice)
    {
        abort_unless($invoice->isDraft(), 404);
        $errors = [];
        $data = $this->parse($request, $errors);
        if ($errors !== []) {
            return back()->withInput()->withErrors($errors);
        }
        Fakturierung::save($invoice, $data, $request->user());

        return redirect()->route('invoices.show', $invoice)->with('status', 'Gespeichert.');
    }

    public function show(Invoice $invoice)
    {
        $invoice->load('lines');
        $seller = $invoice->seller ?: Aussteller::snapshot();

        return view('invoices.show', [
            'inv' => $invoice, 'sum' => Fakturierung::totals($invoice), 'seller' => $seller,
            'problems' => $invoice->isDraft() ? Pflicht::check($invoice, $seller, date('Y-m-d')) : [],
            'original' => $invoice->original_id ? Invoice::query()->find($invoice->original_id) : null,
            'credit' => $invoice->isCredit() ? null : Invoice::query()->where('original_id', $invoice->id)->first(),
        ]);
    }

    public function finalize(Request $request, Invoice $invoice)
    {
        $date = $request->input('issued_on');
        $date = is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date : null;
        try {
            $inv = Fakturierung::finalize($invoice, $request->user(), $date);
        } catch (RuntimeException $e) {
            return back()->withErrors(['finalize' => $e->getMessage()]);
        }

        return redirect()->route('invoices.show', $inv)->with('status', 'Die Rechnung '.$inv->number.' ist festgeschrieben und kann nicht mehr geändert werden.');
    }

    public function destroy(Invoice $invoice)
    {
        abort_unless($invoice->isDraft(), 404);
        $invoice->delete();

        return redirect()->route('invoices.index')->with('status', 'Der Entwurf wurde gelöscht.');
    }

    public function credit(Request $request, Invoice $invoice)
    {
        try {
            $c = Fakturierung::credit($invoice, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['finalize' => $e->getMessage()]);
        }

        return redirect()->route('invoices.show', $c)->with('status', 'Die Gutschrift '.$c->number.' ist erstellt. Die Rechnung '.$invoice->number.' gilt als storniert.');
    }

    public function paid(Invoice $invoice)
    {
        abort_if($invoice->number === null || $invoice->isCredit(), 404);
        Fakturierung::togglePaid($invoice);

        return back()->with('status', $invoice->paid_on === null ? 'Als offen markiert.' : 'Als bezahlt markiert.');
    }

    public function sent(Invoice $invoice)
    {
        abort_if($invoice->number === null, 404);
        Fakturierung::markSent($invoice);

        return back()->with('status', 'Als versendet markiert.');
    }

    public function mail(Request $request, Invoice $invoice)
    {
        $data = $request->validate(['to' => ['required', 'email', 'max:190'], 'subject' => ['required', 'string', 'max:190'], 'body' => ['required', 'string', 'max:5000']]);
        $err = Fakturierung::sendMail($invoice, $request->user(), $data['to'], $data['subject'], $data['body']);
        if ($err !== null) {
            return back()->withInput()->withErrors(['mail' => $err]);
        }

        return back()->with('status', 'Die E-Mail mit der '.$invoice->kindLabel().' ist versendet.');
    }

    public function pdf(Invoice $invoice)
    {
        abort_if($invoice->number === null, 404);

        return response(Fakturierung::pdf($invoice), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.Fakturierung::fileName($invoice, 'pdf').'"']);
    }

    public function xml(Invoice $invoice)
    {
        abort_if($invoice->xml === null, 404);

        return response((string) $invoice->xml, 200, ['Content-Type' => 'application/xml; charset=utf-8', 'Content-Disposition' => 'attachment; filename="'.Fakturierung::fileName($invoice, 'xml').'"']);
    }

    public function view(Invoice $invoice)
    {
        $invoice->load('lines');

        return view('invoices.print', ['inv' => $invoice, 'sum' => Fakturierung::totals($invoice)]);
    }

    public function settings()
    {
        return view('invoices.settings', ['d' => Aussteller::data(), 'issues' => Aussteller::issues()]);
    }

    public function saveSettings(Request $request)
    {
        $data = $request->validate([
            'inv_name' => ['nullable', 'string', 'max:190'], 'inv_street' => ['nullable', 'string', 'max:190'], 'inv_zip' => ['nullable', 'string', 'max:10'],
            'inv_city' => ['nullable', 'string', 'max:120'], 'inv_phone' => ['nullable', 'string', 'max:60'], 'inv_email' => ['nullable', 'email', 'max:190'],
            'inv_vat_id' => ['nullable', 'string', 'max:20'], 'inv_tax_number' => ['nullable', 'string', 'max:30'], 'inv_iban' => ['nullable', 'string', 'max:40'],
            'inv_bic' => ['nullable', 'string', 'max:15'], 'inv_holder' => ['nullable', 'string', 'max:190'], 'inv_due_days' => ['nullable', 'integer', 'min:0', 'max:180'],
            'inv_tax_mode' => ['required', 'in:regel,klein'], 'inv_note' => ['nullable', 'string', 'max:1000'],
        ]);
        $values = [];
        foreach (array_keys(Aussteller::FIELDS) as $k) {
            $values[$k] = isset($data[$k]) ? trim((string) $data[$k]) : '';
        }
        $values['inv_vat_id'] = strtoupper(preg_replace('/\s+/', '', $values['inv_vat_id']) ?? '');
        $values['inv_iban'] = strtoupper(preg_replace('/\s+/', '', $values['inv_iban']) ?? '');
        Settings::putMany($values);

        return redirect()->route('invoices.settings')->with('status', 'Die Rechnungsdaten sind gespeichert.');
    }

    private function formData(?Invoice $inv): array
    {
        $user = request()->user();

        return [
            'inv' => $inv, 'lines' => $inv?->lines ?? collect(),
            'tenants' => Tenant::query()->visibleTo($user)->orderBy('display_name')->get(['id', 'display_name', 'company_name', 'type']),
            'owners' => Owner::query()->visibleTo($user)->orderBy('name')->get(['id', 'name', 'vat_id']),
            'mode' => Aussteller::data()['inv_tax_mode'],
        ];
    }

    /** Formular lesen und prüfen. Fehler kommen in $errors, damit nichts Halbes gespeichert wird. */
    private function parse(Request $request, array &$errors): array
    {
        $user = $request->user();
        $kind = in_array($request->input('recipient_kind'), ['tenant', 'owner', 'free'], true) ? $request->input('recipient_kind') : 'free';
        $tenantId = null;
        $ownerId = null;
        $buyer = [
            'name' => trim((string) $request->input('b_name')), 'street' => trim((string) $request->input('b_street')), 'zip' => trim((string) $request->input('b_zip')),
            'city' => trim((string) $request->input('b_city')), 'email' => trim((string) $request->input('b_email')),
            'vat_id' => strtoupper(preg_replace('/\s+/', '', (string) $request->input('b_vat_id')) ?? ''), 'business' => $request->boolean('b_business'),
        ];
        if ($kind === 'tenant') {
            $t = Tenant::query()->visibleTo($user)->find((int) $request->input('tenant_id'));
            if ($t === null) {
                $errors['tenant_id'] = 'Bitte einen Mieter wählen.';
            } else {
                $tenantId = $t->id;
                $buyer = array_merge(Fakturierung::buyerFromTenant($t), ['business' => $request->boolean('b_business')]);
            }
        } elseif ($kind === 'owner') {
            $o = Owner::query()->visibleTo($user)->find((int) $request->input('owner_id'));
            if ($o === null) {
                $errors['owner_id'] = 'Bitte einen Eigentümer wählen.';
            } else {
                $ownerId = $o->id;
                $buyer = array_merge(Fakturierung::buyerFromOwner($o), ['business' => $request->boolean('b_business')]);
            }
        } elseif ($buyer['name'] === '') {
            $errors['b_name'] = 'Bitte den Namen des Empfängers eingeben.';
        }

        $date = function (string $key) use ($request, &$errors): ?string {
            $v = trim((string) $request->input($key));
            if ($v === '') {
                return null;
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) !== 1 || strtotime($v) === false) {
                $errors[$key] = 'Bitte ein gültiges Datum eingeben.';

                return null;
            }

            return $v;
        };
        $lines = [];
        foreach ((array) $request->input('lines', []) as $i => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $priceRaw = trim((string) ($row['price'] ?? ''));
            if ($name === '' && $priceRaw === '') {
                continue;
            }
            $n = count($lines) + 1;
            $price = $priceRaw === '' ? null : Money::parse($priceRaw);
            $qtyRaw = str_replace(',', '.', trim((string) ($row['qty'] ?? '')));
            $qty = $qtyRaw === '' ? 1.0 : (is_numeric($qtyRaw) ? (float) $qtyRaw : null);
            if ($name === '') {
                $errors['lines.'.$i.'.name'] = 'Position '.$n.': Bezeichnung fehlt.';
            }
            if ($price === null) {
                $errors['lines.'.$i.'.price'] = 'Position '.$n.': Bitte einen Preis eingeben.';
            }
            if ($qty === null || $qty <= 0 || $qty > 999999) {
                $errors['lines.'.$i.'.qty'] = 'Position '.$n.': Die Menge muss größer als null sein.';
            }
            $rate = (int) ($row['rate'] ?? 19);
            $unit = array_key_exists((string) ($row['unit'] ?? ''), Berechnung::UNITS) ? (string) $row['unit'] : 'C62';
            $lines[] = [
                'name' => mb_substr($name, 0, 300), 'quantity' => $qty ?? 1, 'unit' => $unit, 'unit_net' => $price ?? '0.00',
                'vat_rate' => in_array($rate, Berechnung::RATES, true) ? $rate : 19, 'exemption' => mb_substr(trim((string) ($row['exempt'] ?? '')), 0, 190) ?: null,
            ];
        }
        if ($lines === []) {
            $errors['lines'] = 'Bitte mindestens eine Position eintragen.';
        }

        return [
            'recipient_kind' => $kind, 'tenant_id' => $tenantId, 'owner_id' => $ownerId, 'buyer' => $buyer, 'issued_on' => $date('issued_on'), 'due_on' => $date('due_on'),
            'delivery_from' => $date('delivery_from'), 'delivery_to' => $date('delivery_to'), 'buyer_reference' => mb_substr(trim((string) $request->input('buyer_reference')), 0, 100) ?: null,
            'note' => mb_substr(trim((string) $request->input('note')), 0, 2000) ?: null, 'lines' => $lines,
        ];
    }
}
