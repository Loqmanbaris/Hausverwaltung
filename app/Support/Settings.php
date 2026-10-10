<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Einstellungen des Systems (Tabelle settings). Vom Administrator unter „Einstellungen“ gepflegt.
 * Hier stehen keine Zugangsdaten, nur Angaben, die auch öffentlich erscheinen dürfen.
 */
class Settings
{
    /** Schlüssel => [Bezeichnung, Pflichtangabe für das Impressum] */
    public const FIELDS = [
        'imprint_name' => ['Name (Vor- und Nachname)', true],
        'imprint_street' => ['Straße und Hausnummer', true],
        'imprint_zip' => ['PLZ', true],
        'imprint_city' => ['Ort', true],
        'imprint_email' => ['E-Mail-Adresse', true],
        'imprint_phone' => ['Telefon', false],
        'imprint_vat_id' => ['Umsatzsteuer-ID (falls vorhanden)', false],
        'home_tagline' => ['Kurztext auf der Startseite', false],
    ];

    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            try {
                self::$cache = DB::table('settings')->pluck('value', 'key')->all();
            } catch (\Throwable) {
                self::$cache = [];
            }
        }

        return self::$cache;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = self::all()[$key] ?? null;

        return ($value === null || trim((string) $value) === '') ? $default : (string) $value;
    }

    /** @param array<string, ?string> $values */
    public static function putMany(array $values): void
    {
        foreach ($values as $key => $value) {
            DB::table('settings')->upsert(
                [['key' => $key, 'value' => $value, 'created_at' => now(), 'updated_at' => now()]],
                ['key'],
                ['value', 'updated_at']
            );
        }

        self::$cache = null;
    }

    public static function imprintComplete(): bool
    {
        foreach (self::FIELDS as $key => [$label, $required]) {
            if ($required && self::get($key) === null) {
                return false;
            }
        }

        return true;
    }

    public static function forgetCache(): void
    {
        self::$cache = null;
    }
}
