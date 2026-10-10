<?php

namespace App\Support;

/**
 * Zeitbasierte Einmalcodes (TOTP, RFC 6238) für Authenticator-Apps.
 * Sechsstellig, 30 Sekunden, SHA-1 (Standard aller gängigen Apps).
 */
class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    public static function base32Encode(string $data): string
    {
        $bits = '';
        foreach (str_split($data) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    public static function base32Decode(string $text): string
    {
        $text = strtoupper((string) preg_replace('/[^A-Za-z2-7]/', '', $text));

        $bits = '';
        foreach (str_split($text) as $char) {
            $bits .= str_pad(decbin(strpos(self::ALPHABET, $char)), 5, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }

    public static function codeAt(string $secret, int $step): string
    {
        $key = self::base32Decode($secret);
        $counter = pack('N2', 0, $step);
        $hash = hash_hmac('sha1', $counter, $key, true);

        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    public static function currentStep(?int $time = null): int
    {
        return intdiv($time ?? time(), 30);
    }

    /**
     * Prüft den Code. Gibt den passenden Zeitschritt zurück oder null.
     * Schritte bis einschließlich $lastUsedStep werden abgelehnt,
     * damit ein abgefangener Code nicht ein zweites Mal funktioniert.
     */
    public static function verify(string $secret, string $code, int $lastUsedStep = 0, int $window = 1, ?int $time = null): ?int
    {
        $code = (string) preg_replace('/\s+/', '', $code);
        if (! preg_match('/^\d{6}$/', $code)) {
            return null;
        }

        $now = self::currentStep($time);
        for ($i = -$window; $i <= $window; $i++) {
            $step = $now + $i;
            if ($step <= $lastUsedStep) {
                continue;
            }
            if (hash_equals(self::codeAt($secret, $step), $code)) {
                return $step;
            }
        }

        return null;
    }

    public static function uri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer.':'.$account)
            .'?secret='.$secret
            .'&issuer='.rawurlencode($issuer)
            .'&algorithm=SHA1&digits=6&period=30';
    }
}
