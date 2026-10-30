<?php

namespace App\Support\Rechnung;

use App\Models\Invoice;

/** Prüft eine Rechnung auf die Pflichtangaben nach § 14 Abs. 4 UStG und die Voraussetzungen der E-Rechnung (Paket 45). */
class Pflicht
{
    /**
     * @param  array<string, mixed>  $seller  Stand der Hausverwaltung
     * @return array<int, string> fehlende oder falsche Angaben in verständlichen Worten
     */
    public static function check(Invoice $inv, array $seller, ?string $issuedOn = null): array
    {
        $e = Aussteller::issues($seller);
        $b = (array) $inv->buyer;
        foreach (['name' => 'Name', 'street' => 'Straße', 'zip' => 'PLZ', 'city' => 'Ort'] as $k => $l) {
            if (trim((string) ($b[$k] ?? '')) === '') {
                $e[] = 'Empfänger: '.$l.' fehlt.';
            }
        }
        $business = ! empty($b['business']);
        if ($business && trim((string) ($b['email'] ?? '')) === '') {
            $e[] = 'Empfänger: E-Mail-Adresse fehlt (bei Unternehmen nötig für die E-Rechnung).';
        }
        if (trim((string) ($b['vat_id'] ?? '')) !== '' && ! Berechnung::validVatId((string) $b['vat_id'])) {
            $e[] = 'Empfänger: Die Umsatzsteuer-ID sieht nicht richtig aus (DE und neun Ziffern).';
        }
        if ($inv->delivery_from === null) {
            $e[] = 'Leistungsdatum oder Leistungszeitraum fehlt.';
        } elseif ($inv->delivery_to !== null && $inv->delivery_to < $inv->delivery_from) {
            $e[] = 'Das Ende des Leistungszeitraums liegt vor dem Beginn.';
        }
        $issued = $issuedOn ?? $inv->issued_on?->format('Y-m-d');
        if ($issued === null) {
            $e[] = 'Rechnungsdatum fehlt.';
        } elseif ($inv->due_on !== null && $inv->due_on->format('Y-m-d') < $issued) {
            $e[] = 'Das Zahlungsziel liegt vor dem Rechnungsdatum.';
        }

        $lines = $inv->lines;
        if ($lines->isEmpty()) {
            $e[] = 'Die Rechnung hat keine Positionen.';
        }
        $reasons = [];
        $small = ($seller['tax_mode'] ?? 'regel') === 'klein';
        foreach ($lines as $i => $l) {
            $n = $i + 1;
            if (trim((string) $l->name) === '') {
                $e[] = 'Position '.$n.': Bezeichnung fehlt.';
            }
            if ((float) $l->quantity <= 0) {
                $e[] = 'Position '.$n.': Menge muss größer als null sein.';
            }
            if (! in_array((int) round((float) $l->vat_rate), Berechnung::RATES, true) || abs((float) $l->vat_rate - round((float) $l->vat_rate)) > 0.001) {
                $e[] = 'Position '.$n.': Steuersatz muss 19, 7 oder 0 Prozent sein.';
            }
            if (! array_key_exists($l->unit, Berechnung::UNITS)) {
                $e[] = 'Position '.$n.': Einheit unbekannt.';
            }
            if ((float) $l->vat_rate <= 0) {
                if (trim((string) $l->exemption) === '') {
                    $e[] = 'Position '.$n.': Bei 0 Prozent muss der Grund der Steuerbefreiung stehen.';
                } else {
                    $reasons[trim((string) $l->exemption)] = true;
                }
            } elseif ($small) {
                $e[] = 'Position '.$n.': Als Kleinunternehmer (§ 19 UStG) darf keine Umsatzsteuer ausgewiesen werden. Steuersatz auf 0 Prozent stellen.';
            }
        }
        if (count($reasons) > 1) {
            $e[] = 'Steuerfreie Positionen: Bitte nur einen Befreiungsgrund je Rechnung angeben.';
        }

        return $e;
    }
}
