<?php

namespace App\Support;

use Closure;

class Stammdaten
{
    public const TAX_STATUS = [
        'regel' => 'Regelbesteuerung',
        'klein' => 'Kleinunternehmer',
    ];

    public const USAGE = [
        'wohnen' => 'Wohnen',
        'gewerbe_option' => 'Gewerbe mit USt-Option',
        'gewerbe_ohne_option' => 'Gewerbe ohne USt-Option',
        'montage' => 'Montage-Wohnung (Beherbergung)',
    ];

    public const HEATING = [
        'zentral_oel' => 'Zentralheizung Öl',
        'zentral_gas' => 'Zentralheizung Gas',
        'fernwaerme' => 'Fernwärme',
        'etage_gas' => 'Etagenheizung Gas',
        'strom' => 'Strom (elektrisch)',
        'waermepumpe' => 'Wärmepumpe',
        'keine' => 'Keine Heizung',
    ];

    public const HOT_WATER = [
        'zentral' => 'zentral',
        'dezentral' => 'dezentral',
    ];

    public const TENANT_TYPE = [
        'person' => 'Privatperson',
        'firma' => 'Firma',
    ];

    public static function messages(): array
    {
        return [
            'required' => 'Bitte „:attribute“ ausfüllen.',
            'required_if' => 'Bitte „:attribute“ ausfüllen.',
            'email' => '„:attribute“ ist keine gültige E-Mail-Adresse.',
            'max.string' => '„:attribute“ ist zu lang (höchstens :max Zeichen).',
            'min.numeric' => '„:attribute“ darf nicht kleiner als :min sein.',
            'max.numeric' => '„:attribute“ darf nicht größer als :max sein.',
            'numeric' => '„:attribute“ muss eine Zahl sein (z. B. 55,5).',
            'integer' => '„:attribute“ ist ungültig.',
            'in' => 'Bei „:attribute“ bitte einen Wert aus der Liste wählen.',
            'regex' => '„:attribute“ hat ein ungültiges Format.',
            'array' => '„:attribute“ ist ungültig.',
        ];
    }

    public static function attributes(): array
    {
        return [
            'name' => 'Name',
            'street' => 'Straße und Hausnummer',
            'zip' => 'PLZ',
            'city' => 'Ort',
            'tax_number' => 'Steuernummer',
            'vat_id' => 'USt-ID',
            'tax_status' => 'Steuerstatus',
            'creditor_id' => 'Gläubiger-ID',
            'email' => 'E-Mail',
            'phone' => 'Telefon',
            'notes' => 'Notizen',
            'owner_id' => 'Eigentümer',
            'label' => 'Bezeichnung',
            'account_holder' => 'Kontoinhaber',
            'iban' => 'IBAN',
            'bic' => 'BIC',
            'bank_name' => 'Bank',
            'heating_type' => 'Heizart',
            'hot_water_type' => 'Warmwasser',
            'floor' => 'Etage',
            'usage_type' => 'Nutzungsart',
            'area_sqm' => 'Fläche',
            'rooms' => 'Zimmer',
            'equipment' => 'Ausstattung',
            'type' => 'Art',
            'company_name' => 'Firmenname',
            'first_name' => 'Vorname',
            'last_name' => 'Nachname',
            'role' => 'Funktion',
            'users' => 'Mitarbeiter',
            'users.*' => 'Mitarbeiter',
        ];
    }

    public static function normalizeIban(mixed $value): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', (string) $value));
    }

    public static function normalizeCode(mixed $value): ?string
    {
        $v = strtoupper((string) preg_replace('/\s+/', '', (string) $value));

        return $v === '' ? null : $v;
    }

    /** Prüfsumme nach ISO 13616 (Modulo 97). */
    public static function ibanValid(string $iban): bool
    {
        $iban = self::normalizeIban($iban);

        if (! preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $iban)) {
            return false;
        }

        $moved = substr($iban, 4).substr($iban, 0, 4);
        $digits = '';
        foreach (str_split($moved) as $char) {
            $digits .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
        }

        $remainder = 0;
        foreach (str_split($digits) as $digit) {
            $remainder = ($remainder * 10 + (int) $digit) % 97;
        }

        return $remainder === 1;
    }

    public static function ibanRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! self::ibanValid((string) $value)) {
                $fail('Die IBAN ist nicht gültig. Bitte noch einmal prüfen.');
            }
        };
    }

    /** Kommazahlen wie „55,5“ in „55.5“ umwandeln. */
    public static function decimalFields(\Illuminate\Http\Request $request, array $fields): void
    {
        foreach ($fields as $field) {
            if ($request->filled($field)) {
                $request->merge([$field => str_replace(',', '.', (string) $request->input($field))]);
            }
        }
    }
}
