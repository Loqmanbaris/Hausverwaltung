<?php

namespace App\Support\Updates;

use RuntimeException;

/**
 * Ein signiertes Update-Paket (Datei *.crmupdate). Aufbau:
 *   {"format":"crm-update-1","key":"…","payload":"<base64 von gzip(JSON)>","signature":"<base64 RSA-SHA256 über payload>"}
 * Die Signatur wird mit dem öffentlichen Schlüssel aus UpdateKey geprüft, bevor irgendetwas gelesen wird.
 */
class UpdatePackage
{
    public const MAX_BYTES = 20 * 1024 * 1024;

    /** Nur diese Ordner dürfen durch ein Paket verändert werden. */
    private const ALLOWED_PREFIXES = ['app/', 'resources/views/', 'resources/css/', 'resources/js/', 'routes/', 'database/migrations/', 'lang/', 'config/'];

    private const ALLOWED_SUFFIXES = ['.php', '.css', '.js', '.html'];

    /** Diese Dateien rührt ein Paket nie an. */
    private const FORBIDDEN = ['config/database.php', 'config/app.php', 'config/session.php'];

    public function __construct(
        public readonly string $name,
        public readonly string $title,
        public readonly string $description,
        public readonly string $created,
        /** @var array<int, array{path: string, old_sha256: ?string, sha256: string, content: string}> */
        public readonly array $files,
        /** @var string[] */
        public readonly array $migrations,
        /** @var string[] */
        public readonly array $requiresFiles,
        public readonly ?string $selftest,
        public readonly string $sha256,
    ) {
    }

    /** @throws RuntimeException mit verständlicher Meldung */
    public static function fromSigned(string $raw, string $publicPem = UpdateKey::PUBLIC_PEM): self
    {
        if ($raw === '' || strlen($raw) > self::MAX_BYTES) {
            throw new RuntimeException('Die Datei ist leer oder zu groß.');
        }
        $env = json_decode($raw, true);
        if (! is_array($env) || ($env['format'] ?? null) !== 'crm-update-1' || ! is_string($env['payload'] ?? null) || ! is_string($env['signature'] ?? null)) {
            throw new RuntimeException('Das ist kein Update-Paket für dieses System (Dateiendung .crmupdate).');
        }

        $key = openssl_pkey_get_public($publicPem);
        $signature = base64_decode($env['signature'], true);
        if ($key === false || $signature === false || openssl_verify($env['payload'], $signature, $key, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('Die Signatur des Pakets ist ungültig. Das Paket stammt nicht aus der gewohnten Quelle oder wurde verändert und wird nicht eingespielt.');
        }

        $json = @gzdecode((string) base64_decode($env['payload'], true));
        $m = is_string($json) ? json_decode($json, true) : null;
        if (! is_array($m) || ! is_string($m['name'] ?? null) || ! is_array($m['files'] ?? null)) {
            throw new RuntimeException('Der Inhalt des Pakets kann nicht gelesen werden.');
        }
        if (preg_match('/^[a-z0-9][a-z0-9._-]{1,59}$/', $m['name']) !== 1) {
            throw new RuntimeException('Der Name des Pakets ist ungültig.');
        }

        $files = [];
        foreach ($m['files'] as $f) {
            $path = (string) ($f['path'] ?? '');
            self::assertPath($path);
            $content = base64_decode((string) ($f['content_b64'] ?? ''), true);
            if ($content === false || hash('sha256', $content) !== ($f['sha256'] ?? '')) {
                throw new RuntimeException('Die Datei '.$path.' im Paket ist beschädigt.');
            }
            $old = $f['old_sha256'] ?? null;
            $files[] = ['path' => $path, 'old_sha256' => is_string($old) && $old !== '' ? $old : null, 'sha256' => $f['sha256'], 'content' => $content];
        }

        $migrations = [];
        foreach ((array) ($m['migrations'] ?? []) as $mig) {
            $mig = (string) $mig;
            if (preg_match('#^database/migrations/[0-9_]+[a-z0-9_]+\.php$#', $mig) !== 1) {
                throw new RuntimeException('Ungültige Migration im Paket: '.$mig);
            }
            $migrations[] = $mig;
        }

        $requires = [];
        foreach ((array) ($m['requires_files'] ?? []) as $r) {
            $r = (string) $r;
            self::assertPath($r, false);
            $requires[] = $r;
        }

        $selftest = isset($m['selftest']) && preg_match('/^[a-z][a-z0-9:-]{2,40}$/', (string) $m['selftest']) === 1 ? (string) $m['selftest'] : null;

        return new self(
            $m['name'],
            mb_substr((string) ($m['title'] ?? $m['name']), 0, 190),
            mb_substr((string) ($m['description'] ?? ''), 0, 5000),
            (string) ($m['created'] ?? ''),
            $files,
            $migrations,
            $requires,
            $selftest,
            hash('sha256', $raw),
        );
    }

    public static function assertPath(string $path, bool $strict = true): void
    {
        $ok = $path !== ''
            && ! str_contains($path, '..')
            && ! str_contains($path, '\\')
            && ! str_starts_with($path, '/')
            && preg_match('#^[A-Za-z0-9_./-]+$#', $path) === 1;

        if ($ok && $strict) {
            $ok = self::startsWithAny($path, self::ALLOWED_PREFIXES)
                && self::endsWithAny($path, self::ALLOWED_SUFFIXES)
                && ! in_array($path, self::FORBIDDEN, true);
        }
        if (! $ok) {
            throw new RuntimeException('Das Paket enthält eine nicht erlaubte Datei: '.$path);
        }
    }

    private static function startsWithAny(string $s, array $list): bool
    {
        foreach ($list as $p) {
            if (str_starts_with($s, $p)) {
                return true;
            }
        }

        return false;
    }

    private static function endsWithAny(string $s, array $list): bool
    {
        foreach ($list as $p) {
            if (str_ends_with($s, $p)) {
                return true;
            }
        }

        return false;
    }
}
