<?php

namespace App\Support\EInvoice;

/**
 * Holt die eingebettete XML-Datei aus einer ZUGFeRD- oder Factur-X-PDF.
 * Die PDF wird nur gelesen, nicht ausgeführt. Es werden die Datenströme der PDF durchsucht und
 * der erste gefunden, der eine E-Rechnung enthält (CII oder UBL).
 */
class PdfXmlExtractor
{
    /** Größtes entpacktes XML, das wir zulassen. */
    private const MAX_XML = 8 * 1024 * 1024;

    /** Mehr Datenströme als das prüfen wir nicht (Schutz vor Bastel-PDFs). */
    private const MAX_STREAMS = 4000;

    public static function extract(string $pdf): ?string
    {
        if ($pdf === '' || ! preg_match_all('/stream\r?\n/', $pdf, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $streams = [];
        foreach ($matches[0] as [$token, $offset]) {
            if (count($streams) >= self::MAX_STREAMS) {
                break;
            }
            // „endstream“ enthält selbst „stream“: nur echte Anfänge nehmen
            if ($offset >= 3 && substr($pdf, $offset - 3, 3) === 'end') {
                continue;
            }
            $start = $offset + strlen($token);
            $end = strpos($pdf, 'endstream', $start);
            if ($end === false) {
                continue;
            }

            $back = substr($pdf, max(0, $offset - 3000), min(3000, $offset));
            $objAt = strrpos($back, 'obj');
            $dict = $objAt === false ? $back : substr($back, $objAt);

            $streams[] = [$dict, $start, $end - $start];
        }

        // Erst die Datenströme, die sich ausdrücklich als eingebettete Datei bezeichnen
        foreach ([true, false] as $strict) {
            foreach ($streams as [$dict, $start, $length]) {
                $declared = stripos($dict, 'EmbeddedFile') !== false;
                if ($strict !== $declared) {
                    continue;
                }
                if (! $strict && (self::isBulky($dict) || $length > 3 * 1024 * 1024)) {
                    continue;
                }

                $xml = self::decode(substr($pdf, $start, $length));
                if ($xml !== null && self::looksLikeInvoice($xml)) {
                    return $xml;
                }
            }
        }

        return null;
    }

    /** Bilder, Schriften, Seitenbeschreibungen und Verzeichnisse enthalten keine Rechnung. */
    private static function isBulky(string $dict): bool
    {
        foreach (['/Image', '/ObjStm', '/XRef', '/FontFile', '/Length1', '/Type/Page', '/Type /Page', '/CIDSet', '/ICCBased', '/N 3', '/N 4', '/Alternate'] as $marker) {
            if (str_contains($dict, $marker)) {
                return true;
            }
        }

        return false;
    }

    private static function decode(string $raw): ?string
    {
        $raw = rtrim($raw, "\r\n");
        if ($raw === '') {
            return null;
        }

        if (str_starts_with($raw, "\x78")) {
            $out = @gzuncompress($raw, self::MAX_XML);
            if ($out === false && strlen($raw) > 2) {
                $out = @gzinflate(substr($raw, 2), self::MAX_XML);
            }

            return is_string($out) && $out !== '' ? $out : null;
        }

        return $raw;
    }

    private static function looksLikeInvoice(string $xml): bool
    {
        $head = ltrim(substr($xml, 0, 6000), "\xEF\xBB\xBF \t\r\n");
        if ($head === '' || $head[0] !== '<') {
            return false;
        }

        return preg_match('/<(?:[A-Za-z0-9_.-]+:)?(?:CrossIndustryInvoice|Invoice|CreditNote)[\s>]/', $head) === 1;
    }
}
