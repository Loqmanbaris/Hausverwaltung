<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Kostenarten für Belege. Die Startliste unten wird beim Einspielen in die Datenbank übernommen
 * und kann danach vom Administrator unter „Kostenarten“ ergänzt und umbenannt werden.
 * Die endgültige Zuordnung zu Konten legen wir später gemeinsam mit dem Steuerberater fest.
 */
class Kostenarten
{
    public const GROUPS = [
        'Laufende Kosten (Betriebskosten)' => [
            'grundsteuer' => 'Grundsteuer',
            'wasser' => 'Wasser und Abwasser',
            'heizung' => 'Heizung und Brennstoff',
            'warmwasser' => 'Warmwasser',
            'muell' => 'Müllabfuhr',
            'strassenreinigung' => 'Straßenreinigung',
            'winterdienst' => 'Winterdienst',
            'versicherung' => 'Versicherungen (Gebäude, Haftpflicht)',
            'hausmeister' => 'Hausmeister',
            'gartenpflege' => 'Gartenpflege',
            'reinigung' => 'Gebäudereinigung',
            'allgemeinstrom' => 'Allgemeinstrom',
            'schornsteinfeger' => 'Schornsteinfeger',
            'aufzug' => 'Aufzug',
            'antenne' => 'Kabel- und Antennenanlage',
            'wartung' => 'Wartung (z. B. Heizung, Rauchmelder)',
            'sonst_betriebskosten' => 'Sonstige Betriebskosten',
        ],
        'Instandhaltung und Investition' => [
            'reparatur' => 'Reparatur und Instandhaltung',
            'modernisierung' => 'Modernisierung und Anschaffung',
        ],
        'Finanzierung und Verwaltung' => [
            'darlehen_zinsen' => 'Darlehenszinsen',
            'steuerberatung' => 'Steuerberatung und Buchhaltung',
            'bankgebuehren' => 'Bankgebühren',
            'sonstiges' => 'Sonstiges',
        ],
    ];

    private static ?array $cache = null;

    public static function forgetCache(): void
    {
        self::$cache = null;
    }

    /** @return array<int, array{key: string, label: string, group: string, active: bool, allocatable: bool}> */
    private static function rows(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $rows = [];
        try {
            foreach (DB::table('cost_types')->orderBy('sort')->orderBy('id')->get() as $r) {
                $rows[] = [
                    'key' => (string) $r->key,
                    'label' => (string) $r->label,
                    'group' => (string) $r->group_label,
                    'active' => (bool) $r->active,
                    'allocatable' => (bool) $r->allocatable,
                ];
            }
        } catch (Throwable) {
            $rows = [];
        }

        if ($rows === []) {
            $first = true;
            foreach (self::GROUPS as $group => $items) {
                foreach ($items as $key => $label) {
                    $rows[] = ['key' => $key, 'label' => $label, 'group' => $group, 'active' => true, 'allocatable' => $first];
                }
                $first = false;
            }
        }

        return self::$cache = $rows;
    }

    /** @return array<string, string> Schlüssel => Bezeichnung */
    public static function all(bool $activeOnly = true): array
    {
        $all = [];
        foreach (self::rows() as $r) {
            if (! $activeOnly || $r['active']) {
                $all[$r['key']] = $r['label'];
            }
        }

        return $all;
    }

    /**
     * Für Auswahllisten: Gruppe => [Schlüssel => Bezeichnung]. Eine stillgelegte Kostenart
     * erscheint nur, wenn sie gerade an dem Datensatz hängt ($include).
     *
     * @return array<string, array<string, string>>
     */
    public static function groups(?string $include = null): array
    {
        $groups = [];
        foreach (self::rows() as $r) {
            if ($r['active'] || ($include !== null && $r['key'] === $include)) {
                $groups[$r['group']][$r['key']] = $r['label'].($r['active'] ? '' : ' (stillgelegt)');
            }
        }

        return $groups;
    }

    /** Schlüssel, die eingegeben werden dürfen: alle aktiven und die bereits gespeicherte. */
    public static function allowed(?string $current = null): array
    {
        $keys = array_keys(self::all(true));
        if ($current !== null && $current !== '' && ! in_array($current, $keys, true)) {
            $keys[] = $current;
        }

        return $keys;
    }

    public static function label(?string $key): string
    {
        if ($key === null || $key === '') {
            return '–';
        }

        return self::all(false)[$key] ?? $key;
    }

    /** Namen aller Gruppen in der Reihenfolge der Liste. */
    public static function groupNames(): array
    {
        return array_values(array_unique(array_column(self::rows(), 'group')));
    }
}
