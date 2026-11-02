<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // FinTS-Bibliothek (Paket 47) liegt unter lib/php-fints, weil auf dem Server kein composer verfügbar ist
        spl_autoload_register(static function (string $class): void {
            if (str_starts_with($class, 'Fhp\\')) {
                $file = base_path('lib/php-fints/src/'.str_replace('\\', '/', substr($class, 4)).'.php');
                if (is_file($file)) {
                    require $file;
                }
            }
        });
    }

    public function boot(): void
    {
        // Alle Links immer mit https erzeugen.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
