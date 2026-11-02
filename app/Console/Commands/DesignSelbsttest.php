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

        // „@php(…)“ vor einem „@php … @endphp“-Block wird von Blade zu einem kaputten Block verschmolzen (Seite bricht mit 500 ab)
        $broken = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)) as $f) {
            if (str_ends_with((string) $f, '.blade.php')) {
                try {
                    $php = Blade::compileString((string) file_get_contents((string) $f));
                } catch (\Throwable) {
                    $php = '@php';
                }
                if (preg_match('/<\?php\(|@php\b|@endphp/', $php) === 1) {
                    $broken[] = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', (string) $f);
                }
            }
        }

        $checks = [
            'Vorlagen sauber übersetzt'.($broken !== [] ? ' (fehlerhaft: '.implode(', ', $broken).')' : '') => $broken === [],
            'Ansichten vorhanden' => view()->exists('layouts.app') && view()->exists('layouts.guest') && view()->exists('partials.nav-main'),
            'Ansichten lesbar' => $compiles('layouts.app') && $compiles('layouts.guest') && $compiles('partials.nav-main'),
            'Kontoauszüge mit Reitern' => $compiles('partials.bank-tabs') && $compiles('bank.index') && $compiles('bank.transactions') && $compiles('bank.expenses'),
            'Einstellungen als Kacheln' => \Illuminate\Support\Facades\Route::has('profile.settings') && view()->exists('profile.settings') && $compiles('profile.settings'),
            'Neues Seitenmenü' => str_contains((string) @file_get_contents(resource_path('views/layouts/app.blade.php')), 'partials.nav-main'),
            'Logo „Haus mit B“' => view()->exists('partials.logo-mark') && $compiles('partials.logo-mark'),
            'Briefkopf mit Logo' => $compiles('partials.briefkopf') && $compiles('partials.brieffuss')
                && \App\Support\Briefkopf::daten(['name' => 'A', 'vat' => 'DE1', 'iban' => 'de89 3704'])['vat_id'] === 'DE1'
                && \App\Support\Briefkopf::daten(['name' => 'A', 'iban' => 'de89 3704'])['iban'] === 'DE893704',
            'Seitenmenü einklappbar' => str_contains((string) @file_get_contents(resource_path('views/layouts/app.blade.php')), 'data-sb-toggle'),
            'Menüpunkte ('.count($m[1]).')' => count($m[1]) >= 12 && $missing === [],   // seit 02.11.2026 schlanker, vieles unter Einstellungen
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
