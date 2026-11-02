<?php

namespace App\Support\Dashboard;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/** Persönliche Einrichtung der Übersicht (Paket 19). */
class Layout
{
    /** Schlüssel => [Bezeichnung, Beschreibung, Breite (voll|halb), standardmäßig an, nur Admin] */
    public const BLOCKS = [
        'money' => ['Geld diesen Monat', 'Soll-Miete, bezahlt, offen, Rückstand', 'voll', true, false],
        'quick' => ['Schnellzugriff', 'Deine Knöpfe für häufige Arbeiten', 'voll', true, false],
        'todo' => ['Zu erledigen', 'Mahnungen, Kontoumsätze, Anfragen und mehr', 'halb', true, false],
        'dates' => ['Termine und Fristen', 'Was in den nächsten 60 Tagen ansteht', 'halb', true, false],
        'chart' => ['Mieteingang 12 Monate', 'Diagramm Soll und bezahlt', 'halb', true, false],
        'letting' => ['Vermietung', 'Quote, leere Einheiten, auslaufende Verträge', 'halb', true, false],
        'expenses' => ['Ausgaben dieses Jahr', 'Belege nach Kostenart und Monat', 'halb', true, false],
        'finance' => ['Finanzierung', 'Restschuld, Raten, Zinsbindung', 'halb', true, false],
        'technik' => ['Schäden und Wartung', 'Offene Aufträge und fällige Wartungen', 'halb', true, false],
        'note' => ['Merkzettel', 'Eigene Notizen, nur für dich sichtbar', 'halb', true, false],
        'checks' => ['Stammdaten zu ergänzen', 'Fehlende Angaben bei Eigentümern, Objekten, Mietern', 'voll', true, false],
        'stock' => ['Bestand', 'Objekte, Einheiten und Fläche je Eigentümer', 'voll', true, false],
        'recent' => ['Letzte Änderungen', 'Die letzten Einträge im Protokoll', 'voll', true, true],
    ];

    /** Schnellzugriff: Schlüssel => [Bezeichnung, Route, nur Admin, standardmäßig an] */
    public const QUICK = [
        'zahlung' => ['Zahlung erfassen', 'rent.month', false, true],
        'kontoauszug' => ['Kontoauszug einlesen', 'bank.index', false, true],
        'beleg' => ['Beleg erfassen', 'receipts.create', false, true],
        'mieter' => ['Neuer Mieter', 'tenants.create', false, true],
        'vertrag' => ['Neuer Mietvertrag', 'leases.create', false, true],
        'objekt' => ['Neues Objekt', 'properties.create', false, false],
        'eigentuemer' => ['Neuer Eigentümer', 'owners.create', true, false],
        'mahnwesen' => ['Mahnwesen', 'dunning.index', false, false],
        'offen' => ['Offene Posten', 'rent.open', false, false],
        'nebenkosten' => ['Nebenkosten', 'nk.index', false, false],
        'inserat' => ['Neues Inserat', 'listings.create', false, false],
        'anfragen' => ['Anfragen', 'inquiries.index', false, false],
        'lieferant' => ['Neuer Lieferant', 'vendors.create', false, false],
        'wiederkehrend' => ['Wiederkehrende Ausgaben', 'recurring.index', false, false],
        'darlehen' => ['Finanzierung', 'finance.index', false, false],
        'schaden' => ['Schaden erfassen', 'workorders.create', false, true],
        'anlagev' => ['Anlage V', 'anlage-v.index', false, false],
    ];

    public const NOTE_MAX = 5000;

    /** Farben für Bereiche (Paket 20): Schlüssel => [Bezeichnung, Farbe, heller Hintergrund] */
    public const COLORS = [
        'keine' => ['Ohne Farbe', '', ''],
        'bunt' => ['Bunt', '', ''],
        'blau' => ['Blau', '#2563eb', '#eff6ff'],
        'gruen' => ['Grün', '#059669', '#ecfdf5'],
        'tuerkis' => ['Türkis', '#0891b2', '#ecfeff'],
        'lila' => ['Lila', '#7c3aed', '#f5f3ff'],
        'rosa' => ['Rosa', '#db2777', '#fdf2f8'],
        'rot' => ['Rot', '#dc2626', '#fef2f2'],
        'orange' => ['Orange', '#ea580c', '#fff7ed'],
        'gelb' => ['Gelb', '#ca8a04', '#fefce8'],
        'grau' => ['Grau', '#475569', '#f1f5f9'],
    ];

    /** Standardfarbe je Bereich */
    public const DEFAULT_COLORS = [
        'money' => 'bunt', 'quick' => 'keine', 'todo' => 'orange', 'dates' => 'blau', 'chart' => 'gruen',
        'letting' => 'tuerkis', 'expenses' => 'lila', 'finance' => 'rosa', 'technik' => 'rot', 'note' => 'gelb', 'checks' => 'grau', 'stock' => 'grau', 'recent' => 'keine',
    ];

    /** Farben für die vier Geld-Kacheln bei „Bunt“ */
    public const MONEY_TILES = ['blau', 'gruen', 'orange', 'rot'];

    /** Welche Farben ein Bereich haben kann („Bunt“ nur bei den Geld-Kacheln). */
    public static function colorChoices(string $block): array
    {
        return array_filter(self::COLORS, fn ($c, $k) => $k !== 'bunt' || $block === 'money', ARRAY_FILTER_USE_BOTH);
    }

    private static function validColor(string $block, ?string $color): string
    {
        return $color !== null && isset(self::colorChoices($block)[$color]) ? $color : (self::DEFAULT_COLORS[$block] ?? 'keine');
    }

    /** CSS-Variablen für einen Bereich (leer bei „ohne Farbe“ und „bunt“). */
    public static function style(string $color): string
    {
        $c = self::COLORS[$color] ?? null;

        return $c && $c[1] !== '' ? '--tile:'.$c[1].';--tile-soft:'.$c[2].';--brand:'.$c[1] : '';
    }

    private static function stored(User $user): array
    {
        $raw = DB::table('users')->where('id', $user->id)->value('dashboard_layout');
        $data = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($data) ? $data : [];
    }

    private static function allowedBlock(string $key, User $user): bool
    {
        return isset(self::BLOCKS[$key]) && (! self::BLOCKS[$key][4] || $user->isAdmin());
    }

    private static function allowedQuick(string $key, User $user): bool
    {
        return isset(self::QUICK[$key]) && (! self::QUICK[$key][2] || $user->isAdmin()) && Route::has(self::QUICK[$key][1]);
    }

    /** Alle Bereiche in der Reihenfolge des Benutzers: [key, label, desc, wide, on]. Neue Bereiche kommen ans Ende. */
    public static function blocks(User $user): array
    {
        $stored = self::stored($user);
        $order = [];
        $on = [];
        $color = [];
        foreach ((array) ($stored['blocks'] ?? []) as $b) {
            if (is_array($b) && isset($b['key']) && self::allowedBlock((string) $b['key'], $user) && ! isset($on[$b['key']])) {
                $order[] = (string) $b['key'];
                $on[$b['key']] = (bool) ($b['on'] ?? true);
                $color[$b['key']] = self::validColor((string) $b['key'], isset($b['color']) ? (string) $b['color'] : null);
            }
        }
        foreach (self::BLOCKS as $key => $def) {
            if (self::allowedBlock($key, $user) && ! isset($on[$key])) {
                $order[] = $key;
                $on[$key] = $def[3];
            }
        }

        return array_map(fn ($k) => [
            'key' => $k, 'label' => self::BLOCKS[$k][0], 'desc' => self::BLOCKS[$k][1],
            'wide' => self::BLOCKS[$k][2] === 'voll', 'on' => $on[$k],
            'color' => $color[$k] ?? self::validColor($k, null),
            'style' => self::style($color[$k] ?? self::validColor($k, null)),
        ], $order);
    }

    /** Sichtbare Bereiche in Reihenfolge. */
    public static function visible(User $user): array
    {
        return array_values(array_filter(self::blocks($user), fn ($b) => $b['on']));
    }

    /** Gewählte Schnellzugriffe: [key, label, url]. */
    public static function quick(User $user): array
    {
        $stored = self::stored($user);
        $keys = isset($stored['quick']) && is_array($stored['quick'])
            ? $stored['quick']
            : array_keys(array_filter(self::QUICK, fn ($q) => $q[3]));
        $out = [];
        foreach (array_unique(array_map('strval', $keys)) as $k) {
            if (self::allowedQuick($k, $user)) {
                $out[] = ['key' => $k, 'label' => self::QUICK[$k][0], 'url' => route(self::QUICK[$k][1])];
            }
        }

        return $out;
    }

    /** Alle wählbaren Schnellzugriffe mit Status. */
    public static function quickChoices(User $user): array
    {
        $chosen = array_column(self::quick($user), 'key');
        $out = [];
        foreach (self::QUICK as $k => $q) {
            if (self::allowedQuick($k, $user)) {
                $out[] = ['key' => $k, 'label' => $q[0], 'on' => in_array($k, $chosen, true)];
            }
        }

        return $out;
    }

    /** @param array<int,string> $order @param array<int,string> $on @param array<int,string> $quick */
    public static function save(User $user, array $order, array $on, array $quick, array $colors = []): void
    {
        $blocks = [];
        $seen = [];
        foreach ($order as $k) {
            $k = (string) $k;
            if (self::allowedBlock($k, $user) && ! isset($seen[$k])) {
                $seen[$k] = true;
                $blocks[] = ['key' => $k, 'on' => in_array($k, $on, true), 'color' => self::validColor($k, isset($colors[$k]) ? (string) $colors[$k] : null)];
            }
        }
        $quick = array_values(array_filter(array_unique(array_map('strval', $quick)), fn ($k) => self::allowedQuick($k, $user)));
        DB::table('users')->where('id', $user->id)->update(['dashboard_layout' => json_encode(['blocks' => $blocks, 'quick' => $quick])]);
    }

    public static function reset(User $user): void
    {
        DB::table('users')->where('id', $user->id)->update(['dashboard_layout' => null]);
    }

    public static function note(User $user): string
    {
        return (string) DB::table('users')->where('id', $user->id)->value('dashboard_note');
    }

    public static function saveNote(User $user, ?string $text): void
    {
        $text = trim((string) $text);
        DB::table('users')->where('id', $user->id)->update(['dashboard_note' => $text === '' ? null : mb_substr($text, 0, self::NOTE_MAX)]);
    }
}
