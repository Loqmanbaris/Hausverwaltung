<?php

namespace App\Support\Backup;

use RuntimeException;

/**
 * Verschlüsselt Sicherungsdateien so, dass der Server sie nur verschlüsseln, aber nie öffnen kann.
 *
 * Aufbau der Datei (alle Zahlen Big-Endian):
 *   "BKP1" | Länge der Dateiendung (1 Byte) | Endung (z. B. "zip") |
 *   Länge des verpackten Schlüssels (2 Bytes) | mit RSA-OAEP (SHA-1) verpackter AES-Schlüssel |
 *   8 Bytes Basis für die Startwerte | Blockgröße (4 Bytes) |
 *   danach Blöcke: je Klartextblock der Chiffretext plus 16 Bytes Prüfsumme (AES-256-GCM).
 *
 * Startwert eines Blocks = 8 Bytes Basis + 4 Bytes Blocknummer.
 * Zusätzlich geprüfte Daten: 0x01 beim letzten Block, sonst 0x00 (erkennt abgeschnittene Dateien).
 * Die Browser-Seite „sicherungs-werkzeug.html“ öffnet dieses Format mit WebCrypto.
 */
class Crypto
{
    public const MAGIC = 'BKP1';

    public const CHUNK = 4194304; // 4 MiB

    public static function encryptFile(string $source, string $target, string $publicKeyPem, string $extension): void
    {
        if ($extension === '' || strlen($extension) > 16) {
            throw new RuntimeException('Ungültige Dateiendung für die Sicherung.');
        }

        $publicKey = openssl_pkey_get_public($publicKeyPem);
        if ($publicKey === false) {
            throw new RuntimeException('Der öffentliche Schlüssel ist ungültig.');
        }

        $aesKey = random_bytes(32);
        $wrapped = '';
        if (! openssl_public_encrypt($aesKey, $wrapped, $publicKey, OPENSSL_PKCS1_OAEP_PADDING)) {
            throw new RuntimeException('Der Schlüssel konnte nicht verpackt werden.');
        }

        $base = random_bytes(8);
        $in = @fopen($source, 'rb');
        $out = @fopen($target, 'wb');

        if ($in === false || $out === false) {
            if (is_resource($in)) {
                fclose($in);
            }
            if (is_resource($out)) {
                fclose($out);
            }
            throw new RuntimeException('Dateien für die Verschlüsselung konnten nicht geöffnet werden.');
        }

        try {
            self::write($out, self::MAGIC.chr(strlen($extension)).$extension
                .pack('n', strlen($wrapped)).$wrapped.$base.pack('N', self::CHUNK));

            $index = 0;
            $current = self::readBytes($in, self::CHUNK);

            while (true) {
                $next = self::readBytes($in, self::CHUNK);
                $last = ($next === '');
                $tag = '';

                $cipher = openssl_encrypt(
                    $current,
                    'aes-256-gcm',
                    $aesKey,
                    OPENSSL_RAW_DATA,
                    $base.pack('N', $index),
                    $tag,
                    $last ? "\x01" : "\x00",
                    16
                );

                if ($cipher === false || strlen($tag) !== 16) {
                    throw new RuntimeException('Verschlüsselung fehlgeschlagen.');
                }

                self::write($out, $cipher.$tag);

                if ($last) {
                    break;
                }

                $current = $next;
                $index++;
            }
        } finally {
            fclose($in);
            fclose($out);
        }
    }

    /** Öffnet eine Sicherung mit dem privaten Schlüssel. Gibt die Dateiendung des Inhalts zurück. */
    public static function decryptFile(string $source, string $target, string $privateKeyPem): string
    {
        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if ($privateKey === false) {
            throw new RuntimeException('Der private Schlüssel ist ungültig.');
        }

        $in = @fopen($source, 'rb');
        $out = @fopen($target, 'wb');
        if ($in === false || $out === false) {
            throw new RuntimeException('Dateien konnten nicht geöffnet werden.');
        }

        try {
            if (self::readBytes($in, 4) !== self::MAGIC) {
                throw new RuntimeException('Das ist keine Sicherungsdatei.');
            }

            $extension = self::readBytes($in, ord(self::readBytes($in, 1)));
            $wrappedLength = unpack('n', self::readBytes($in, 2))[1];
            $wrapped = self::readBytes($in, $wrappedLength);
            $base = self::readBytes($in, 8);
            $chunk = unpack('N', self::readBytes($in, 4))[1];

            $aesKey = '';
            if (! openssl_private_decrypt($wrapped, $aesKey, $privateKey, OPENSSL_PKCS1_OAEP_PADDING)) {
                throw new RuntimeException('Der Schlüssel passt nicht zu dieser Sicherung.');
            }

            $size = $chunk + 16;
            $index = 0;
            $current = self::readBytes($in, $size);

            while (true) {
                $next = self::readBytes($in, $size);
                $last = ($next === '');

                if (strlen($current) < 16) {
                    throw new RuntimeException('Die Sicherungsdatei ist unvollständig.');
                }

                $plain = openssl_decrypt(
                    substr($current, 0, -16),
                    'aes-256-gcm',
                    $aesKey,
                    OPENSSL_RAW_DATA,
                    $base.pack('N', $index),
                    substr($current, -16),
                    $last ? "\x01" : "\x00"
                );

                if ($plain === false) {
                    throw new RuntimeException('Die Sicherungsdatei ist beschädigt oder unvollständig.');
                }

                self::write($out, $plain);

                if ($last) {
                    break;
                }

                $current = $next;
                $index++;
            }

            return $extension;
        } finally {
            fclose($in);
            fclose($out);
        }
    }

    private static function readBytes($handle, int $length): string
    {
        $data = '';
        while (strlen($data) < $length && ! feof($handle)) {
            $part = fread($handle, $length - strlen($data));
            if ($part === false || $part === '') {
                break;
            }
            $data .= $part;
        }

        return $data;
    }

    private static function write($handle, string $data): void
    {
        if ($data !== '' && fwrite($handle, $data) !== strlen($data)) {
            throw new RuntimeException('Schreiben fehlgeschlagen (Speicherplatz voll?).');
        }
    }
}
