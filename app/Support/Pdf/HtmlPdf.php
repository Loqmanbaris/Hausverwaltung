<?php

namespace App\Support\Pdf;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Wandelt die Druckansichten des CRM (Mahnung, Nebenkostenabrechnung, Mietvertrag, Übergabeprotokoll,
 * Ratenvereinbarung) in ein PDF um (Paket 21). Kein vollständiger HTML-Renderer: unterstützt werden die
 * Elemente und Klassen, die diese Ansichten benutzen. Jedes Element mit der Klasse „page“ beginnt eine neue Seite.
 */
class HtmlPdf
{
    private const MM = 2.8346;

    private const TOP = 18 * self::MM;

    private const BOTTOM = 18 * self::MM;

    private const LEFT = 22 * self::MM;

    private const RIGHT = 18 * self::MM;

    private const LH = 1.38;

    private PdfWriter $pdf;

    private float $contentW;

    private float $contentH;

    private int $imgCounter = 0;

    /** Klassen-Regeln: Stil (vererbt) und Block-Eigenschaften */
    private const CLASSES = [
        'sender' => ['size' => 7.5, 'color' => '#333333', 'underline' => true, 'mb' => 4],
        'addr' => ['mt' => 12, 'minh' => 85],
        'meta' => ['align' => 'right', 'mt' => 4, 'mb' => 16],
        'small' => ['size' => 8.5, 'color' => '#333333'],
        'storno' => ['bold' => true, 'color' => '#b42318', 'box' => '#b42318', 'mb' => 10],
        'box' => ['box' => '#999999', 'mt' => 10],
        'n' => ['align' => 'right'],
        'ct-title' => ['size' => 15, 'bold' => true, 'align' => 'center', 'mb' => 10],
        'ct-between' => ['align' => 'center', 'mb' => 4],
        'ct-party' => ['align' => 'center', 'mb' => 6],
        'ct-role' => ['color' => '#555555', 'size' => 9.5],
        'ct-signs' => ['mt' => 18, 'keep' => true],
        'hb-signs' => ['mt' => 18, 'keep' => true],
        'ct-sign-row' => ['row' => true, 'mt' => 14],
        'hb-sign-row' => ['row' => true, 'mt' => 14],
        'signs' => ['row' => true, 'mt' => 60, 'childTop' => true, 'size' => 9],
        'ct-sign' => ['minh' => 55, 'bottom' => true],
        'hb-sign' => ['minh' => 50, 'bottom' => true],
        'ct-line' => ['top' => true, 'size' => 9],
        'hb-line' => ['top' => true, 'size' => 9],
        'ct-foot' => ['size' => 7.5, 'color' => '#666666', 'mt' => 14],
        'hb-foot' => ['size' => 7.5, 'color' => '#666666', 'mt' => 14],
        'hb-title' => ['size' => 14, 'bold' => true, 'mb' => 8],
        'hb-note' => ['pre' => true, 'mb' => 4],
        'hb-photos' => ['photos' => true, 'mt' => 4, 'mb' => 4],
        'hb-room' => ['keep' => true],
        'hb-t' => ['size' => 9],
        'st-gut' => ['color' => '#067647'],
        'st-gebrauch' => ['color' => '#93370d'],
        'st-mangel' => ['color' => '#b42318', 'bold' => true],
        'st-na' => ['color' => '#667085'],
    ];

    private const SKIP_CLASSES = ['toolbar', 'draft', 'no-pdf'];

    public static function render(string $html, string $title = ''): string
    {
        return (new self($title))->run($html);
    }

    private function __construct(string $title)
    {
        $this->pdf = new PdfWriter($title);
        $this->contentW = PdfWriter::W - self::LEFT - self::RIGHT;
        $this->contentH = PdfWriter::H - self::TOP - self::BOTTOM;
    }

    private function run(string $html): string
    {
        $doc = new DOMDocument;
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $body = $doc->getElementsByTagName('body')->item(0) ?? $doc->documentElement;
        $pages = [];
        $this->findPages($body, $pages);
        if ($pages === []) {
            $pages = [$body];
        }
        $base = ['size' => 10, 'bold' => false, 'color' => '#111111', 'align' => 'left', 'pre' => false];
        foreach ($pages as $page) {
            $frags = $this->blockChildren($page, $base, $this->contentW);
            $this->paginate($frags);
        }
        if ($this->pdf->pageCount() === 0) {
            $this->pdf->addPage();
        }

        return $this->pdf->output();
    }

    private function findPages(DOMNode $node, array &$pages): void
    {
        foreach ($node->childNodes as $c) {
            if (! $c instanceof DOMElement || $this->skip($c)) {
                continue;
            }
            if (in_array('page', $this->classes($c), true)) {
                $pages[] = $c;
            } else {
                $this->findPages($c, $pages);
            }
        }
    }

    // ---------------------------------------------------------------- Hilfen

    private function classes(DOMElement $el): array
    {
        return preg_split('/\s+/', trim($el->getAttribute('class'))) ?: [];
    }

    private function skip(DOMElement $el): bool
    {
        $tag = strtolower($el->tagName);
        if (in_array($tag, ['style', 'script', 'head', 'title', 'meta', 'button', 'noscript', 'template'], true)) {
            return true;
        }

        return array_intersect(self::SKIP_CLASSES, $this->classes($el)) !== [];
    }

    /** Stil und Block-Eigenschaften für ein Element. */
    private function style(DOMElement $el, array $parent): array
    {
        $s = $parent;
        $b = [];
        $tag = strtolower($el->tagName);
        $tagRules = [
            'h1' => ['size' => 13, 'bold' => true, 'mb' => 10],
            'h2' => ['size' => 11, 'bold' => true, 'mt' => 10, 'mb' => 4, 'keepNext' => true],
            'h3' => ['size' => 10.5, 'bold' => true, 'mt' => 8, 'mb' => 3, 'keepNext' => true],
            'p' => ['mb' => 6],
            'table' => ['mt' => 5, 'mb' => 6],
            'ul' => ['mb' => 6], 'ol' => ['mb' => 6],
            'strong' => ['bold' => true], 'b' => ['bold' => true],
            'th' => ['bold' => true, 'size' => 8.5, 'color' => '#444444'],
            'small' => ['size' => 8.5],
        ];
        $rules = [$tagRules[$tag] ?? []];
        foreach ($this->classes($el) as $c) {
            if (isset(self::CLASSES[$c])) {
                $rules[] = self::CLASSES[$c];
            }
        }
        // Überschriften in Vertragsabschnitten etwas kleiner
        if ($tag === 'h2' && $el->parentNode instanceof DOMElement && in_array('ct-sec', $this->classes($el->parentNode), true)) {
            $rules[] = ['size' => 10.5, 'mt' => 9, 'mb' => 2];
        }
        $css = strtolower($el->getAttribute('style'));
        if ($css !== '') {
            if (str_contains($css, 'pre-line') || str_contains($css, 'pre-wrap')) {
                $rules[] = ['pre' => true];
            }
            if (preg_match('/text-align:\s*(left|right|center)/', $css, $m)) {
                $rules[] = ['align' => $m[1]];
            }
            if (preg_match('/font-weight:\s*(700|bold|600)/', $css)) {
                $rules[] = ['bold' => true];
            }
            if (preg_match('/margin-top:\s*([\d.]+)(mm|pt|rem|px)/', $css, $m)) {
                $rules[] = ['mt' => $this->unit((float) $m[1], $m[2])];
            }
        }
        foreach ($rules as $r) {
            foreach ($r as $k => $v) {
                if (in_array($k, ['size', 'bold', 'color', 'align', 'pre'], true)) {
                    $s[$k] = $v;
                } else {
                    $b[$k] = $v;
                }
            }
        }

        return [$s, $b];
    }

    private function unit(float $v, string $u): float
    {
        return match ($u) {
            'mm' => $v * self::MM,
            'rem' => $v * 10,
            'px' => $v * 0.75,
            default => $v,
        };
    }

    private function isBlock(DOMNode $n): bool
    {
        return $n instanceof DOMElement && in_array(strtolower($n->tagName), ['div', 'p', 'h1', 'h2', 'h3', 'h4', 'table', 'ul', 'ol', 'li', 'section', 'header', 'footer', 'hr', 'article', 'blockquote'], true);
    }

    // ---------------------------------------------------------------- Blöcke

    /**
     * Fragmente: ['h' => Höhe, 'draw' => fn(PdfWriter, x, y), 'space' => bool, 'keepNext' => bool, 'header' => ?Fragment]
     */
    private function blockChildren(DOMNode $node, array $style, float $width): array
    {
        $frags = [];
        $inline = [];
        $flush = function () use (&$inline, &$frags, $style, $width) {
            if ($inline !== []) {
                array_push($frags, ...$this->paragraph($inline, $style, $width));
                $inline = [];
            }
        };
        foreach ($node->childNodes as $c) {
            if ($c instanceof DOMElement && $this->skip($c)) {
                continue;
            }
            if ($this->isBlock($c) || ($c instanceof DOMElement && strtolower($c->tagName) === 'img' && $this->blockImageContext($node))) {
                $flush();
                array_push($frags, ...$this->block($c, $style, $width));
            } else {
                $inline[] = $c;
            }
        }
        $flush();

        return $frags;
    }

    private function blockImageContext(DOMNode $parent): bool
    {
        return $parent instanceof DOMElement && array_intersect(['ct-sign', 'hb-sign'], $this->classes($parent)) !== [];
    }

    private function block(DOMElement $el, array $parentStyle, float $width): array
    {
        $tag = strtolower($el->tagName);
        [$s, $b] = $this->style($el, $parentStyle);

        if ($tag === 'hr') {
            return [$this->spacer(4), ['h' => 6, 'draw' => fn (PdfWriter $p, $x, $y) => $p->line($x, $y + 3, $x + $width, $y + 3, 0.5, '#999999')]];
        }
        if ($tag === 'img') {
            return $this->imageBlock($el, $width, 55);
        }

        if ($tag === 'table') {
            $inner = $this->table($el, $s, $width, $b);
        } elseif ($tag === 'ul' || $tag === 'ol') {
            $inner = $this->listBlock($el, $s, $width, $tag === 'ol');
        } elseif (! empty($b['row'])) {
            $inner = [$this->row($el, $s, $width, $b)];
        } elseif (! empty($b['photos'])) {
            $inner = $this->photos($el, $width);
        } elseif (! empty($b['box'])) {
            $pad = 7;
            $kids = $this->blockChildren($el, $s, $width - 2 * $pad);
            $h = $this->sum($kids) + 2 * $pad;
            $color = $b['box'];
            $boxW = in_array('storno', $this->classes($el), true) ? min($width, $this->naturalWidth($el, $s) + 2 * $pad + 2) : $width;
            $inner = [['h' => $h, 'draw' => function (PdfWriter $p, $x, $y) use ($kids, $pad, $boxW, $h, $color) {
                $p->rect($x, $y, $boxW, $h, null, $color, 0.8);
                $this->drawStack($p, $kids, $x + $pad, $y + $pad);
            }]];
        } else {
            $inner = $this->blockChildren($el, $s, $width);
        }

        if (! empty($b['top'])) {
            $kids = $inner;
            $h = $this->sum($kids) + 3;
            $inner = [['h' => $h, 'draw' => function (PdfWriter $p, $x, $y) use ($kids, $width) {
                $p->line($x, $y, $x + $width, $y, 0.6, '#333333');
                $this->drawStack($p, $kids, $x, $y + 3);
            }]];
        }
        if (! empty($b['minh']) && empty($b['bottom'])) {
            $missing = $b['minh'] - $this->sum($inner);
            if ($missing > 0) {
                $inner[] = $this->spacer($missing, false);
            }
        }
        if (! empty($b['keep'])) {
            $h = $this->sum($inner);
            if ($h <= $this->contentH * 0.95) {
                $kids = $inner;
                $inner = [['h' => $h, 'draw' => fn (PdfWriter $p, $x, $y) => $this->drawStack($p, $kids, $x, $y)]];
            }
        }
        if (! empty($b['keepNext']) && $inner !== []) {
            $inner[count($inner) - 1]['keepNext'] = true;
        }

        $out = [];
        if (! empty($b['mt'])) {
            $out[] = $this->spacer($b['mt']);
        }
        array_push($out, ...$inner);
        if (! empty($b['mb'])) {
            $out[] = $this->spacer($b['mb']);
        }

        return $out;
    }

    private function spacer(float $h, bool $droppable = true): array
    {
        return ['h' => $h, 'draw' => null, 'space' => $droppable];
    }

    private function sum(array $frags): float
    {
        return array_sum(array_column($frags, 'h'));
    }

    private function drawStack(PdfWriter $p, array $frags, float $x, float $y): void
    {
        foreach ($frags as $f) {
            if ($f['draw']) {
                ($f['draw'])($p, $x, $y);
            }
            $y += $f['h'];
        }
    }

    // ---------------------------------------------------------------- Text

    /** Inline-Inhalt in Wort-Stücke zerlegen: [text, size, bold, color] oder "\n" für Umbruch. */
    private function runs(array $nodes, array $style): array
    {
        $out = [];
        foreach ($nodes as $n) {
            if ($n instanceof DOMText) {
                $t = $n->nodeValue ?? '';
                if ($style['pre']) {
                    $lines = preg_split('/\r?\n/', $t);
                    foreach ($lines as $i => $line) {
                        if ($i > 0) {
                            $out[] = "\n";
                        }
                        $line = preg_replace('/[ \t]+/', ' ', $line);
                        if ($line !== '') {
                            $out[] = [$line, $style['size'], $style['bold'], $style['color']];
                        }
                    }
                } else {
                    $t = preg_replace('/\s+/u', ' ', $t);
                    if ($t !== '') {
                        $out[] = [$t, $style['size'], $style['bold'], $style['color']];
                    }
                }
            } elseif ($n instanceof DOMElement) {
                if ($this->skip($n)) {
                    continue;
                }
                $tag = strtolower($n->tagName);
                if ($tag === 'br') {
                    $out[] = "\n";

                    continue;
                }
                if ($tag === 'img') {
                    continue;
                }
                [$s] = $this->style($n, $style);
                array_push($out, ...$this->runs(iterator_to_array($n->childNodes), $s));
            }
        }

        return $out;
    }

    /** Runs in Wörter zerlegen und auf Zeilen verteilen. @return array<int, array{w: float, size: float, words: array}> */
    private function wrap(array $runs, float $width): array
    {
        $lines = [];
        $cur = [];
        $curW = 0.0;
        $maxSize = 0.0;
        $pendingSpace = false;
        $push = function () use (&$lines, &$cur, &$curW, &$maxSize) {
            $lines[] = ['w' => $curW, 'size' => $maxSize, 'words' => $cur];
            $cur = [];
            $curW = 0.0;
            $maxSize = 0.0;
        };
        $hadText = false;
        foreach ($runs as $r) {
            if ($r === "\n") {
                if ($cur === [] && $maxSize === 0.0) {
                    $maxSize = 10;
                }
                $push();
                $pendingSpace = false;

                continue;
            }
            [$text, $size, $bold, $color] = $r;
            $parts = preg_split('/( )/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
            foreach ($parts as $part) {
                if ($part === ' ') {
                    $pendingSpace = $cur !== [];

                    continue;
                }
                if ($part === '') {
                    continue;
                }
                $enc = PdfWriter::encode($part);
                $w = PdfWriter::width($enc, $size, $bold);
                $space = $pendingSpace ? PdfWriter::width(' ', $size, $bold) : 0.0;
                if ($cur !== [] && $curW + $space + $w > $width) {
                    $push();
                    $space = 0.0;
                }
                // Überlanges Wort zeichenweise trennen
                while ($w > $width && strlen($enc) > 1) {
                    $cut = strlen($enc);
                    while ($cut > 1 && PdfWriter::width(substr($enc, 0, $cut), $size, $bold) > $width - $curW) {
                        $cut--;
                    }
                    $piece = substr($enc, 0, $cut);
                    $cur[] = ['t' => $piece, 'x' => $curW, 'size' => $size, 'bold' => $bold, 'color' => $color, 'w' => PdfWriter::width($piece, $size, $bold)];
                    $curW += PdfWriter::width($piece, $size, $bold);
                    $maxSize = max($maxSize, $size);
                    $push();
                    $enc = substr($enc, $cut);
                    $w = PdfWriter::width($enc, $size, $bold);
                }
                $cur[] = ['t' => $enc, 'x' => $curW + $space, 'size' => $size, 'bold' => $bold, 'color' => $color, 'w' => $w];
                $curW += $space + $w;
                $maxSize = max($maxSize, $size);
                $pendingSpace = false;
                $hadText = true;
            }
            // Leerzeichen am Ende eines Runs merken
            if (str_ends_with($text, ' ') && $cur !== []) {
                $pendingSpace = true;
            }
        }
        if ($cur !== []) {
            $push();
        }
        // Abschließende leere Zeile durch <br> am Ende entfernen
        while ($lines !== [] && end($lines)['words'] === [] && count($lines) > 1 && ! $hadText) {
            array_pop($lines);
        }

        return $lines;
    }

    private function paragraph(array $nodes, array $style, float $width, array $b = []): array
    {
        $runs = $this->runs($nodes, $style);
        // Nur Leerraum? Dann nichts
        $hasText = false;
        foreach ($runs as $r) {
            if ($r !== "\n" && trim($r[0]) !== '') {
                $hasText = true;
                break;
            }
        }
        if (! $hasText) {
            return [];
        }
        // führende/abschließende Umbrüche entfernen
        while ($runs !== [] && $runs[0] === "\n") {
            array_shift($runs);
        }
        while ($runs !== [] && end($runs) === "\n") {
            array_pop($runs);
        }
        $lines = $this->wrap($runs, $width);
        $frags = [];
        $underline = $nodes !== [] && $nodes[0]->parentNode instanceof DOMElement && in_array('sender', $this->classes($nodes[0]->parentNode), true);
        foreach ($lines as $ln) {
            $size = $ln['size'] ?: $style['size'];
            $h = $size * self::LH;
            $align = $style['align'];
            $frags[] = ['h' => $h, 'draw' => function (PdfWriter $p, $x, $y) use ($ln, $size, $h, $align, $width, $underline) {
                $off = match ($align) {
                    'center' => ($width - $ln['w']) / 2,
                    'right' => $width - $ln['w'],
                    default => 0,
                };
                $base = $y + ($h - $size) / 2 + $size * 0.82;
                foreach ($ln['words'] as $wd) {
                    $p->text($x + $off + $wd['x'], $base, $wd['t'], $wd['size'], $wd['bold'], $wd['color']);
                }
                if ($underline) {
                    $p->line($x + $off, $base + 2.2, $x + $off + $ln['w'], $base + 2.2, 0.5, '#999999');
                }
            }];
        }

        return $frags;
    }

    /** Breite des Inhalts in einer Zeile (ohne Umbruch, längste Zeile bei <br>). */
    private function naturalWidth(DOMNode $node, array $style): float
    {
        $nodes = $node instanceof DOMElement ? iterator_to_array($node->childNodes) : [$node];
        $lines = $this->wrap($this->runs($nodes, $style), 10000);
        $w = 0.0;
        foreach ($lines as $l) {
            $w = max($w, $l['w']);
        }
        // Blockkinder (z. B. <p> in Zellen)
        if ($node instanceof DOMElement) {
            foreach ($node->childNodes as $c) {
                if ($this->isBlock($c)) {
                    [$cs] = $this->style($c, $style);
                    $w = max($w, $this->naturalWidth($c, $cs));
                }
            }
        }

        return $w;
    }

    /** Breite des längsten Wortes. */
    private function minWidth(DOMNode $node, array $style): float
    {
        $nodes = $node instanceof DOMElement ? iterator_to_array($node->childNodes) : [$node];
        $max = 0.0;
        foreach ($this->runs($nodes, $style) as $r) {
            if ($r === "\n") {
                continue;
            }
            foreach (explode(' ', $r[0]) as $word) {
                $max = max($max, PdfWriter::width(PdfWriter::encode($word), $r[1], $r[2]));
            }
        }

        return min($max, 160);
    }

    // ---------------------------------------------------------------- Listen

    private function listBlock(DOMElement $el, array $style, float $width, bool $ordered): array
    {
        $frags = [];
        $i = 0;
        foreach ($el->childNodes as $li) {
            if (! $li instanceof DOMElement || strtolower($li->tagName) !== 'li') {
                continue;
            }
            $i++;
            [$s] = $this->style($li, $style);
            $kids = $this->blockChildren($li, $s, $width - 14);
            if ($kids === []) {
                continue;
            }
            $bullet = $ordered ? $i.'.' : chr(149);
            $size = $s['size'];
            $first = array_shift($kids);
            $draw = $first['draw'];
            $first['draw'] = function (PdfWriter $p, $x, $y) use ($draw, $bullet, $size, $first) {
                $p->text($x - 12, $y + ($first['h'] - $size) / 2 + $size * 0.82, $bullet, $size, false, '#333333');
                if ($draw) {
                    $draw($p, $x, $y);
                }
            };
            foreach (array_merge([$first], $kids) as $f) {
                $d = $f['draw'];
                $f['draw'] = $d ? fn (PdfWriter $p, $x, $y) => $d($p, $x + 14, $y) : null;
                $frags[] = $f;
            }
            $frags[] = $this->spacer(1.5, false);
        }

        return $frags;
    }

    // ---------------------------------------------------------------- Tabellen

    private function table(DOMElement $table, array $style, float $width, array $tb): array
    {
        $classes = $this->classes($table);
        $meta = in_array('hb-meta', $classes, true);
        $rows = [];
        foreach ($table->getElementsByTagName('tr') as $tr) {
            /** @var DOMElement $tr */
            $inHead = $tr->parentNode instanceof DOMElement && strtolower($tr->parentNode->tagName) === 'thead';
            [$rs] = $this->style($tr, $style);
            $cells = [];
            foreach ($tr->childNodes as $td) {
                if (! $td instanceof DOMElement || ! in_array(strtolower($td->tagName), ['td', 'th'], true)) {
                    continue;
                }
                [$cs] = $this->style($td, $rs);
                if ($meta && strtolower($td->tagName) === 'th') {
                    $cs['bold'] = false;
                    $cs['color'] = '#555555';
                    $cs['size'] = $rs['size'];
                }
                $cells[] = ['el' => $td, 'style' => $cs, 'span' => max(1, (int) $td->getAttribute('colspan')), 'th' => strtolower($td->tagName) === 'th'];
            }
            if ($cells !== []) {
                $rows[] = ['cells' => $cells, 'head' => $inHead, 'classes' => $this->classes($tr), 'tr' => $tr];
            }
        }
        if ($rows === []) {
            return [];
        }
        $ncols = max(array_map(fn ($r) => array_sum(array_column($r['cells'], 'span')), $rows));
        $padH = $meta ? 0 : 3.7;
        $padV = $meta ? 1.6 : 3.2;

        // Spaltenbreiten
        $nat = array_fill(0, $ncols, 0.0);
        $min = array_fill(0, $ncols, 0.0);
        foreach ($rows as $r) {
            $c = 0;
            foreach ($r['cells'] as $cell) {
                if ($cell['span'] === 1 && $c < $ncols) {
                    $nat[$c] = max($nat[$c], $this->naturalWidth($cell['el'], $cell['style']) + 2 * $padH + ($meta ? 14 : 1));
                    $min[$c] = max($min[$c], $this->minWidth($cell['el'], $cell['style']) + 2 * $padH + 1);
                }
                $c += $cell['span'];
            }
        }
        $sumNat = array_sum($nat);
        $sumMin = array_sum($min);
        if ($meta) {
            $widths = $sumNat <= $width ? $nat : $this->shrink($nat, $min, $width);
            if (count($widths) > 1 && array_sum($widths) < $width) {
                $widths[$ncols - 1] = $width - array_sum(array_slice($widths, 0, -1));
            }
        } elseif ($sumNat <= $width) {
            // Rest auf breite Textspalten verteilen (Zahlenspalten bleiben schmal)
            $extra = $width - $sumNat;
            $weights = [];
            foreach ($nat as $i => $w) {
                $weights[$i] = $this->isNumericColumn($rows, $i) ? 0.15 * $w : $w;
            }
            $tw = array_sum($weights) ?: 1;
            $widths = array_map(fn ($w, $i) => $w + $extra * $weights[$i] / $tw, $nat, array_keys($nat));
        } else {
            $widths = $this->shrink($nat, $min, $width);
        }
        // Raumtabellen im Übergabeprotokoll: feste Spalten wie in der Druckansicht
        if (in_array('hb-t', $classes, true) && $table->parentNode instanceof DOMElement && in_array('hb-room', $this->classes($table->parentNode), true) && in_array($ncols, [3, 4], true)) {
            $pct = $ncols === 4 ? [0.30, 0.22, 0.24, 0.24] : [0.30, 0.24, 0.46];
            $widths = array_map(fn ($q) => $q * $width, $pct);
        }
        $tableW = array_sum($widths);

        $frags = [];
        $header = null;
        foreach ($rows as $r) {
            $isSum = in_array('sum', $r['classes'], true);
            $isRes = in_array('res', $r['classes'], true);
            $bg = in_array('hb-new', $r['classes'], true) ? '#fef3f2' : null;
            $layout = [];
            $c = 0;
            $rowH = 0.0;
            foreach ($r['cells'] as $cell) {
                $cw = array_sum(array_slice($widths, $c, $cell['span']));
                $cs = $cell['style'];
                if ($isSum || $isRes) {
                    $cs['bold'] = true;
                }
                if ($isRes) {
                    $cs['size'] = 11;
                }
                $kids = $this->blockChildren($cell['el'], $cs, max(10, $cw - 2 * $padH));
                $kids = array_values(array_filter($kids, fn ($f) => empty($f['space'])));
                $h = $this->sum($kids) + 2 * $padV;
                $rowH = max($rowH, $h);
                $layout[] = ['x' => array_sum(array_slice($widths, 0, $c)), 'w' => $cw, 'kids' => $kids, 'th' => $cell['th']];
                $c += $cell['span'];
            }
            $head = $r['head'];
            $frag = ['h' => $rowH, 'draw' => function (PdfWriter $p, $x, $y) use ($layout, $rowH, $padH, $padV, $meta, $isSum, $isRes, $bg, $tableW, $head) {
                if ($bg) {
                    $p->rect($x, $y, $tableW, $rowH, $bg);
                }
                foreach ($layout as $cell) {
                    $this->drawStack($p, $cell['kids'], $x + $cell['x'] + $padH, $y + $padV);
                }
                if ($meta) {
                    return;
                }
                if ($isSum) {
                    $p->line($x, $y, $x + $tableW, $y, 0.8, '#333333');
                } elseif (! $isRes) {
                    $p->line($x, $y + $rowH, $x + $tableW, $y + $rowH, $head ? 0.8 : 0.3, $head ? '#333333' : '#bbbbbb');
                }
            }];
            if ($head) {
                $frag['isHeader'] = true;
                $header = $frag;
            } elseif ($header !== null) {
                $frag['header'] = $header;
            }
            $frags[] = $frag;
        }
        // Kopfzeile nicht allein am Seitenende
        foreach ($frags as $i => $f) {
            if (! empty($f['isHeader'])) {
                $frags[$i]['keepNext'] = true;
            }
        }

        return $frags;
    }

    private function isNumericColumn(array $rows, int $col): bool
    {
        foreach ($rows as $r) {
            $c = 0;
            foreach ($r['cells'] as $cell) {
                if ($c === $col && $cell['span'] === 1) {
                    return in_array('n', $this->classes($cell['el']), true);
                }
                $c += $cell['span'];
            }
        }

        return false;
    }

    private function shrink(array $nat, array $min, float $width): array
    {
        $sumMin = array_sum($min);
        if ($sumMin >= $width) {
            return array_map(fn ($m) => $m * $width / $sumMin, $min);
        }
        $flex = array_map(fn ($n, $m) => max(0, $n - $m), $nat, $min);
        $tf = array_sum($flex) ?: 1;

        return array_map(fn ($m, $f) => $m + ($width - $sumMin) * $f / $tf, $min, $flex);
    }

    // ---------------------------------------------------------------- Spalten (Unterschriften)

    private function row(DOMElement $el, array $style, float $width, array $b): array
    {
        $cols = [];
        foreach ($el->childNodes as $c) {
            if ($c instanceof DOMElement && ! $this->skip($c)) {
                $cols[] = $c;
            }
        }
        $n = max(1, count($cols));
        $gap = 24;
        $cw = ($width - $gap * ($n - 1)) / $n;
        $layout = [];
        $rowH = 0.0;
        foreach ($cols as $i => $col) {
            [$cs, $cb] = $this->style($col, $style);
            $kids = $this->blockChildren($col, $cs, $cw);
            $top = ! empty($b['childTop']) || ! empty($cb['top']);
            $h = $this->sum($kids) + ($top ? 3 : 0);
            $minh = $cb['minh'] ?? 0;
            $rowH = max($rowH, $h, $minh);
            $layout[] = ['kids' => $kids, 'h' => $h, 'x' => $i * ($cw + $gap), 'top' => $top, 'bottom' => ! empty($cb['bottom'])];
        }

        return ['h' => $rowH, 'draw' => function (PdfWriter $p, $x, $y) use ($layout, $rowH, $cw) {
            foreach ($layout as $col) {
                $yy = $col['bottom'] ? $y + $rowH - $col['h'] : $y;
                if ($col['top']) {
                    $p->line($x + $col['x'], $yy, $x + $col['x'] + $cw, $yy, 0.6, '#333333');
                    $yy += 3;
                }
                $this->drawStack($p, $col['kids'], $x + $col['x'], $yy);
            }
        }];
    }

    // ---------------------------------------------------------------- Bilder

    /** data:-Bild als JPEG [jpeg, w, h] oder null. Bei $crop wird auf 4:3 zugeschnitten. */
    private function jpeg(string $src, bool $crop = false): ?array
    {
        if (! preg_match('#^data:image/(png|jpe?g|webp|gif);base64,(.+)$#s', $src, $m) || ! function_exists('imagecreatefromstring')) {
            return null;
        }
        $raw = base64_decode($m[2], true);
        if ($raw === false || strlen($raw) > 8_000_000) {
            return null;
        }
        $img = @imagecreatefromstring($raw);
        if ($img === false) {
            return null;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $sx = 0;
        $sy = 0;
        $tw = $w;
        $th = $h;
        if ($crop) {
            if ($w / max(1, $h) > 4 / 3) {
                $tw = (int) round($h * 4 / 3);
                $sx = (int) (($w - $tw) / 2);
            } else {
                $th = (int) round($w * 3 / 4);
                $sy = (int) (($h - $th) / 2);
            }
        }
        $scale = min(1, 1400 / max($tw, $th));
        $dw = max(1, (int) round($tw * $scale));
        $dh = max(1, (int) round($th * $scale));
        $out = imagecreatetruecolor($dw, $dh);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopyresampled($out, $img, 0, 0, $sx, $sy, $dw, $dh, $tw, $th);
        ob_start();
        imagejpeg($out, null, 85);
        $data = (string) ob_get_clean();
        imagedestroy($img);
        imagedestroy($out);

        return [$data, $dw, $dh];
    }

    private function imageBlock(DOMElement $img, float $width, float $maxH): array
    {
        $j = $this->jpeg($img->getAttribute('src'));
        if ($j === null) {
            return [];
        }
        [$data, $pw, $ph] = $j;
        $h = min($maxH, $ph * 0.75);
        $w = $h * $pw / $ph;
        if ($w > $width) {
            $w = $width;
            $h = $w * $ph / $pw;
        }
        $key = 'i'.(++$this->imgCounter);

        return [['h' => $h + 2, 'draw' => fn (PdfWriter $p, $x, $y) => $p->image($key, $data, $pw, $ph, $x, $y, $w, $h)]];
    }

    private function photos(DOMElement $el, float $width): array
    {
        $imgs = [];
        foreach ($el->getElementsByTagName('img') as $img) {
            $j = $this->jpeg($img->getAttribute('src'), true);
            if ($j !== null) {
                $imgs[] = $j;
            }
        }
        $gap = 6;
        $w = min(150, ($width - 2 * $gap) / 3);
        $h = $w * 3 / 4;
        $frags = [];
        foreach (array_chunk($imgs, 3) as $chunk) {
            $items = [];
            foreach ($chunk as $j) {
                $items[] = ['key' => 'p'.(++$this->imgCounter), 'j' => $j];
            }
            $frags[] = ['h' => $h + $gap, 'draw' => function (PdfWriter $p, $x, $y) use ($items, $w, $h, $gap) {
                foreach ($items as $i => $it) {
                    [$data, $pw, $ph] = $it['j'];
                    $p->image($it['key'], $data, $pw, $ph, $x + $i * ($w + $gap), $y, $w, $h);
                    $p->rect($x + $i * ($w + $gap), $y, $w, $h, null, '#dddddd', 0.4);
                }
            }];
        }

        return $frags;
    }

    // ---------------------------------------------------------------- Seiten

    private function paginate(array $frags): void
    {
        $first = $this->pdf->pageCount();
        $this->pdf->addPage();
        $y = self::TOP;
        $bottom = PdfWriter::H - self::BOTTOM;
        $atTop = true;
        $count = count($frags);
        for ($i = 0; $i < $count; $i++) {
            $f = $frags[$i];
            if (! empty($f['space']) && $atTop) {
                continue;
            }
            $need = $f['h'];
            if (! empty($f['keepNext'])) {
                $j = $i + 1;
                while ($j < $count && ! empty($frags[$j]['space'])) {
                    $j++;
                }
                if ($j < $count && $frags[$j]['h'] < $this->contentH / 3) {
                    $need += $frags[$j]['h'];
                }
            }
            if ($y + $need > $bottom && ! $atTop) {
                if (! empty($f['space'])) {
                    continue;
                }
                $this->pdf->addPage();
                $y = self::TOP;
                $atTop = true;
                if (! empty($f['header'])) {
                    ($f['header']['draw'])($this->pdf, self::LEFT, $y);
                    $y += $f['header']['h'];
                }
            }
            if ($f['draw']) {
                ($f['draw'])($this->pdf, self::LEFT, $y);
            }
            $y += $f['h'];
            $atTop = $atTop && ! empty($f['space']);
            if ($f['draw']) {
                $atTop = false;
            }
        }
        // Seitenzahlen, wenn das Schreiben mehrere Seiten hat
        $last = $this->pdf->pageCount();
        $n = $last - $first;
        if ($n > 1) {
            for ($i = 0; $i < $n; $i++) {
                $this->pdf->setPage($first + $i);
                $t = PdfWriter::encode('Seite '.($i + 1).' von '.$n);
                $this->pdf->text((PdfWriter::W - PdfWriter::width($t, 7.5)) / 2, PdfWriter::H - 10 * self::MM, $t, 7.5, false, '#777777');
            }
            $this->pdf->setPage($last - 1);
        }
    }
}
