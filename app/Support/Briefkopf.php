<?php

namespace App\Support;

use App\Models\Owner;

/**
 * Briefkopf für alle Schreiben (02.11.2026): links immer das Logo „Haus mit B“, rechts und in der Fußzeile
 * die Angaben des jeweiligen Eigentümers (bzw. bei Rechnungen des Rechnungstellers).
 */
class Briefkopf
{
    /**
     * Einheitliche Angaben aus einem Eigentümer oder einem Datenfeld (Rechnungssteller, E-Rechnung).
     *
     * @return array{name: string, street: string, zip: string, city: string, phone: string, email: string, bank: string, iban: string, bic: string, holder: string, tax_number: string, vat_id: string}
     */
    public static function daten(mixed $from): array
    {
        $d = ['name' => '', 'street' => '', 'zip' => '', 'city' => '', 'phone' => '', 'email' => '', 'bank' => '', 'iban' => '', 'bic' => '', 'holder' => '', 'tax_number' => '', 'vat_id' => ''];
        if ($from instanceof Owner) {
            $account = null;
            try {
                $account = $from->bankAccounts()->orderByDesc('is_primary')->orderBy('id')->first();
            } catch (\Throwable) {
                $account = null;
            }
            foreach (['name', 'street', 'zip', 'city', 'phone', 'email', 'tax_number', 'vat_id'] as $k) {
                $d[$k] = trim((string) ($from->{$k} ?? ''));
            }
            if ($account) {
                $d['bank'] = trim((string) $account->bank_name);
                $d['iban'] = trim((string) $account->iban);
                $d['bic'] = trim((string) $account->bic);
                $d['holder'] = trim((string) $account->account_holder);
            }
        } elseif (is_array($from)) {
            $pick = fn (array $keys) => trim((string) (collect($keys)->map(fn ($k) => $from[$k] ?? null)->first(fn ($v) => $v !== null && trim((string) $v) !== '') ?? ''));
            $d = [
                'name' => $pick(['name']), 'street' => $pick(['street']), 'zip' => $pick(['zip']), 'city' => $pick(['city']),
                'phone' => $pick(['phone']), 'email' => $pick(['email']), 'bank' => $pick(['bank', 'bank_name']),
                'iban' => $pick(['iban']), 'bic' => $pick(['bic']), 'holder' => $pick(['holder', 'account_holder']),
                'tax_number' => $pick(['tax_number', 'tax']), 'vat_id' => $pick(['vat_id', 'vat']),
            ];
        }
        $d['iban'] = strtoupper((string) preg_replace('/\s+/', '', $d['iban']));

        return $d;
    }

    public static function iban(string $iban): string
    {
        return trim(chunk_split($iban, 4, ' '));
    }
}
