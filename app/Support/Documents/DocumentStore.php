<?php

namespace App\Support\Documents;

use RuntimeException;

/**
 * Geschützter Dateispeicher für Belege.
 *
 * - liegt unter storage/app/documents, also außerhalb des öffentlichen Ordners
 * - Dateinamen sind zufällig, der Originalname steht nur in der Datenbank
 * - jede Datei wird mit AES-256-GCM verschlüsselt (Schlüssel wird aus APP_KEY abgeleitet),
 *   die Dateien sind also auch in den Sicherungen nicht im Klartext lesbar
 * - eine Datei wird nie überschrieben oder geändert
 */
class DocumentStore
{
    private const MAGIC = 'CRMD1';

    private const PATTERN = '#^\d{4}/\d{2}/[a-f0-9]{40}\.dat$#';

    private static ?string $root = null;

    /** Nur für Tests: anderen Speicherort verwenden. */
    public static function useRoot(?string $path): void
    {
        self::$root = $path === null ? null : rtrim($path, '/');
    }

    public static function root(): string
    {
        return self::$root ?? storage_path('app/documents');
    }

    /** Speichert den Inhalt und gibt den relativen Pfad zurück. */
    public static function put(string $plain): string
    {
        $relative = date('Y').'/'.date('m').'/'.bin2hex(random_bytes(20)).'.dat';
        $full = self::root().'/'.$relative;
        self::ensureDirectory(dirname($full));

        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag, self::aad($relative));
        if ($cipher === false || strlen($tag) !== 16) {
            throw new RuntimeException('Die Datei konnte nicht verschlüsselt werden.');
        }

        $tmp = $full.'.part';
        if (file_put_contents($tmp, self::MAGIC.$iv.$tag.$cipher, LOCK_EX) === false) {
            @unlink($tmp);
            throw new RuntimeException('Die Datei konnte nicht gespeichert werden. Ist der Speicher beschreibbar?');
        }
        @chmod($tmp, 0644);

        if (! rename($tmp, $full)) {
            @unlink($tmp);
            throw new RuntimeException('Die Datei konnte nicht abgelegt werden.');
        }

        return $relative;
    }

    /** Liest und entschlüsselt eine Datei. */
    public static function get(string $relative): string
    {
        self::assertPath($relative);
        $full = self::root().'/'.$relative;

        $raw = is_file($full) ? file_get_contents($full) : false;
        if ($raw === false) {
            throw new RuntimeException('Die Datei fehlt im Speicher.');
        }

        $head = strlen(self::MAGIC);
        if (strlen($raw) < $head + 28 || substr($raw, 0, $head) !== self::MAGIC) {
            throw new RuntimeException('Die Datei ist beschädigt.');
        }

        $iv = substr($raw, $head, 12);
        $tag = substr($raw, $head + 12, 16);
        $cipher = substr($raw, $head + 28);

        $plain = openssl_decrypt($cipher, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag, self::aad($relative));
        if ($plain === false) {
            throw new RuntimeException('Die Datei ist beschädigt oder der Schlüssel passt nicht.');
        }

        return $plain;
    }

    public static function exists(string $relative): bool
    {
        return preg_match(self::PATTERN, $relative) === 1 && is_file(self::root().'/'.$relative);
    }

    /** Nur zum Aufräumen, wenn das Speichern eines Belegs scheitert. */
    public static function forget(string $relative): void
    {
        if (preg_match(self::PATTERN, $relative) === 1) {
            @unlink(self::root().'/'.$relative);
        }
    }

    public static function isWritable(): bool
    {
        try {
            self::ensureDirectory(self::root());

            return is_writable(self::root());
        } catch (RuntimeException) {
            return false;
        }
    }

    private static function assertPath(string $relative): void
    {
        if (preg_match(self::PATTERN, $relative) !== 1) {
            throw new RuntimeException('Ungültiger Dateipfad.');
        }
    }

    private static function aad(string $relative): string
    {
        return self::MAGIC.'|'.$relative;
    }

    private static function key(): string
    {
        $key = (string) config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $key = (string) base64_decode(substr($key, 7), true);
        }
        if ($key === '') {
            throw new RuntimeException('APP_KEY fehlt, Dateien können nicht ver- oder entschlüsselt werden.');
        }

        return hash_hkdf('sha256', $key, 32, 'crm-documents-v1');
    }

    private static function ensureDirectory(string $dir): void
    {
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException('Der Ordner für Belege konnte nicht angelegt werden.');
        }

        // Zusätzlicher Schutz, falls der Ordner jemals versehentlich öffentlich erreichbar wäre.
        $guard = self::root().'/.htaccess';
        if (! is_file($guard) && is_dir(self::root())) {
            @file_put_contents($guard, "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
            @chmod($guard, 0644);
        }
    }
}
