<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Schreibt Einträge in das Protokoll (Tabelle audit_logs).
 * Sensible Felder werden nie im Klartext protokolliert.
 *
 * Beide Schreibweisen funktionieren:
 *   Audit::log('owner.created', $owner, 'Eigentümer angelegt', null, $daten);
 *   Audit::log('login.failed', 'Falsches Passwort', $user, label: $email);
 */
class Audit
{
    private const HIDDEN = ['iban', 'tax_number', 'password', 'two_factor_secret'];

    public static function log(
        string $event,
        mixed $second = null,
        mixed $third = null,
        ?array $old = null,
        ?array $new = null,
        ?string $label = null,
        ?User $actor = null
    ): void {
        [$subject, $description] = self::arguments($second, $third);

        $user = $actor ?? auth()->user();

        DB::table('audit_logs')->insert([
            'user_id' => $user?->id,
            'user_label' => $label ?? $user?->name,
            'event' => $event,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'old_values' => $old === null ? null : json_encode(self::mask($old), JSON_UNESCAPED_UNICODE),
            'new_values' => $new === null ? null : json_encode(self::mask($new), JSON_UNESCAPED_UNICODE),
            'ip_address' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }

    /**
     * Erkennt die Schreibweise: (Datensatz, Beschreibung) oder (Beschreibung, Datensatz).
     *
     * @return array{0: ?Model, 1: string}
     */
    public static function arguments(mixed $second, mixed $third): array
    {
        if (is_string($second)) {
            return [$third instanceof Model ? $third : null, $second];
        }

        return [$second instanceof Model ? $second : null, is_string($third) ? $third : ''];
    }

    /** Liefert [alteWerte, neueWerte] nur für geänderte Felder, sonst [null, null]. */
    public static function diff(array $before, array $after): array
    {
        $old = [];
        $new = [];

        foreach ($after as $key => $value) {
            $was = $before[$key] ?? null;
            if ((string) $was !== (string) $value) {
                $old[$key] = $was;
                $new[$key] = $value;
            }
        }

        return [$old ?: null, $new ?: null];
    }

    private static function mask(array $values): array
    {
        foreach (self::HIDDEN as $key) {
            if (array_key_exists($key, $values) && $values[$key] !== null && $values[$key] !== '') {
                $values[$key] = '[verborgen]';
            }
        }

        return $values;
    }
}
