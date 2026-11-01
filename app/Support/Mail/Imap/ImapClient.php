<?php

namespace App\Support\Mail\Imap;

use RuntimeException;

/**
 * Schlanker IMAP-Zugang (RFC 3501) über eine verschlüsselte Verbindung, ohne PHP-Erweiterung „imap“
 * (die gibt es ab PHP 8.4 nicht mehr im Kern). Liest nur; Nachrichten werden mit BODY.PEEK geholt,
 * bleiben also im Postfach ungelesen und unverändert.
 */
class ImapClient
{
    /** @var resource|null */
    private $stream = null;

    private int $tag = 0;

    public function __construct(
        private string $host,
        private int $port = 993,
        private int $timeout = 20,
    ) {}

    public function connect(): void
    {
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
        $scheme = $this->port === 143 ? 'tcp' : 'ssl';
        $errno = 0;
        $errstr = '';
        $s = @stream_socket_client($scheme.'://'.$this->host.':'.$this->port, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $ctx);
        if ($s === false) {
            throw new RuntimeException('Keine Verbindung zum Posteingangsserver '.$this->host.':'.$this->port.' ('.trim($errstr ?: 'Fehler '.$errno).').');
        }
        stream_set_timeout($s, $this->timeout);
        $this->stream = $s;
        $greeting = $this->readLine();
        if (! str_starts_with($greeting, '* OK') && ! str_starts_with($greeting, '* PREAUTH')) {
            throw new RuntimeException('Unerwartete Antwort des Servers: '.mb_substr($greeting, 0, 120));
        }
        if ($scheme === 'tcp') {
            $this->command('STARTTLS');
            if (! @stream_socket_enable_crypto($s, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Verschlüsselung (STARTTLS) fehlgeschlagen.');
            }
        }
    }

    public function login(string $user, string $password): void
    {
        try {
            $this->command('LOGIN '.self::quote($user).' '.self::quote($password));
        } catch (RuntimeException $e) {
            throw new RuntimeException('Anmeldung am Posteingangsserver fehlgeschlagen. Bitte Benutzername (die vollständige E-Mail-Adresse) und Passwort prüfen.');
        }
    }

    /** @return array{exists: int, uidvalidity: int, uidnext: int} */
    public function select(string $folder = 'INBOX'): array
    {
        $lines = $this->command('EXAMINE '.self::quote($folder));
        $out = ['exists' => 0, 'uidvalidity' => 0, 'uidnext' => 0];
        foreach ($lines as $l) {
            if (preg_match('/^\* (\d+) EXISTS/i', $l, $m)) {
                $out['exists'] = (int) $m[1];
            } elseif (preg_match('/UIDVALIDITY (\d+)/i', $l, $m)) {
                $out['uidvalidity'] = (int) $m[1];
            } elseif (preg_match('/UIDNEXT (\d+)/i', $l, $m)) {
                $out['uidnext'] = (int) $m[1];
            }
        }

        return $out;
    }

    /** @return int[] aufsteigend sortierte UIDs */
    public function uidSearch(string $criteria): array
    {
        $uids = [];
        foreach ($this->command('UID SEARCH '.$criteria) as $l) {
            if (preg_match('/^\* SEARCH\b(.*)$/i', $l, $m)) {
                foreach (preg_split('/\s+/', trim($m[1])) ?: [] as $u) {
                    if ($u !== '' && ctype_digit($u)) {
                        $uids[] = (int) $u;
                    }
                }
            }
        }
        sort($uids);

        return array_values(array_unique($uids));
    }

    /** Größe einer Nachricht in Byte (ohne sie zu laden). */
    public function size(int $uid): int
    {
        foreach ($this->command('UID FETCH '.$uid.' (RFC822.SIZE)') as $l) {
            if (preg_match('/RFC822\.SIZE (\d+)/i', $l, $m)) {
                return (int) $m[1];
            }
        }

        return 0;
    }

    /** Die vollständige Nachricht (Kopf und Inhalt), ohne sie als gelesen zu markieren. */
    public function fetchRaw(int $uid): ?string
    {
        $raw = null;
        foreach ($this->command('UID FETCH '.$uid.' (BODY.PEEK[])', true) as $part) {
            if (is_array($part)) {
                $raw = $part['literal'];
            }
        }

        return $raw;
    }

    /** Nur die Kopfzeilen (für sehr große Nachrichten). */
    public function fetchHeader(int $uid): ?string
    {
        $raw = null;
        foreach ($this->command('UID FETCH '.$uid.' (BODY.PEEK[HEADER])', true) as $part) {
            if (is_array($part)) {
                $raw = $part['literal'];
            }
        }

        return $raw;
    }

    public function logout(): void
    {
        if ($this->stream) {
            try {
                $this->command('LOGOUT');
            } catch (\Throwable) {
            }
            @fclose($this->stream);
            $this->stream = null;
        }
    }

    public function __destruct()
    {
        if ($this->stream) {
            @fclose($this->stream);
        }
    }

    public static function quote(string $s): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $s).'"';
    }

    /**
     * Befehl senden und alle Antwortzeilen bis zur Abschlusszeile lesen.
     * Mit $literals werden Literale ({n}) als ['literal' => …] mitgeliefert.
     *
     * @return array<int, string|array{literal: string}>
     */
    private function command(string $cmd, bool $literals = false): array
    {
        if (! $this->stream) {
            throw new RuntimeException('Keine Verbindung zum Posteingangsserver.');
        }
        $tag = 'A'.str_pad((string) ++$this->tag, 4, '0', STR_PAD_LEFT);
        $this->write($tag.' '.$cmd."\r\n");
        $out = [];
        while (true) {
            $line = $this->readLine();
            if (str_starts_with($line, $tag.' ')) {
                $rest = substr($line, strlen($tag) + 1);
                if (stripos($rest, 'OK') !== 0) {
                    throw new RuntimeException('Server meldet: '.mb_substr($rest, 0, 200));
                }

                return $out;
            }
            // Literal: Zeile endet mit {n}, danach folgen genau n Byte
            while (preg_match('/\{(\d+)\}$/', $line, $m)) {
                $data = $this->readBytes((int) $m[1]);
                if ($literals) {
                    $out[] = ['literal' => $data];
                }
                $line = $this->readLine();
            }
            $out[] = $line;
        }
    }

    private function write(string $data): void
    {
        $len = strlen($data);
        $done = 0;
        while ($done < $len) {
            $n = @fwrite($this->stream, substr($data, $done));
            if ($n === false || $n === 0) {
                throw new RuntimeException('Verbindung zum Posteingangsserver unterbrochen.');
            }
            $done += $n;
        }
    }

    private function readLine(): string
    {
        $line = @fgets($this->stream);
        if ($line === false) {
            $meta = stream_get_meta_data($this->stream);
            throw new RuntimeException(! empty($meta['timed_out']) ? 'Der Posteingangsserver antwortet nicht (Zeitüberschreitung).' : 'Verbindung zum Posteingangsserver unterbrochen.');
        }

        return rtrim($line, "\r\n");
    }

    private function readBytes(int $n): string
    {
        $buf = '';
        while (strlen($buf) < $n) {
            $chunk = @fread($this->stream, min(65536, $n - strlen($buf)));
            if ($chunk === false || $chunk === '') {
                $meta = stream_get_meta_data($this->stream);
                if (! empty($meta['timed_out']) || feof($this->stream)) {
                    throw new RuntimeException('Verbindung beim Laden einer E-Mail unterbrochen.');
                }

                continue;
            }
            $buf .= $chunk;
        }

        return $buf;
    }
}
