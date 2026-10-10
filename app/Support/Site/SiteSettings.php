<?php

namespace App\Support\Site;

use App\Support\Settings;

/** Texte und Farben der öffentlichen Webseite (Paket 16). Gespeichert in der Tabelle settings. */
class SiteSettings
{
    public const ACCENTS = [
        '#c2410c' => 'Terrakotta',
        '#0f766e' => 'Petrol',
        '#1d4ed8' => 'Blau',
        '#15803d' => 'Grün',
        '#b91c1c' => 'Rot',
        '#7c3aed' => 'Violett',
        '#111827' => 'Schwarz',
    ];

    /** Schlüssel => [Bezeichnung, Standardwert, Länge] */
    public const FIELDS = [
        'site_name' => ['Name der Webseite', '', 60],
        'site_headline' => ['Große Überschrift (eine Zeile je Wort oder Satz)', "Wohnen.\nArbeiten.\nAnkommen.", 120],
        'site_intro' => ['Text unter der Überschrift', 'Wohnungen, möblierte Monteurwohnungen und Gewerbeflächen – direkt vom Eigentümer.', 400],
        'site_accent' => ['Akzentfarbe', '#c2410c', 7],
        'site_services_intro' => ['Leistungen: Einleitung', 'Wir vermieten und verwalten unsere eigenen Häuser. Ohne Makler, mit kurzen Wegen.', 600],
        'site_services' => ['Leistungen (JSON)', '', 6000],
        'site_objects_intro' => ['Unsere Objekte: Einleitung', 'Eine Auswahl der Häuser, die wir vermieten und pflegen.', 600],
        'site_tenants_intro' => ['Für Mieter: Einleitung', 'Hier finden Sie alles Wichtige rund um Ihr Mietverhältnis.', 600],
        'site_damage_text' => ['Für Mieter: Schaden melden', 'Melden Sie Schäden bitte so schnell wie möglich, am besten mit genauer Beschreibung. Bei Gefahr (Wasserrohrbruch, Gasgeruch, Stromausfall im ganzen Haus) rufen Sie bitte sofort die Notfallnummern an.', 1200],
        'site_emergency' => ['Für Mieter: Notfallnummern (eine je Zeile: Bezeichnung: Nummer)', "Feuerwehr und Rettungsdienst: 112\nPolizei: 110", 2000],
    ];

    public static function defaultServices(): array
    {
        return [
            ['title' => 'Wohnungen', 'text' => 'Gepflegte Mietwohnungen in guter Lage. Freie Wohnungen finden Sie unter „Wohnungen“.'],
            ['title' => 'Monteurwohnungen', 'text' => 'Möblierte Wohnungen für Monteure und Firmen – tage-, wochen- oder monatsweise, mit Küche, WLAN und Parkplatz.'],
            ['title' => 'Gewerbeflächen', 'text' => 'Läden, Büros und Lagerflächen für kleine und mittlere Betriebe.'],
            ['title' => 'Persönliche Betreuung', 'text' => 'Ein fester Ansprechpartner für alle Anliegen – von der Besichtigung bis zur Nebenkostenabrechnung.'],
        ];
    }

    public static function get(string $key): string
    {
        $default = self::FIELDS[$key][1] ?? '';
        if ($key === 'site_name') {
            $default = (string) config('app.name');
        }

        return (string) (Settings::get($key) ?? $default);
    }

    public static function name(): string
    {
        return self::get('site_name') ?: (string) config('app.name');
    }

    public static function accent(): string
    {
        $c = self::get('site_accent');

        return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? strtolower($c) : '#c2410c';
    }

    /** @return string[] */
    public static function headlineLines(): array
    {
        return array_values(array_filter(array_map('trim', explode("\n", self::get('site_headline'))), fn ($l) => $l !== ''));
    }

    /** @return array<int, array{title: string, text: string}> */
    public static function services(): array
    {
        $raw = Settings::get('site_services');
        $list = $raw ? json_decode($raw, true) : null;

        return is_array($list) ? array_values(array_filter($list, fn ($s) => trim($s['title'] ?? '') !== '')) : self::defaultServices();
    }

    /** @return array<int, array{label: string, number: string}> */
    public static function emergency(): array
    {
        $out = [];
        foreach (explode("\n", self::get('site_emergency')) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $pos = mb_strrpos($line, ':');
            $out[] = $pos === false ? ['label' => $line, 'number' => ''] : ['label' => trim(mb_substr($line, 0, $pos)), 'number' => trim(mb_substr($line, $pos + 1))];
        }

        return $out;
    }

    /** Dunklere Variante der Akzentfarbe für Hover. */
    public static function darken(string $hex, float $f = 0.82): string
    {
        $h = ltrim($hex, '#');

        return sprintf('#%02x%02x%02x', (int) (hexdec(substr($h, 0, 2)) * $f), (int) (hexdec(substr($h, 2, 2)) * $f), (int) (hexdec(substr($h, 4, 2)) * $f));
    }
}
