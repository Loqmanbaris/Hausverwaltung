<?php

namespace App\Support\Contracts;

/** Setzt die Werte in die Vorlage ein und nummeriert Paragrafen und Absätze. Reine Textverarbeitung ohne Datenbank. */
class ContractRenderer
{
    public const BLANK = '________________';

    /**
     * @param  array<int, array{key: string, title: string, text: string, enabled?: bool}>  $sections
     * @param  array<string, string>  $values
     * @return array<int, array{no: int, title: string, paragraphs: string[]}>
     */
    public static function render(array $sections, array $values): array
    {
        $out = [];
        $no = 0;
        foreach ($sections as $s) {
            if (array_key_exists('enabled', $s) && ! $s['enabled']) {
                continue;
            }
            $text = self::fill((string) $s['text'], $values);
            $paragraphs = self::paragraphs($text);
            if ($paragraphs === []) {
                continue;
            }
            $out[] = ['no' => ++$no, 'title' => trim((string) $s['title']), 'paragraphs' => $paragraphs];
        }

        return $out;
    }

    /** Bedingungen auswerten und Platzhalter ersetzen. */
    public static function fill(string $text, array $values): string
    {
        $text = str_replace("\r", '', $text);
        // Bedingungen von innen nach außen auflösen
        $pattern = '/\[\[wenn (nicht )?([a-z_]+)\]\]((?:(?!\[\[wenn ).)*?)\[\[\/wenn\]\]/s';
        for ($i = 0; $i < 20 && preg_match($pattern, $text); $i++) {
            $text = preg_replace_callback($pattern, function ($m) use ($values) {
                $has = trim((string) ($values[$m[2]] ?? '')) !== '';

                return ($m[1] !== '' ? ! $has : $has) ? $m[3] : '';
            }, $text);
        }
        // Übrig gebliebene, nicht geschlossene Bedingungen entfernen
        $text = preg_replace('/\[\[\/?wenn[^\]]*\]\]/', '', $text);

        return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', function ($m) use ($values) {
            $v = trim((string) ($values[$m[1]] ?? ''));

            return $v === '' ? self::BLANK : $v;
        }, $text);
    }

    /** Absätze trennen, leere entfernen, (#) durchnummerieren (bei nur einem Absatz ohne Nummer). */
    public static function paragraphs(string $text): array
    {
        $parts = preg_split("/\n\s*\n/", $text);
        $paras = [];
        foreach ($parts as $p) {
            $p = trim(preg_replace("/[ \t]+\n/", "\n", $p));
            if ($p !== '' && $p !== '(#)') {
                $paras[] = $p;
            }
        }
        $count = count(array_filter($paras, fn ($p) => str_starts_with($p, '(#)')));
        $n = 0;
        foreach ($paras as &$p) {
            if (str_starts_with($p, '(#)')) {
                $p = $count > 1 ? '('.(++$n).')'.substr($p, 3) : ltrim(substr($p, 3));
            }
        }
        unset($p);

        return $paras;
    }

    /** Prüfsumme über den Inhalt (Vertragstext und Vertragsparteien). */
    public static function hash(array $rendered, array $header): string
    {
        return hash('sha256', json_encode(['h' => $header, 's' => $rendered], JSON_UNESCAPED_UNICODE));
    }
}
