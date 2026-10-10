<?php

namespace App\Support\Pdf;

/**
 * Kleiner PDF-Schreiber (Paket 21): A4-Seiten, Helvetica/Helvetica-Bold (WinAnsi), Linien,
 * Flächen und JPEG-Bilder. Koordinaten in Punkt, Ursprung oben links (wird intern umgerechnet).
 */
class PdfWriter
{
    public const W = 595.28;

    public const H = 841.89;

    /** @var array<int, string> Inhalt je Seite */
    private array $pages = [];

    private int $current = -1;

    /** @var array<string, array{data:string,w:int,h:int}> */
    private array $images = [];

    public function __construct(private string $title = '')
    {
    }

    public function addPage(): void
    {
        $this->pages[] = '';
        $this->current = count($this->pages) - 1;
    }

    public function pageCount(): int
    {
        return count($this->pages);
    }

    public function setPage(int $i): void
    {
        $this->current = $i;
    }

    /** UTF-8 → Windows-1252, nicht darstellbare Zeichen werden ersetzt. */
    public static function encode(string $s): string
    {
        $s = strtr($s, ['−' => '-', '‐' => '-', '‑' => '-', ' ' => ' ', ' ' => ' ', '✓' => '', '✕' => 'x', '↗' => '', '→' => '->', '←' => '<-', '…' => '...', '≈' => '~', '²' => '²']);
        $out = @mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
        if ($out === false || $out === '') {
            $out = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s) ?: '';
        }

        return $out;
    }

    /** Breite eines (bereits kodierten) Textes in Punkt. */
    public static function width(string $encoded, float $size, bool $bold = false): float
    {
        $t = $bold ? FontMetrics::BOLD : FontMetrics::REGULAR;
        $w = 0;
        $n = strlen($encoded);
        for ($i = 0; $i < $n; $i++) {
            $w += $t[ord($encoded[$i])];
        }

        return $w * $size / 1000;
    }

    private static function color(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (! preg_match('/^[0-9a-f]{6}$/i', $hex)) {
            $hex = '111111';
        }

        return sprintf('%.3F %.3F %.3F', hexdec(substr($hex, 0, 2)) / 255, hexdec(substr($hex, 2, 2)) / 255, hexdec(substr($hex, 4, 2)) / 255);
    }

    private function out(string $s): void
    {
        $this->pages[$this->current] .= $s."\n";
    }

    /** Text (bereits kodiert) mit Grundlinie bei $y. */
    public function text(float $x, float $y, string $encoded, float $size, bool $bold = false, string $color = '#111111'): void
    {
        if ($encoded === '') {
            return;
        }
        $esc = strtr($encoded, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '', "\n" => '']);
        $this->out(sprintf('BT %s rg /%s %.2F Tf %.2F %.2F Td (%s) Tj ET', self::color($color), $bold ? 'F2' : 'F1', $size, $x, self::H - $y, $esc));
    }

    public function line(float $x1, float $y1, float $x2, float $y2, float $width = 0.5, string $color = '#999999'): void
    {
        $this->out(sprintf('q %s RG %.2F w %.2F %.2F m %.2F %.2F l S Q', self::color($color), $width, $x1, self::H - $y1, $x2, self::H - $y2));
    }

    public function rect(float $x, float $y, float $w, float $h, ?string $fill = null, ?string $stroke = null, float $lw = 0.6): void
    {
        $op = $fill && $stroke ? 'B' : ($fill ? 'f' : 'S');
        $this->out(sprintf('q %s%s%.2F w %.2F %.2F %.2F %.2F re %s Q', $fill ? self::color($fill).' rg ' : '', $stroke ? self::color($stroke).' RG ' : '', $lw, $x, self::H - $y - $h, $w, $h, $op));
    }

    /** JPEG-Bild einbetten; $key dient dem Wiederverwenden. */
    public function image(string $key, string $jpeg, int $pw, int $ph, float $x, float $y, float $w, float $h): void
    {
        if (! isset($this->images[$key])) {
            $this->images[$key] = ['data' => $jpeg, 'w' => $pw, 'h' => $ph, 'n' => count($this->images) + 1];
        }
        $this->out(sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /Im%d Do Q', $w, $h, $x, self::H - $y - $h, $this->images[$key]['n']));
    }

    public function output(): string
    {
        $objs = [];
        $add = function (string $body) use (&$objs): int {
            $objs[] = $body;

            return count($objs);
        };
        $catalog = $add('');
        $pagesId = $add('');
        $f1 = $add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
        $f2 = $add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>');
        $imgRefs = '';
        foreach ($this->images as $img) {
            $id = $add(sprintf("<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n", $img['w'], $img['h'], strlen($img['data'])).$img['data']."\nendstream");
            $imgRefs .= sprintf(' /Im%d %d 0 R', $img['n'], $id);
        }
        $res = sprintf('<< /Font << /F1 %d 0 R /F2 %d 0 R >>%s >>', $f1, $f2, $imgRefs !== '' ? ' /XObject <<'.$imgRefs.' >>' : '');
        $kids = [];
        foreach ($this->pages as $content) {
            $z = function_exists('gzcompress') ? gzcompress($content, 6) : false;
            $stream = $z !== false
                ? sprintf("<< /Length %d /Filter /FlateDecode >>\nstream\n", strlen($z)).$z."\nendstream"
                : sprintf("<< /Length %d >>\nstream\n", strlen($content)).$content."\nendstream";
            $cid = $add($stream);
            $kids[] = $add(sprintf('<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %.2F %.2F] /Resources %s /Contents %d 0 R >>', $pagesId, self::W, self::H, $res, $cid));
        }
        $objs[$catalog - 1] = sprintf('<< /Type /Catalog /Pages %d 0 R >>', $pagesId);
        $objs[$pagesId - 1] = sprintf('<< /Type /Pages /Kids [%s] /Count %d >>', implode(' ', array_map(fn ($k) => $k.' 0 R', $kids)), count($kids));
        $title = self::encode($this->title);
        $info = $add(sprintf('<< /Title (%s) /Producer (CRM) /CreationDate (D:%s) >>', strtr($title, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']), date('YmdHis')));

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objs as $i => $body) {
            $offsets[$i + 1] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n".$body."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objs) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $o) {
            $pdf .= sprintf("%010d 00000 n \n", $o);
        }
        $pdf .= sprintf("trailer\n<< /Size %d /Root %d 0 R /Info %d 0 R >>\nstartxref\n%d\n%%%%EOF\n", count($objs) + 1, $catalog, $info, $xref);

        return $pdf;
    }
}
