<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Geheimer Schlüssel in der Adresse für den Cronjob bei IONOS.
 * IONOS kann nur eine Webadresse aufrufen, deshalb steckt der Schlüssel in der Adresse selbst.
 */
class CronToken
{
    public static function get(): ?string
    {
        return Settings::get('cron_token');
    }

    /** Liefert den Schlüssel und legt ihn beim ersten Mal an. */
    public static function ensure(): string
    {
        $token = self::get();
        if ($token === null) {
            $token = Str::random(48);
            Settings::putMany(['cron_token' => $token]);
        }

        return $token;
    }

    public static function url(?string $token = null): string
    {
        return rtrim((string) config('app.url'), '/').'/cron/'.($token ?? self::ensure());
    }
}
