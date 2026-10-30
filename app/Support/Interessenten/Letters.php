<?php

namespace App\Support\Interessenten;

use App\Models\Applicant;
use App\Models\Listing;
use App\Support\Mail\Outbox;

/** Angaben für Zusage und Absage an Interessenten (Paket 31). */
class Letters
{
    /** @return array<string, mixed> */
    public static function data(Applicant $a, string $kind): array
    {
        $a->loadMissing(['unit.property', 'owner', 'listing']);
        $listing = $a->listing ?? ($a->unit_id ? Listing::query()->where('unit_id', $a->unit_id)->orderByDesc('id')->first() : null);

        return [
            'a' => $a,
            'kind' => $kind,
            'owner' => $a->owner,
            'unit' => $a->unit,
            'property' => $a->unit?->property,
            'listing' => $listing,
            'salutation' => $a->first_name ? 'Guten Tag '.$a->name().',' : 'Guten Tag '.$a->last_name.',',
            'date' => date('Y-m-d'),
            'keep_months' => Interessenten::KEEP_DECIDED,
        ];
    }

    public static function title(string $kind): string
    {
        return $kind === 'zusage' ? 'Zusage zur Vermietung' : 'Absage zur Wohnung';
    }

    public static function pdfName(Applicant $a, string $kind): string
    {
        return Outbox::fileName($kind === 'zusage' ? 'Zusage' : 'Absage', $a->name(), $a->unit?->label);
    }
}
