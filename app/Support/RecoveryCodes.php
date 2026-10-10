<?php

namespace App\Support;

/**
 * Wiederherstellungscodes für den Fall, dass das Handy nicht verfügbar ist.
 * Gespeichert wird nur ein Fingerabdruck (HMAC), nie der Code selbst.
 */
class RecoveryCodes
{
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    /** @return array<int, string> Klartext-Codes im Format abcde-fghij */
    public static function generate(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $raw = '';
            for ($j = 0; $j < 10; $j++) {
                $raw .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $codes[] = substr($raw, 0, 5).'-'.substr($raw, 5);
        }

        return $codes;
    }

    public static function normalize(string $code): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', strtolower($code));
    }

    public static function fingerprint(string $code): string
    {
        return hash_hmac('sha256', self::normalize($code), (string) config('app.key'));
    }
}
