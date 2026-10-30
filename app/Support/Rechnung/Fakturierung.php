<?php

namespace App\Support\Rechnung;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Owner;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit;
use App\Support\ERechnung\XRechnung;
use App\Support\Mail\MailService;
use App\Support\Pdf\HtmlPdf;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Rechnungen schreiben, festschreiben, stornieren und versenden (Paket 45). */
class Fakturierung
{
    public static function buyerFromTenant(Tenant $t): array
    {
        $contact = $t->contacts()->where('receives_invoices', true)->whereNotNull('email')->first();

        return [
            'name' => (string) ($t->type === 'firma' ? $t->company_name : $t->display_name), 'street' => (string) $t->street, 'zip' => (string) $t->zip,
            'city' => (string) $t->city, 'email' => (string) ($contact?->email ?: $t->email), 'vat_id' => (string) $t->vat_id, 'business' => $t->type === 'firma',
        ];
    }

    public static function buyerFromOwner(Owner $o): array
    {
        return [
            'name' => (string) $o->name, 'street' => (string) $o->street, 'zip' => (string) $o->zip, 'city' => (string) $o->city,
            'email' => (string) $o->email, 'vat_id' => (string) $o->vat_id, 'business' => trim((string) $o->vat_id) !== '',
        ];
    }

    /**
     * Entwurf anlegen oder ändern.
     *
     * @param  array<string, mixed>  $d  recipient_kind, tenant_id, owner_id, buyer, issued_on, due_on, delivery_from, delivery_to, buyer_reference, note, lines
     */
    public static function save(?Invoice $inv, array $d, User $user): Invoice
    {
        if ($inv !== null && ! $inv->isDraft()) {
            throw new RuntimeException('Eine festgeschriebene Rechnung kann nicht mehr geändert werden. Bitte mit einer Gutschrift stornieren und neu schreiben.');
        }

        return DB::transaction(function () use ($inv, $d, $user) {
            $mode = Aussteller::data()['inv_tax_mode'];
            $attrs = [
                'recipient_kind' => $d['recipient_kind'], 'tenant_id' => $d['tenant_id'] ?? null, 'owner_id' => $d['owner_id'] ?? null, 'buyer' => $d['buyer'],
                'tax_mode' => $mode, 'issued_on' => $d['issued_on'] ?? null, 'due_on' => $d['due_on'] ?? null, 'delivery_from' => $d['delivery_from'] ?? null,
                'delivery_to' => $d['delivery_to'] ?? null, 'buyer_reference' => $d['buyer_reference'] ?? null, 'note' => $d['note'] ?? null,
            ];
            if ($inv === null) {
                $inv = Invoice::query()->create($attrs + ['type' => '380', 'status' => 'entwurf', 'created_by' => $user->id]);
            } else {
                $inv->update($attrs);
                $inv->lines()->delete();
            }
            foreach (array_values($d['lines']) as $i => $l) {
                $inv->lines()->create([
                    'pos' => $i + 1, 'name' => $l['name'], 'quantity' => $l['quantity'], 'unit' => $l['unit'], 'unit_net' => $l['unit_net'],
                    'vat_rate' => $mode === 'klein' ? 0 : $l['vat_rate'], 'exemption' => $mode === 'klein' ? Berechnung::SMALL_BUSINESS : ((float) $l['vat_rate'] <= 0 ? $l['exemption'] : null),
                ]);
            }
            self::storeTotals($inv->refresh());

            return $inv->refresh();
        });
    }

    /** @return array{groups: array, net: int, vat: int, gross: int} */
    public static function totals(Invoice $inv): array
    {
        $lines = [];
        foreach ($inv->lines()->get() as $l) {
            $lines[] = ['net' => Berechnung::lineNet((float) $l->quantity, (float) $l->unit_net), 'rate' => (float) $l->vat_rate, 'exemption' => $l->exemption];
        }

        return Berechnung::summary($lines);
    }

    private static function storeTotals(Invoice $inv): void
    {
        $t = self::totals($inv);
        $inv->update(['net' => $t['net'] / 100, 'vat' => $t['vat'] / 100, 'gross' => $t['gross'] / 100]);
    }

    /** Nächste Rechnungsnummer: RE-2026-0001 bzw. GS-2026-0001, fortlaufend je Jahr und Art. */
    public static function nextNumber(string $type, int $year): string
    {
        $prefix = $type === '381' ? 'GS' : 'RE';
        $max = 0;
        foreach (Invoice::query()->where('number', 'like', $prefix.'-'.$year.'-%')->lockForUpdate()->pluck('number') as $n) {
            $max = max($max, (int) substr((string) $n, strlen($prefix.'-'.$year.'-')));
        }

        return sprintf('%s-%d-%04d', $prefix, $year, $max + 1);
    }

    /** Festschreiben: prüft die Pflichtangaben, vergibt die Nummer, friert Aussteller und Beträge ein und erzeugt die XRechnung (bei Unternehmen). */
    public static function finalize(Invoice $inv, User $user, ?string $issuedOn = null): Invoice
    {
        if (! $inv->isDraft()) {
            throw new RuntimeException('Die Rechnung ist schon festgeschrieben.');
        }
        $inv->load('lines');
        $seller = Aussteller::snapshot();
        $issued = $issuedOn ?: ($inv->issued_on?->format('Y-m-d') ?: date('Y-m-d'));
        $inv->issued_on = $issued;
        if ($inv->due_on === null) {
            $inv->due_on = date('Y-m-d', strtotime($issued.' +'.(int) $seller['due_days'].' days'));
        }
        $errors = Pflicht::check($inv, $seller, $issued);
        if ($errors !== []) {
            throw new RuntimeException("Die Rechnung kann noch nicht festgeschrieben werden:\n- ".implode("\n- ", $errors));
        }

        return DB::transaction(function () use ($inv, $user, $seller, $issued) {
            $t = self::totals($inv);
            $inv->fill([
                'number' => self::nextNumber('380', (int) substr($issued, 0, 4)), 'seller' => $seller, 'status' => 'festgeschrieben', 'finalized_at' => now(),
                'issued_on' => $issued, 'net' => $t['net'] / 100, 'vat' => $t['vat'] / 100, 'gross' => $t['gross'] / 100, 'tax_mode' => $seller['tax_mode'],
            ]);
            $inv->save();
            self::attachXml($inv);
            Audit::log('invoice.finalized', $inv, 'Rechnung '.$inv->number.' festgeschrieben: '.number_format($t['gross'] / 100, 2, ',', '.').' €');

            return $inv->refresh();
        });
    }

    private static function attachXml(Invoice $inv): void
    {
        if (empty(((array) $inv->buyer)['business'])) {
            return; // an Privatpersonen ist keine E-Rechnung nötig
        }
        $xml = RechnungXml::build($inv->refresh());
        $errors = XRechnung::schemaErrors($xml);
        if ($errors !== []) {
            throw new RuntimeException('Die E-Rechnung ist nicht gültig: '.implode(' ', $errors));
        }
        $inv->update(['xml' => $xml]);
    }

    /** Stornieren: erzeugt eine Gutschrift über denselben Betrag. Die Rechnung gilt danach als storniert. */
    public static function credit(Invoice $orig, User $user, ?string $issuedOn = null): Invoice
    {
        if ($orig->isCredit() || $orig->isDraft() || $orig->status === 'storniert' || $orig->number === null) {
            throw new RuntimeException('Diese Rechnung kann nicht storniert werden.');
        }
        $issued = $issuedOn ?: date('Y-m-d');

        return DB::transaction(function () use ($orig, $user, $issued) {
            $c = Invoice::query()->create([
                'type' => '381', 'status' => 'festgeschrieben', 'recipient_kind' => $orig->recipient_kind, 'tenant_id' => $orig->tenant_id, 'owner_id' => $orig->owner_id,
                'buyer' => $orig->buyer, 'seller' => $orig->seller, 'tax_mode' => $orig->tax_mode, 'issued_on' => $issued, 'due_on' => $issued,
                'delivery_from' => $orig->delivery_from, 'delivery_to' => $orig->delivery_to, 'buyer_reference' => $orig->buyer_reference,
                'note' => 'Gutschrift zur Rechnung '.$orig->number.' vom '.$orig->issued_on->format('d.m.Y'), 'original_id' => $orig->id,
                'net' => $orig->net, 'vat' => $orig->vat, 'gross' => $orig->gross, 'finalized_at' => now(), 'created_by' => $user->id,
            ]);
            foreach ($orig->lines()->get() as $l) {
                $c->lines()->create($l->only(['pos', 'name', 'quantity', 'unit', 'unit_net', 'vat_rate', 'exemption']));
            }
            $c->update(['number' => self::nextNumber('381', (int) substr($issued, 0, 4))]);
            self::attachXml($c->refresh());
            $orig->update(['status' => 'storniert']);
            Audit::log('invoice.credited', $c, 'Gutschrift '.$c->number.' zur Rechnung '.$orig->number);

            return $c->refresh();
        });
    }

    public static function markSent(Invoice $inv): void
    {
        if ($inv->status === 'festgeschrieben') {
            $inv->update(['status' => 'versendet', 'sent_on' => date('Y-m-d')]);
        }
    }

    public static function togglePaid(Invoice $inv): void
    {
        $inv->update(['paid_on' => $inv->paid_on === null ? date('Y-m-d') : null]);
    }

    public static function fileName(Invoice $inv, string $ext): string
    {
        return $inv->kindLabel().'-'.preg_replace('/[^A-Za-z0-9._-]/', '-', (string) $inv->number).'.'.$ext;
    }

    public static function pdf(Invoice $inv): string
    {
        $html = view('invoices.print', ['inv' => $inv->loadMissing('lines'), 'sum' => self::totals($inv)])->render();

        return HtmlPdf::render($html, $inv->kindLabel().' '.$inv->number);
    }

    /** Rechnung per E-Mail mit PDF (und bei Unternehmen der XRechnung) senden. Gibt einen Fehlertext zurück oder null. */
    public static function sendMail(Invoice $inv, User $user, string $to, string $subject, string $body): ?string
    {
        if ($inv->number === null) {
            return 'Der Entwurf muss zuerst festgeschrieben werden.';
        }
        $attach = [['name' => self::fileName($inv, 'pdf'), 'data' => self::pdf($inv), 'mime' => 'application/pdf']];
        if ($inv->xml) {
            $attach[] = ['name' => self::fileName($inv, 'xml'), 'data' => (string) $inv->xml, 'mime' => 'application/xml'];
        }
        $log = MailService::send([$to], $subject, $body, $attach, ['kind' => 'erechnung', 'tenant_id' => $inv->tenant_id], $user, true);
        if ($log->status !== 'gesendet') {
            return MailService::friendlyError((string) $log->error);
        }
        self::markSent($inv);

        return null;
    }
}
