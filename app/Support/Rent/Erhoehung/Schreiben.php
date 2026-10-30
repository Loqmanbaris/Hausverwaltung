<?php

namespace App\Support\Rent\Erhoehung;

use App\Models\RentIncrease;
use App\Support\Mail\Outbox;
use App\Support\Rent\Mieten;

/** Zusammenstellung der Angaben für das Mieterhöhungsschreiben (Druckansicht und PDF, Paket 27). */
class Schreiben
{
    /** @return array<string, mixed> */
    public static function data(RentIncrease $inc): array
    {
        $inc->loadMissing(['lease.unit.property', 'lease.tenant.contacts', 'lease.rents', 'owner']);
        $lease = $inc->lease;
        $tenant = $lease?->tenant;
        $unit = $lease?->unit;
        $property = $unit?->property;
        $owner = $inc->owner;
        $contact = $tenant?->type === 'firma' ? $tenant->contacts->firstWhere('receives_invoices', true) : null;

        $effective = $inc->effective_from->format('Y-m-d');
        $src = $lease?->rents->filter(fn ($r) => $r->valid_from->format('Y-m-d') < $effective && $r->id !== $inc->lease_rent_id)->last() ?? $lease?->currentRent($effective);
        $oldParts = [];
        $newParts = [];
        foreach (Mieten::COMPONENTS as $col => $label) {
            $old = (float) ($src?->{$col} ?? 0);
            $new = $col === 'rent_cold' ? (float) $inc->new_cold : $old;
            $oldParts[$col] = $col === 'rent_cold' ? (float) $inc->old_cold : $old;
            $newParts[$col] = $new;
        }
        $vat = (float) ($lease?->vat_rate ?? 0);
        $oldNet = array_sum($oldParts);
        $newNet = array_sum($newParts);

        $area = $inc->area_sqm !== null ? (float) $inc->area_sqm : null;

        return [
            'inc' => $inc,
            'lease' => $lease,
            'tenant' => $tenant,
            'unit' => $unit,
            'property' => $property,
            'owner' => $owner,
            'contact' => $contact,
            'salutation' => Outbox::salutation($tenant),
            'components' => Mieten::COMPONENTS,
            'old_parts' => $oldParts,
            'new_parts' => $newParts,
            'old_net' => $oldNet,
            'new_net' => $newNet,
            'vat' => $vat,
            'new_gross' => round($newNet * (1 + $vat / 100), 2),
            'area' => $area,
            'old_sqm' => $area ? (float) $inc->old_cold / $area : null,
            'new_sqm' => $area ? (float) $inc->new_cold / $area : null,
            'timeline' => Regeln::timeline($inc->kind, $inc->notice_on->format('Y-m-d'), (bool) $inc->datum('delayed')),
            'data' => $inc->data ?? [],
        ];
    }

    public static function title(RentIncrease $inc): string
    {
        return match ($inc->kind) {
            'vergleich' => 'Mieterhöhungsverlangen nach § 558 BGB',
            'modernisierung' => ($inc->datum('heating') && (float) $inc->datum('subsidies', 0) > 0 ? 'Mieterhöhung nach Heizungstausch (§ 559e BGB)' : 'Mieterhöhung nach Modernisierung (§ 559 BGB)'),
            'index' => 'Mieterhöhung wegen Änderung des Verbraucherpreisindex (§ 557b BGB)',
            'staffel' => 'Mitteilung zur vereinbarten Staffelmiete',
            default => 'Vorschlag zur Anpassung der Miete',
        };
    }

    public static function pdfName(RentIncrease $inc): string
    {
        return Outbox::fileName('Mieterhoehung', $inc->kindLabel(), $inc->effective_from->format('Y-m-d'), $inc->lease?->unit?->label);
    }
}
