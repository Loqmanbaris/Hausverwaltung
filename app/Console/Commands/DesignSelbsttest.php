<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

/** Selbsttest Paket 17 (neue Gestaltung des CRM). */
class DesignSelbsttest extends Command
{
    protected $signature = 'design:selbsttest';

    protected $description = 'Selbsttest neue Gestaltung des CRM (Paket 17)';

    public function handle(): int
    {
        $compiles = function (string $view): bool {
            try {
                $file = view()->getFinder()->find($view);
                Blade::compileString((string) file_get_contents($file));

                return true;
            } catch (\Throwable) {
                return false;
            }
        };
        $nav = (string) @file_get_contents(resource_path('views/partials/nav-main.blade.php'));
        preg_match_all("/\\['[^']+', '([a-z.-]+)', \\[/", $nav, $m);
        $missing = array_filter($m[1], fn ($r) => ! Route::has($r));

        $checks = [
            'Ansichten vorhanden' => view()->exists('layouts.app') && view()->exists('layouts.guest') && view()->exists('partials.nav-main'),
            'Ansichten lesbar' => $compiles('layouts.app') && $compiles('layouts.guest') && $compiles('partials.nav-main'),
            'Einstellungen als Kacheln' => \Illuminate\Support\Facades\Route::has('profile.settings') && view()->exists('profile.settings') && $compiles('profile.settings'),
            'Neues Seitenmenü' => str_contains((string) @file_get_contents(resource_path('views/layouts/app.blade.php')), 'partials.nav-main'),
            'Menüpunkte ('.count($m[1]).')' => count($m[1]) > 20 && $missing === [],
        ];
        $this->line('Selbsttest Paket 17 (CRM neu gestaltet)');
        $failed = 0;
        foreach ($checks as $label => $ok) {
            $failed += $ok ? 0 : 1;
            $this->line(($ok ? '  [OK]     ' : '  [FEHLER] ').$label);
        }
        if ($missing !== []) {
            $this->line('  Fehlende Seiten: '.implode(', ', $missing));
        }
        if ($failed > 0) {
            $this->error($failed.' Prüfung(en) fehlgeschlagen.');

            return self::FAILURE;
        }
        $this->info('Alle Prüfungen in Ordnung.');

        return self::SUCCESS;
    }
}
