<?php

namespace App\Support\Mail;

use App\Models\ReceivedEmail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/** Darstellung im E-Mail-Bereich wie bei Outlook (gleicher Aufbau wie bei Freytag): Zeiten, Gruppen, Kürzel, Symbole, sichere HTML-Ansicht. */
class MailView
{
    private const DAYS = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];

    private const COLORS = ['#0f6cbd', '#8764b8', '#038387', '#ca5010', '#4f6bed', '#c239b3', '#498205', '#986f0b', '#d13438', '#005b70'];

    /** Zeit in der Liste: heute Uhrzeit, gestern „Gestern“, diese Woche Wochentag, sonst Datum. */
    public static function shortTime(?Carbon $t): string
    {
        if ($t === null) {
            return '';
        }
        $t = $t->copy()->timezone('Europe/Berlin');
        $today = now('Europe/Berlin')->startOfDay();
        if ($t >= $today) {
            return $t->format('H:i');
        }
        if ($t >= $today->copy()->subDay()) {
            return 'Gestern';
        }
        if ($t >= $today->copy()->subDays(6)) {
            return self::DAYS[(int) $t->format('w')].' '.$t->format('H:i');
        }

        return $t->format('d.m.Y');
    }

    public static function longTime(?Carbon $t): string
    {
        if ($t === null) {
            return '';
        }
        $t = $t->copy()->timezone('Europe/Berlin');

        return self::DAYS[(int) $t->format('w')].', '.$t->format('d.m.Y H:i');
    }

    /** Überschrift der Gruppe in der Liste. */
    public static function group(?Carbon $t): string
    {
        if ($t === null) {
            return 'Älter';
        }
        $t = $t->copy()->timezone('Europe/Berlin');
        $today = now('Europe/Berlin')->startOfDay();
        $week = $today->copy()->startOfWeek();

        return match (true) {
            $t >= $today => 'Heute',
            $t >= $today->copy()->subDay() => 'Gestern',
            $t >= $week => 'Diese Woche',
            $t >= $week->copy()->subWeek() => 'Letzte Woche',
            $t >= $today->copy()->startOfMonth() => 'Dieser Monat',
            default => 'Älter',
        };
    }

    public static function size(int $b): string
    {
        return $b >= 1048576 ? number_format($b / 1048576, 1, ',', '').' MB' : max(1, (int) round($b / 1024)).' KB';
    }

    /** Rundes Namenskürzel mit fester Farbe je Adresse. */
    public static function avatar(string $name, string $address, string $class = ''): string
    {
        $name = trim(preg_replace('/\s*\(.*?\)\s*/', ' ', $name !== '' ? $name : (string) strstr($address.'@', '@', true)));
        if (str_contains($name, ',')) {
            $name = trim(implode(' ', array_reverse(array_map('trim', explode(',', $name, 2))))); // „Bauer, Anna“
        }
        $w = preg_split('/[\s._-]+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
        $short = mb_strtoupper(mb_substr($w[0], 0, 1).(count($w) > 1 ? mb_substr(end($w), 0, 1) : ''));
        $color = self::COLORS[crc32(mb_strtolower($address)) % count(self::COLORS)];

        return '<span class="pf-avatar '.e($class).'" style="--av:'.$color.'" aria-hidden="true">'.e($short ?: '?').'</span>';
    }

    public static function icon(string $n): string
    {
        static $i = [
            'inbox' => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
            'send' => '<path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/>',
            'archive' => '<rect x="2" y="3" width="20" height="5" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"/><path d="M10 12h4"/>',
            'trash' => '<path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>',
            'reply' => '<path d="M9 17l-5-5 5-5"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/>',
            'replyall' => '<path d="M7 17l-5-5 5-5"/><path d="M12 17l-5-5 5-5"/><path d="M22 18v-2a4 4 0 0 0-4-4H7"/>',
            'forward' => '<path d="M15 17l5-5-5-5"/><path d="M4 18v-2a4 4 0 0 1 4-4h12"/>',
            'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
            'clip' => '<path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>',
            'pen' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>',
            'back' => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
            'search' => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/>',
            'user' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
            'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
            'image' => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>',
            'sign' => '<path d="M3 17c3-3 5-9 8-9s-1 8 2 8 3-4 5-4 2 3 3 3"/><path d="M3 21h18"/>',
            'refresh' => '<path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>',
            'gear' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68 1.65 1.65 0 0 0 10 3.17V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
            'alert' => '<circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>',
            'x' => '<path d="M18 6L6 18M6 6l12 12"/>',
        ];

        return '<svg class="ic" viewBox="0 0 24 24" aria-hidden="true">'.($i[$n] ?? '').'</svg>';
    }

    public const SEPARATOR = '---------- Weitergeleitete Nachricht ----------';

    /** Text sicher ausgeben: Internetadressen anklickbar, Zitate abgesetzt, Kopf weitergeleiteter Nachrichten fett. */
    public static function textHtml(string $t): string
    {
        $out = '';
        $quote = false;
        $head = false;
        foreach (explode("\n", $t) as $z) {
            if (rtrim($z) === self::SEPARATOR) {
                $out .= '<hr class="pf-trenner">';
                $head = true;

                continue;
            }
            if ($head && trim($z) === '') {
                $head = false;
            }
            if ($head && preg_match('/^(Von|Gesendet|An|Cc|Betreff|Datum|From|Sent|To|Subject):(.*)$/u', $z, $m)) {
                $out .= '<b>'.e($m[1]).':</b>'.e($m[2])."\n";

                continue;
            }
            $is = str_starts_with($z, '>');
            if ($is && ! $quote) {
                $out .= '<blockquote>';
                $quote = true;
            }
            if (! $is && $quote) {
                $out .= '</blockquote>';
                $quote = false;
            }
            $z = e($is ? (string) preg_replace('/^(>\s?)+/', '', $z) : $z);
            $out .= preg_replace_callback('#https?://[^\s<>"\')\]]+#', fn ($m) => '<a href="'.$m[0].'" target="_blank" rel="noopener noreferrer">'.$m[0].'</a>', $z)."\n";
        }
        if ($quote) {
            $out .= '</blockquote>';
        }

        return rtrim($out);
    }

    /** Enthält die HTML-Fassung Bilder aus dem Internet? */
    public static function hasExternalImages(string $html): bool
    {
        return (bool) preg_match('#(src|background)\s*=\s*["\']?\s*https?:|url\(\s*["\']?https?:#i', $html);
    }

    /**
     * HTML für den abgeschotteten Rahmen: ohne Skripte, Formulare und eingebettete Fremdinhalte;
     * eingebettete Bilder (cid:) als data:-Adresse. Bilder aus dem Internet blockt die CSP der Antwort.
     */
    public static function safeHtml(ReceivedEmail $mail, bool $external = false): string
    {
        $h = (string) $mail->body_html;
        $h = (string) preg_replace('#<(script|iframe|object|embed|applet|form|frameset|frame|noscript)\b.*?(</\1\s*>|$)#is', '', $h);
        $h = (string) preg_replace('#<(script|iframe|object|embed|applet|form|frame|base|link|meta)\b[^>]*>#i', '', $h);
        $h = (string) preg_replace('#\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $h);
        $h = (string) preg_replace('#(href|src|action)\s*=\s*(["\']?)\s*(javascript|vbscript|data:text):#i', '$1=$2#', $h);

        $images = [];
        foreach ($mail->attachments ?? [] as $a) {
            $cid = mb_strtolower((string) ($a['cid'] ?? ''));
            if ($cid !== '' && str_starts_with((string) $a['mime'], 'image/') && $a['mime'] !== 'image/svg+xml' && ($a['size'] ?? 0) < 5000000
                && Storage::disk('local')->exists($a['path'])) {
                $images[$cid] = 'data:'.$a['mime'].';base64,'.base64_encode((string) Storage::disk('local')->get($a['path']));
            }
        }
        if ($images !== []) {
            $h = (string) preg_replace_callback('#cid:([^"\'\s>)]+)#i', fn ($m) => $images[mb_strtolower(rawurldecode($m[1]))] ?? $m[0], $h);
        }
        $style = '<base target="_blank"><style>html,body{margin:0;background:#fff;color:#000}body{padding:4px 2px;font-family:Calibri,"Segoe UI",Arial,sans-serif;font-size:15px;line-height:1.4;overflow-wrap:anywhere}img{max-width:100%;height:auto}table{max-width:100%}pre{white-space:pre-wrap}'.($external ? '' : 'img[src^="http"]{display:none}').'</style>';
        if (preg_match('#<head\b[^>]*>#i', $h)) {
            return (string) preg_replace('#<head\b[^>]*>#i', '$0'.$style, $h, 1);
        }

        return '<!DOCTYPE html><html><head><meta charset="utf-8">'.$style.'</head><body>'.$h.'</body></html>';
    }
}
