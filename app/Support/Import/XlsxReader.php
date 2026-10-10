<?php

namespace App\Support\Import;

use RuntimeException;

/**
 * Liest Excel-Dateien (.xlsx) ohne Zusatzbibliotheken: ZIP-Inhalt selbst entpacken, XML auswerten.
 * Liefert je Blatt die Zeilen als Arrays (Spaltenindex => Text). Formeln werden als zuletzt berechneter Wert gelesen.
 */
class XlsxReader
{
    public const MAX_BYTES = 5_000_000;

    public const MAX_ROWS = 5000;

    /** @return array<string, array<int, array<int, string>>> Blattname => Zeilennummer => [Spalte => Wert] */
    public static function read(string $raw): array
    {
        if (strlen($raw) > self::MAX_BYTES) {
            throw new RuntimeException('Die Datei ist zu groß (höchstens 5 MB).');
        }
        if (! str_starts_with($raw, "PK\x03\x04")) {
            throw new RuntimeException('Das ist keine Excel-Datei im Format .xlsx. Bitte in Excel „Speichern unter“ → „Excel-Arbeitsmappe (.xlsx)“ wählen.');
        }
        $files = self::unzip($raw);
        foreach (['xl/workbook.xml', 'xl/_rels/workbook.xml.rels'] as $need) {
            if (! isset($files[$need])) {
                throw new RuntimeException('Die Excel-Datei ist unvollständig oder beschädigt.');
            }
        }
        $shared = isset($files['xl/sharedStrings.xml']) ? self::sharedStrings($files['xl/sharedStrings.xml']) : [];

        $rels = [];
        $relXml = self::xml($files['xl/_rels/workbook.xml.rels']);
        foreach ($relXml->children() as $r) {
            $rels[(string) $r['Id']] = (string) $r['Target'];
        }
        $wb = self::xml($files['xl/workbook.xml']);
        $out = [];
        $ns = $wb->getNamespaces(true);
        foreach ($wb->sheets->sheet ?? [] as $sheet) {
            $name = (string) $sheet['name'];
            $rid = '';
            foreach ($ns as $prefix => $uri) {
                if (str_contains($uri, 'relationships')) {
                    $rid = (string) $sheet->attributes($uri)['id'];
                }
            }
            $target = $rels[$rid] ?? '';
            $path = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target;
            if (! isset($files[$path])) {
                continue;
            }
            $out[$name] = self::sheet($files[$path], $shared);
        }

        return $out;
    }

    /** @return array<string, string> */
    private static function unzip(string $raw): array
    {
        $eocd = strrpos($raw, "PK\x05\x06");
        if ($eocd === false) {
            throw new RuntimeException('Die Excel-Datei ist beschädigt.');
        }
        $e = unpack('vdisk/vcdisk/vnum/vtotal/Vsize/Voffset', substr($raw, $eocd + 4, 16));
        $pos = $e['offset'];
        $files = [];
        for ($i = 0; $i < $e['total']; $i++) {
            if (substr($raw, $pos, 4) !== "PK\x01\x02") {
                throw new RuntimeException('Die Excel-Datei ist beschädigt (Inhaltsverzeichnis).');
            }
            $h = unpack('vmade/vneed/vflags/vmethod/vtime/vdate/Vcrc/Vcsize/Vusize/vnlen/velen/vclen/vdisk/viattr/Veattr/Voffset', substr($raw, $pos + 4, 42));
            $name = substr($raw, $pos + 46, $h['nlen']);
            $pos += 46 + $h['nlen'] + $h['elen'] + $h['clen'];
            if (! preg_match('#^(xl/|\[Content_Types\])#', $name) || str_ends_with($name, '/')) {
                continue;
            }
            $l = unpack('vnlen/velen', substr($raw, $h['offset'] + 26, 4));
            $data = substr($raw, $h['offset'] + 30 + $l['nlen'] + $l['elen'], $h['csize']);
            if ($h['usize'] > 50_000_000) {
                throw new RuntimeException('Die Excel-Datei ist zu groß.');
            }
            $content = match ($h['method']) {
                0 => $data,
                8 => @gzinflate($data),
                default => false,
            };
            if ($content === false) {
                throw new RuntimeException('Die Excel-Datei konnte nicht entpackt werden.');
            }
            $files[$name] = $content;
        }

        return $files;
    }

    private static function xml(string $content): \SimpleXMLElement
    {
        $x = @simplexml_load_string($content, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        if ($x === false) {
            throw new RuntimeException('Die Excel-Datei enthält fehlerhafte Daten.');
        }

        return $x;
    }

    /** @return string[] */
    private static function sharedStrings(string $content): array
    {
        $x = self::xml($content);
        $out = [];
        foreach ($x->si as $si) {
            if (isset($si->t)) {
                $out[] = (string) $si->t;
            } else {
                $s = '';
                foreach ($si->r as $r) {
                    $s .= (string) $r->t;
                }
                $out[] = $s;
            }
        }

        return $out;
    }

    /** @return array<int, array<int, string>> */
    private static function sheet(string $content, array $shared): array
    {
        $x = self::xml($content);
        $rows = [];
        $count = 0;
        foreach ($x->sheetData->row ?? [] as $row) {
            $r = (int) $row['r'];
            $cells = [];
            $col = 0;
            foreach ($row->c as $c) {
                $ref = (string) $c['r'];
                $col = $ref !== '' ? self::colIndex($ref) : $col + 1;
                $t = (string) $c['t'];
                $v = match ($t) {
                    's' => $shared[(int) $c->v] ?? '',
                    'inlineStr' => isset($c->is->t) ? (string) $c->is->t : implode('', array_map(fn ($rr) => (string) $rr->t, iterator_to_array($c->is->r ?? [], false))),
                    'b' => ((string) $c->v) === '1' ? 'ja' : 'nein',
                    default => (string) $c->v,
                };
                $v = trim(str_replace("\r", '', $v));
                if ($v !== '') {
                    $cells[$col] = $v;
                }
            }
            if ($cells !== []) {
                $rows[$r] = $cells;
                if (++$count > self::MAX_ROWS) {
                    throw new RuntimeException('Zu viele Zeilen in einem Blatt (höchstens '.self::MAX_ROWS.').');
                }
            }
        }

        return $rows;
    }

    public static function colIndex(string $ref): int
    {
        preg_match('/^([A-Z]+)/', strtoupper($ref), $m);
        $n = 0;
        foreach (str_split($m[1] ?? 'A') as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n;
    }

    public static function colName(int $i): string
    {
        $s = '';
        while ($i > 0) {
            $m = ($i - 1) % 26;
            $s = chr(65 + $m).$s;
            $i = intdiv($i - $m - 1, 26);
        }

        return $s;
    }
}
