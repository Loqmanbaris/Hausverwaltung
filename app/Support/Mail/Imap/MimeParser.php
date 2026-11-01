<?php

namespace App\Support\Mail\Imap;

/**
 * Zerlegt eine E-Mail (RFC 5322 / MIME) in Kopfzeilen, lesbaren Text und Anhänge.
 * HTML-Inhalte werden nur als Text übernommen, damit im CRM nie fremdes HTML oder Skript angezeigt wird.
 */
class MimeParser
{
    /**
     * @return array{
     *   subject: string, from_address: string, from_name: string, to: string, cc: string, reply_to: string,
     *   date: ?\DateTimeImmutable, message_id: string, in_reply_to: string, references: string,
     *   text: string, attachments: array<int, array{name: string, mime: string, data: string}>, headers: array<string, string>
     * }
     */
    public static function parse(string $raw): array
    {
        [$headers, $body] = self::split($raw);
        $text = '';
        $html = '';
        $attachments = [];
        self::walk($headers, $body, $text, $html, $attachments, 0);

        if (trim($text) === '' && $html !== '') {
            $text = self::htmlToText($html);
        }
        $text = self::tidy($text);

        [$fromAddr, $fromName] = self::firstAddress($headers['from'] ?? '');
        $date = null;
        if (! empty($headers['date'])) {
            $clean = preg_replace('/\s*\([^)]*\)\s*$/', '', $headers['date']);
            try {
                $date = new \DateTimeImmutable($clean);
            } catch (\Throwable) {
                $date = null;
            }
        }

        return [
            'subject' => self::decodeHeader($headers['subject'] ?? ''),
            'from_address' => $fromAddr,
            'from_name' => $fromName,
            'to' => self::addressList($headers['to'] ?? ''),
            'cc' => self::addressList($headers['cc'] ?? ''),
            'reply_to' => self::firstAddress($headers['reply-to'] ?? '')[0],
            'date' => $date,
            'message_id' => trim($headers['message-id'] ?? ''),
            'in_reply_to' => trim($headers['in-reply-to'] ?? ''),
            'references' => trim(preg_replace('/\s+/', ' ', $headers['references'] ?? '')),
            'text' => $text,
            'attachments' => $attachments,
            'headers' => $headers,
        ];
    }

    /** @return array{0: array<string, string>, 1: string} Kopfzeilen (Name klein) und Inhalt */
    public static function split(string $raw): array
    {
        $raw = str_replace("\r\n", "\n", $raw);
        $pos = strpos($raw, "\n\n");
        if ($pos === false) {
            $head = $raw;
            $body = '';
        } else {
            $head = substr($raw, 0, $pos);
            $body = substr($raw, $pos + 2);
        }
        $head = preg_replace("/\n[ \t]+/", ' ', $head);
        $headers = [];
        foreach (explode("\n", (string) $head) as $line) {
            $c = strpos($line, ':');
            if ($c === false) {
                continue;
            }
            $name = strtolower(trim(substr($line, 0, $c)));
            $value = trim(substr($line, $c + 1));
            // Bei mehrfachen Kopfzeilen zählt die erste (z. B. Received bleibt unwichtig)
            if (! isset($headers[$name])) {
                $headers[$name] = $value;
            }
        }

        return [$headers, $body];
    }

    private static function walk(array $headers, string $body, string &$text, string &$html, array &$attachments, int $depth): void
    {
        if ($depth > 12) {
            return;
        }
        [$type, $params] = self::headerParams($headers['content-type'] ?? 'text/plain');
        $type = strtolower($type ?: 'text/plain');
        [$disp, $dparams] = self::headerParams($headers['content-disposition'] ?? '');
        $disp = strtolower($disp);

        if (str_starts_with($type, 'multipart/') && ! empty($params['boundary'])) {
            $parts = self::multipartParts($body, $params['boundary']);
            if ($type === 'multipart/alternative') {
                // Erst Text, sonst HTML; Anhänge in Unterteilen trotzdem einsammeln
                $t = '';
                $h = '';
                foreach ($parts as $p) {
                    [$ph, $pb] = self::split($p);
                    self::walk($ph, $pb, $t, $h, $attachments, $depth + 1);
                }
                if (trim($t) !== '') {
                    $text .= ($text !== '' ? "\n\n" : '').$t;
                } elseif ($h !== '') {
                    $html .= $h;
                }

                return;
            }
            foreach ($parts as $p) {
                [$ph, $pb] = self::split($p);
                self::walk($ph, $pb, $text, $html, $attachments, $depth + 1);
            }

            return;
        }

        $data = self::decodeBody($body, strtolower(trim($headers['content-transfer-encoding'] ?? '')));
        $name = self::fileName($dparams, $params);

        $isAttachment = $disp === 'attachment' || ($name !== '' && $disp !== 'inline' && ! in_array($type, ['text/plain', 'text/html'], true))
            || ! in_array($type, ['text/plain', 'text/html', 'message/delivery-status', 'text/rfc822-headers'], true);
        if ($type === 'message/rfc822') {
            $isAttachment = true;
            if ($name === '') {
                $inner = self::split($data)[0];
                $name = (self::decodeHeader($inner['subject'] ?? '') ?: 'Nachricht').'.eml';
            }
        }

        if ($isAttachment) {
            if ($data === '') {
                return;
            }
            $attachments[] = [
                'name' => self::safeName($name !== '' ? $name : 'anhang-'.(count($attachments) + 1).self::extension($type)),
                'mime' => $type,
                'data' => $data,
            ];

            return;
        }

        $data = self::toUtf8($data, $params['charset'] ?? '');
        if ($type === 'text/html') {
            $html .= $data;
        } else {
            $text .= ($text !== '' ? "\n\n" : '').$data;
        }
    }

    /** @return string[] */
    private static function multipartParts(string $body, string $boundary): array
    {
        $parts = [];
        $delim = '--'.$boundary;
        $chunks = explode("\n".$delim, "\n".$body);
        array_shift($chunks); // Text vor der ersten Grenze
        foreach ($chunks as $c) {
            if (str_starts_with($c, '--')) {
                break; // Schlussgrenze
            }
            // Rest der Grenzzeile (Leerzeichen) entfernen
            $nl = strpos($c, "\n");
            $parts[] = $nl === false ? '' : substr($c, $nl + 1);
        }

        return $parts;
    }

    public static function decodeBody(string $body, string $encoding): string
    {
        return match ($encoding) {
            'base64' => (string) base64_decode(preg_replace('/[^A-Za-z0-9+\/=]/', '', $body)),
            'quoted-printable' => quoted_printable_decode(str_replace("\n", "\r\n", $body)),
            default => $body,
        };
    }

    /** @return array{0: string, 1: array<string, string>} */
    public static function headerParams(string $value): array
    {
        $parts = self::splitParams($value);
        $main = trim((string) array_shift($parts));
        $params = [];
        $continued = [];
        foreach ($parts as $p) {
            $eq = strpos($p, '=');
            if ($eq === false) {
                continue;
            }
            $k = strtolower(trim(substr($p, 0, $eq)));
            $v = trim(substr($p, $eq + 1));
            if (strlen($v) >= 2 && $v[0] === '"' && str_ends_with($v, '"')) {
                $v = (string) preg_replace('/\\\\(.)/s', '$1', substr($v, 1, -1));
            }
            // RFC 2231: name*0*=…, name*1*=… und name*=utf-8''…
            if (preg_match('/^([^*]+)\*(\d+)?(\*)?$/', $k, $m)) {
                $continued[$m[1]][(int) ($m[2] ?? 0)] = [$v, isset($m[3]) || ($m[2] ?? '') === ''];

                continue;
            }
            $params[$k] = $v;
        }
        foreach ($continued as $k => $segs) {
            ksort($segs);
            $charset = 'UTF-8';
            $out = '';
            foreach ($segs as $i => [$v, $enc]) {
                if ($enc) {
                    if ($i === array_key_first($segs) && preg_match("/^([^']*)'[^']*'(.*)$/", $v, $m)) {
                        $charset = $m[1] ?: 'UTF-8';
                        $v = $m[2];
                    }
                    $v = rawurldecode($v);
                }
                $out .= $v;
            }
            $params[$k] = self::toUtf8($out, $charset);
        }

        return [$main, $params];
    }

    /** @return string[] Teilt an Semikolons außerhalb von Anführungszeichen */
    private static function splitParams(string $value): array
    {
        $out = [];
        $cur = '';
        $q = false;
        $len = strlen($value);
        for ($i = 0; $i < $len; $i++) {
            $ch = $value[$i];
            if ($ch === '\\' && $q && $i + 1 < $len) {
                $cur .= $ch.$value[++$i];

                continue;
            }
            if ($ch === '"') {
                $q = ! $q;
            }
            if ($ch === ';' && ! $q) {
                $out[] = $cur;
                $cur = '';

                continue;
            }
            $cur .= $ch;
        }
        $out[] = $cur;

        return $out;
    }

    private static function fileName(array $dparams, array $params): string
    {
        $n = $dparams['filename'] ?? $params['name'] ?? '';

        return self::decodeHeader((string) $n);
    }

    public static function decodeHeader(string $value): string
    {
        if ($value === '') {
            return '';
        }
        if (str_contains($value, '=?')) {
            // Leerraum zwischen zwei kodierten Wörtern gehört nicht zum Text (RFC 2047)
            $value = preg_replace('/(\?=)\s+(=\?)/', '$1$2', $value);
            $out = preg_replace_callback('/=\?([^?]+)\?([bBqQ])\?([^?]*)\?=/', function ($m) {
                $charset = preg_replace('/\*.*$/', '', $m[1]);
                $data = strtoupper($m[2]) === 'B'
                    ? (string) base64_decode($m[3])
                    : quoted_printable_decode(str_replace('_', ' ', $m[3]));

                return self::toUtf8($data, $charset);
            }, $value);

            return trim((string) $out);
        }

        return trim(self::toUtf8($value, ''));
    }

    public static function toUtf8(string $s, string $charset): string
    {
        $charset = strtoupper(trim($charset, " \"'"));
        if ($charset === '' || $charset === 'UTF-8' || $charset === 'UTF8' || $charset === 'US-ASCII' || $charset === 'ASCII') {
            return mb_check_encoding($s, 'UTF-8') ? $s : mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
        }
        if (in_array($charset, ['ISO-8859-1', 'LATIN1', 'ISO8859-1'], true)) {
            $charset = 'Windows-1252';
        }
        try {
            if (in_array(strtolower($charset), array_map('strtolower', mb_list_encodings()), true)) {
                $r = mb_convert_encoding($s, 'UTF-8', $charset);
                if (is_string($r)) {
                    return $r;
                }
            }
        } catch (\Throwable) {
        }
        if (function_exists('iconv')) {
            $r = @iconv($charset, 'UTF-8//IGNORE', $s);
            if (is_string($r) && $r !== '') {
                return $r;
            }
        }

        return mb_convert_encoding($s, 'UTF-8', 'UTF-8');
    }

    /** @return array{0: string, 1: string} Adresse (klein) und Name */
    public static function firstAddress(string $value): array
    {
        $list = self::addresses($value);

        return $list[0] ?? ['', ''];
    }

    /** @return array<int, array{0: string, 1: string}> */
    public static function addresses(string $value): array
    {
        $value = self::toUtf8($value, '');
        $out = [];
        // An Kommas außerhalb von Anführungszeichen und spitzen Klammern trennen
        $items = [];
        $cur = '';
        $q = false;
        $a = false;
        foreach (preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
            if ($ch === '"') {
                $q = ! $q;
            } elseif ($ch === '<' && ! $q) {
                $a = true;
            } elseif ($ch === '>' && ! $q) {
                $a = false;
            }
            if ($ch === ',' && ! $q && ! $a) {
                $items[] = $cur;
                $cur = '';

                continue;
            }
            $cur .= $ch;
        }
        $items[] = $cur;
        foreach ($items as $it) {
            $it = trim($it);
            if ($it === '') {
                continue;
            }
            if (preg_match('/^(.*)<([^>]+)>\s*$/s', $it, $m)) {
                $name = self::decodeHeader(trim(trim($m[1]), '"\' '));
                $addr = trim($m[2]);
            } else {
                $name = '';
                $addr = trim(preg_replace('/\(.*\)/', '', $it), '<> ');
            }
            if ($addr !== '') {
                $out[] = [mb_strtolower($addr), $name];
            }
        }

        return $out;
    }

    private static function addressList(string $value): string
    {
        return implode(', ', array_map(fn ($a) => $a[1] !== '' ? $a[1].' <'.$a[0].'>' : $a[0], self::addresses($value)));
    }

    public static function htmlToText(string $html): string
    {
        $html = preg_replace('#<(head|style|script|title)\b[^>]*>.*?</\1\s*>#is', '', $html);
        $html = preg_replace('#<!--.*?-->#s', '', (string) $html);
        $html = preg_replace('#<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is', '$2 ($1)', (string) $html);
        $html = preg_replace('#<br\s*/?>#i', "\n", (string) $html);
        $html = preg_replace('#</(p|div|tr|li|h[1-6]|table|blockquote)\s*>#i', "\n", (string) $html);
        $html = preg_replace('#<li\b[^>]*>#i', '• ', (string) $html);
        $html = preg_replace('#</t[dh]\s*>#i', "\t", (string) $html);
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Wiederholte Links „Text (Text)“ kürzen
        $text = preg_replace('/(\S+) \(\1\)/', '$1', $text);
        $text = str_replace("\u{00A0}", ' ', (string) $text);

        return $text;
    }

    private static function tidy(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+\n/', "\n", $text);
        $text = preg_replace('/\n{3,}/', "\n\n", (string) $text);

        return trim((string) $text);
    }

    public static function safeName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\:*?"<>|]+/u', '_', $name);
        $name = trim((string) $name, ' ._');

        return mb_substr($name !== '' ? $name : 'anhang', 0, 150);
    }

    private static function extension(string $mime): string
    {
        return match ($mime) {
            'application/pdf' => '.pdf',
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
            'image/gif' => '.gif',
            'text/calendar' => '.ics',
            'message/rfc822' => '.eml',
            default => '.bin',
        };
    }
}
