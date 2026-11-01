<?php

namespace App\Support\Mail;

use App\Support\Settings;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/** Zugangsdaten für den E-Mail-Versand (Paket 21) und den Empfang (Posteingang). Das Passwort wird verschlüsselt gespeichert. */
class MailSettings
{
    public const DEFAULTS = [
        'mail_host' => 'smtp.ionos.de',
        'mail_port' => '587',
        'mail_username' => '',
        'mail_from_address' => '',
        'mail_from_name' => '',
        'mail_bcc' => '',
        'mail_signature' => '',
        'mail_notify' => '1',
        'imap_enabled' => '1',
        'imap_host' => 'imap.ionos.de',
        'imap_port' => '993',
    ];

    public static function get(string $key): string
    {
        return (string) Settings::get($key, self::DEFAULTS[$key] ?? '');
    }

    public static function password(): ?string
    {
        $enc = Settings::get('mail_password_enc');
        if ($enc === null) {
            return null;
        }
        try {
            return Crypt::decryptString($enc);
        } catch (Throwable) {
            return null;
        }
    }

    public static function hasPassword(): bool
    {
        return self::password() !== null;
    }

    public static function configured(): bool
    {
        return self::get('mail_host') !== '' && self::get('mail_username') !== '' && self::get('mail_from_address') !== '' && self::hasPassword();
    }

    /** E-Mail-Empfang (Posteingang): gleiche Zugangsdaten, eigener Server. */
    public static function receiveConfigured(): bool
    {
        return self::get('imap_enabled') === '1' && self::get('imap_host') !== '' && self::get('mail_username') !== '' && self::hasPassword();
    }

    /** @param array<string, ?string> $values */
    public static function save(array $values, ?string $password): void
    {
        $put = [];
        foreach (array_keys(self::DEFAULTS) as $k) {
            if (array_key_exists($k, $values)) {
                $put[$k] = $values[$k] === null ? null : trim((string) $values[$k]);
            }
        }
        if ($password !== null && $password !== '') {
            $put['mail_password_enc'] = Crypt::encryptString($password);
        }
        Settings::putMany($put);
    }

    public static function fromName(): string
    {
        return self::get('mail_from_name') ?: (string) Settings::get('imprint_name', config('app.name'));
    }

    /** Adresse für Blindkopie und Hinweise (leer = Absender). */
    public static function copyAddress(): string
    {
        return self::get('mail_bcc') ?: self::get('mail_from_address');
    }
}
