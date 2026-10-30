<?php

namespace App\Support\ERechnung;

use App\Models\IssuedInvoice;
use App\Models\Lease;
use App\Models\Owner;
use App\Models\RentCharge;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit;
use App\Support\Rent\Erhoehung\Regeln;
use App\Support\Rent\Mieten;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** E-Rechnungen aus Monatsmieten erstellen, stornieren, Pflichtangaben prüfen (Paket 37). */
class Rechnungen
{
    public static function sellerData(Owner $o): array
    {
        $account = $o->bankAccounts()->orderByDesc('is_primary')->orderBy('id')->first();

        return [
            'seller' => [
                'name' => (string) $o->name, 'street' => (string) $o->street, 'zip' => (string) $o->zip, 'city' => (string) $o->city,
                'vat_id' => (string) $o->vat_id, 'tax_number' => (string) $o->tax_number, 'contact_name' => (string) $o->name, 'phone' => (string) $o->phone, 'email' => (string) $o->email,
            ],
            'iban' => (string) ($account?->iban ?? ''), 'bic' => (string) ($account?->bic ?? ''), 'account_holder' => (string) ($account?->account_holder ?? ''),
        ];
    }

    public static function buyerData(Tenant $t): array
    {
        $contact = $t->contacts()->where('receives_invoices', true)->whereNotNull('email')->first();

        return [
            'name' => (string) ($t->type === 'firma' ? $t->company_name : $t->display_name), 'street' => (string) $t->street, 'zip' => (string) $t->zip, 'city' => (string) $t->city,
            'email' => (string) ($contact?->email ?: $t->email), 'vat_id' => (string) $t->vat_id,
        ];
    }

    /** Fehlende Angaben je Eigentümer (ohne Mieter und Posten), für die Einrichtungsliste. */
    public static function ownerIssues(Owner $o): array
    {
        $d = self::sellerData($o) + ['buyer' => ['name' => 'x', 'street' => 'x', 'zip' => 'x', 'city' => 'x', 'email' => 'x'], 'lines' => [['name' => 'x', 'net' => 1]], 'type' => 380];

        return array_values(array_filter(XRechnung::missing($d), fn ($m) => str_starts_with($m, 'Eigentümer')));
    }

    /** Verträge, für die eine E-Rechnung nötig sein kann: umsatzsteuerpflichtig und an ein Unternehmen vermietet. */
    public static function eligibleLeases(User $user)
    {
        return Lease::query()->visibleTo($user)->where('vat_rate', '>', 0)->whereHas('tenant', fn ($q) => $q->where('type', 'firma'))->with(['unit.property', 'tenant', 'owner']);
    }

    /**
     * Monatsmieten, für die noch keine Rechnung besteht.
     *
     * @return \Illuminate\Support\Collection<int, RentCharge>
     */
    public static function pending(User $user, ?string $today = null, int $daysBack = 120, int $daysAhead = 45)
    {
        $today ??= date('Y-m-d');
        $leaseIds = self::eligibleLeases($user)->pluck('leases.id');
        if ($leaseIds->isEmpty()) {
            return collect();
        }
        $done = IssuedInvoice::query()->whereNotNull('rent_charge_id')->pluck('rent_charge_id');

        return RentCharge::query()->whereIn('lease_id', $leaseIds)->where('kind', 'miete')->where('total', '>', 0)
            ->whereBetween('due_date', [date('Y-m-d', strtotime($today.' -'.$daysBack.' days')), date('Y-m-d', strtotime($today.' +'.$daysAhead.' days'))])
            ->whereNotIn('id', $done)->with(['lease.unit.property', 'lease.tenant', 'lease.owner'])->orderBy('due_date')->get();
    }

    public static function nextNumber(int $ownerId, string $type, int $year): string
    {
        $prefix = $type === '381' ? 'G' : 'R';
        $n = IssuedInvoice::query()->where('owner_id', $ownerId)->where('type', $type)->where('number', 'like', $prefix.$year.'-%')->count() + 1;

        return $prefix.$year.'-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    /** Daten für die XML-Datei aus einem Soll-Posten. */
    public static function dataFromCharge(RentCharge $c, string $number, string $issuedOn): array
    {
        $lease = $c->lease;
        $owner = $lease->owner;
        $tenant = $lease->tenant;
        $month = $c->period->format('m/Y');
        $lines = [];
        foreach (Mieten::COMPONENTS as $col => $label) {
            $cents = Regeln::cents($c->{$col} ?? 0);
            if ($cents > 0) {
                $lines[] = ['name' => $label.' '.$month.' ('.$lease->unit?->label.')', 'net' => $cents];
            }
        }
        $due = max($c->due_date->format('Y-m-d'), $issuedOn);

        return self::sellerData($owner) + [
            'type' => 380, 'number' => $number, 'issued_on' => $issuedOn, 'due_on' => $due,
            'period_from' => $c->period_from->format('Y-m-d'), 'period_to' => $c->period_to->format('Y-m-d'),
            'buyer_reference' => 'Mietvertrag '.$lease->id, 'note' => 'Miete für '.$lease->unit?->label.', '.$lease->unit?->property?->addressLine().' für '.$month,
            'buyer' => self::buyerData($tenant), 'rate' => (float) $c->vat_rate, 'lines' => $lines,
        ];
    }

    /** Erstellt die Rechnung zu einem Soll-Posten. Fehlende Pflichtangaben und ein Schemafehler brechen ab, damit nie eine unbrauchbare Rechnung entsteht. */
    public static function createFromCharge(RentCharge $c, User $user, ?string $issuedOn = null): IssuedInvoice
    {
        $issuedOn ??= date('Y-m-d');
        $c->loadMissing(['lease.unit.property', 'lease.tenant.contacts', 'lease.owner']);
        if (IssuedInvoice::query()->where('rent_charge_id', $c->id)->exists()) {
            throw new RuntimeException('Für diese Miete gibt es schon eine Rechnung.');
        }

        return DB::transaction(function () use ($c, $user, $issuedOn) {
            $owner = $c->lease->owner;
            $number = self::nextNumber((int) $owner->id, '380', (int) substr($issuedOn, 0, 4));
            $data = self::dataFromCharge($c, $number, $issuedOn);
            $missing = XRechnung::missing($data);
            if ($missing !== []) {
                throw new RuntimeException(implode(' ', $missing));
            }
            $xml = XRechnung::build($data);
            $errors = XRechnung::schemaErrors($xml);
            if ($errors !== []) {
                throw new RuntimeException('Die erzeugte Datei besteht die Schema-Prüfung nicht: '.$errors[0]);
            }
            $t = XRechnung::totals($data['lines'], $data['rate']);
            $inv = IssuedInvoice::create([
                'owner_id' => $owner->id, 'lease_id' => $c->lease_id, 'rent_charge_id' => $c->id, 'type' => '380', 'number' => $number,
                'issued_on' => $issuedOn, 'due_on' => $data['due_on'], 'period_from' => $data['period_from'], 'period_to' => $data['period_to'],
                'net' => Regeln::euro($t['net']), 'vat' => Regeln::euro($t['vat']), 'gross' => Regeln::euro($t['gross']), 'vat_rate' => $data['rate'],
                'status' => 'erstellt', 'xml' => $xml, 'created_by' => $user->id,
            ]);
            Audit::log('einvoice.created', $inv, 'E-Rechnung '.$number.' erstellt: '.$c->lease->title().' ('.Regeln::eur($t['gross']).')');

            return $inv;
        });
    }

    /** Storniert eine Rechnung durch eine Gutschrift in voller Höhe. */
    public static function credit(IssuedInvoice $orig, User $user, ?string $issuedOn = null): IssuedInvoice
    {
        if ($orig->isCredit() || $orig->status === 'storniert') {
            throw new RuntimeException('Diese Rechnung kann nicht noch einmal storniert werden.');
        }
        $issuedOn ??= date('Y-m-d');
        $orig->loadMissing(['lease.unit.property', 'lease.tenant.contacts', 'owner']);

        return DB::transaction(function () use ($orig, $user, $issuedOn) {
            $number = self::nextNumber((int) $orig->owner_id, '381', (int) substr($issuedOn, 0, 4));
            $lease = $orig->lease;
            $lines = [];
            foreach (self::linesOf($orig) as $l) {
                $lines[] = $l;
            }
            $data = self::sellerData($orig->owner) + [
                'type' => 381, 'number' => $number, 'issued_on' => $issuedOn, 'due_on' => $issuedOn,
                'period_from' => $orig->period_from?->format('Y-m-d'), 'period_to' => $orig->period_to?->format('Y-m-d'),
                'buyer_reference' => 'Mietvertrag '.$lease->id, 'note' => 'Gutschrift zur Rechnung '.$orig->number,
                'original' => ['number' => $orig->number, 'issued_on' => $orig->issued_on->format('Y-m-d')],
                'buyer' => self::buyerData($lease->tenant), 'rate' => (float) $orig->vat_rate, 'lines' => $lines,
            ];
            $missing = XRechnung::missing($data);
            if ($missing !== []) {
                throw new RuntimeException(implode(' ', $missing));
            }
            $xml = XRechnung::build($data);
            $errors = XRechnung::schemaErrors($xml);
            if ($errors !== []) {
                throw new RuntimeException('Die erzeugte Datei besteht die Schema-Prüfung nicht: '.$errors[0]);
            }
            $t = XRechnung::totals($lines, $data['rate']);
            $c = IssuedInvoice::create([
                'owner_id' => $orig->owner_id, 'lease_id' => $orig->lease_id, 'original_id' => $orig->id, 'type' => '381', 'number' => $number,
                'issued_on' => $issuedOn, 'due_on' => $issuedOn, 'period_from' => $data['period_from'], 'period_to' => $data['period_to'],
                'net' => Regeln::euro($t['net']), 'vat' => Regeln::euro($t['vat']), 'gross' => Regeln::euro($t['gross']), 'vat_rate' => $data['rate'],
                'status' => 'erstellt', 'xml' => $xml, 'created_by' => $user->id,
            ]);
            $orig->update(['status' => 'storniert']);
            Audit::log('einvoice.credited', $orig, 'Rechnung '.$orig->number.' durch Gutschrift '.$number.' storniert');

            return $c;
        });
    }

    /** Positionen einer gespeicherten Rechnung (aus der XML-Datei gelesen). */
    public static function linesOf(IssuedInvoice $inv): array
    {
        $doc = new \DOMDocument();
        @$doc->loadXML($inv->xml);
        $x = new \DOMXPath($doc);
        $x->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $x->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $out = [];
        foreach ($x->query('//cac:InvoiceLine') as $line) {
            $out[] = [
                'name' => (string) $x->evaluate('string(cac:Item/cbc:Name)', $line),
                'net' => (int) round(((float) $x->evaluate('string(cbc:LineExtensionAmount)', $line)) * 100),
            ];
        }

        return $out;
    }

    public static function markSent(IssuedInvoice $inv): void
    {
        if ($inv->status === 'erstellt') {
            $inv->update(['status' => 'versendet', 'sent_on' => date('Y-m-d')]);
        }
    }

    public static function fileName(IssuedInvoice $inv, string $ext): string
    {
        return preg_replace('/[^A-Za-z0-9._-]+/', '_', $inv->number).'.'.$ext;
    }
}
