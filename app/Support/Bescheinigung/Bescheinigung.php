<?php

namespace App\Support\Bescheinigung;

use App\Models\Lease;
use App\Models\RentCharge;
use App\Support\Mail\Outbox;
use App\Support\Rent\Mieten;
use DateTimeImmutable;

/**
 * Bescheinigungen zum Mietvertrag (Paket 44):
 * - Wohnungsgeberbestätigung nach § 19 Bundesmeldegesetz (Pflicht bei Ein- und Auszug, binnen zwei Wochen)
 * - Mietschuldenfreiheitsbescheinigung (für den neuen Vermieter des Mieters)
 * Reine Logik plus Zusammenstellen der Daten; gespeichert wird nichts, nur protokolliert.
 */
class Bescheinigung
{
    public const VORGAENGE = ['einzug' => 'Einzug', 'auszug' => 'Auszug'];

    /** Namen der meldepflichtigen Personen: eine je Zeile, ohne Leerzeilen und Doppelte, höchstens 20. */
    public static function personen(string $text): array
    {
        $out = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $name = trim((string) preg_replace('/\s+/u', ' ', $line));
            if ($name !== '' && ! in_array($name, $out, true)) {
                $out[] = mb_substr($name, 0, 120);
            }
        }

        return array_slice($out, 0, 20);
    }

    /** Vorschlag für die Personen: der Mieter (bei Firmen leer, dort sind es die Bewohner). */
    public static function personenVorschlag(Lease $lease): string
    {
        $t = $lease->tenant;
        if ($t === null || $t->type === 'firma') {
            return '';
        }

        return trim(($t->first_name ?? '').' '.($t->last_name ?? '')) ?: (string) $t->display_name;
    }

    /** Datum im Format TT.MM.JJJJ aus JJJJ-MM-TT (oder leer). */
    public static function de(?string $date): string
    {
        if ($date === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return '';
        }

        return (new DateTimeImmutable($date))->format('d.m.Y');
    }

    /**
     * Offene Beträge (Miete, Nachzahlungen, Gebühren), die bis zum Stichtag fällig waren.
     */
    public static function offenBis(Lease $lease, string $stichtag): float
    {
        $v = (float) RentCharge::query()->where('lease_id', $lease->id)->where('due_date', '<=', $stichtag)
            ->selectRaw('coalesce(sum(total - paid_amount), 0) as o')->value('o');

        return round(max(0, $v), 2);
    }

    /** Gemeinsame Angaben für beide Schreiben. */
    public static function basis(Lease $lease): array
    {
        $lease->loadMissing(['unit.property', 'tenant', 'owner']);
        $unit = $lease->unit;
        $property = $unit?->property;
        $tenant = $lease->tenant;
        $owner = $lease->owner;
        $lage = trim(implode(', ', array_filter([
            $unit?->label,
            $unit?->floor !== null && $unit?->floor !== '' ? self::geschoss((string) $unit->floor) : null,
        ])));

        return [
            'lease' => $lease,
            'unit' => $unit,
            'property' => $property,
            'tenant' => $tenant,
            'owner' => $owner,
            'lage' => $lage,
            'mieter' => $tenant?->type === 'firma' ? (string) $tenant->company_name : (trim(($tenant?->first_name ?? '').' '.($tenant?->last_name ?? '')) ?: (string) $tenant?->display_name),
            'anrede' => Outbox::salutation($tenant),
            'wohnung' => trim(($property?->street ?? '').', '.trim(($property?->zip ?? '').' '.($property?->city ?? '')), ', '),
            'beginn' => $lease->start_date?->format('Y-m-d'),
            'ende' => $lease->end_date?->format('Y-m-d'),
        ];
    }

    /** „2“ → „2. OG“, „0“/„EG“ → „EG“, sonst unverändert. */
    public static function geschoss(string $floor): string
    {
        $f = trim($floor);
        if ($f === '0' || mb_strtolower($f) === 'eg') {
            return 'EG';
        }
        if (preg_match('/^-?\d+$/', $f) === 1) {
            return (int) $f < 0 ? abs((int) $f).'. UG' : $f.'. OG';
        }

        return $f;
    }

    /** Monatliche Miete laut Vertrag am Stichtag: [Kaltmiete, Vorauszahlungen und Zuschläge, gesamt]. */
    public static function miete(Lease $lease, string $stichtag): array
    {
        $r = $lease->currentRent($stichtag);
        if ($r === null) {
            return [0.0, 0.0, 0.0];
        }
        $cold = (float) $r->rent_cold;
        $other = 0.0;
        foreach (array_keys(Mieten::COMPONENTS) as $col) {
            if ($col !== 'rent_cold') {
                $other += (float) $r->{$col};
            }
        }
        $vat = 1 + (float) $lease->vat_rate / 100;

        return [round($cold * $vat, 2), round($other * $vat, 2), round(($cold + $other) * $vat, 2)];
    }
}
