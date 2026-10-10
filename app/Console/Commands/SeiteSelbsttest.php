<?php

namespace App\Console\Commands;

use App\Support\Site\SiteFiles;
use App\Support\Site\SiteSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/** Selbsttest Paket 16 (Gestaltung der Webseite). */
class SeiteSelbsttest extends Command
{
    protected $signature = 'seite:selbsttest';

    protected $description = 'Selbsttest Webseite gestalten (Paket 16)';

    public function handle(): int
    {
        $dir = SiteFiles::root();
        $checks = [
            'Tabellen' => Schema::hasTable('site_objects') && Schema::hasTable('site_assets') && Schema::hasColumn('inquiries', 'topic'),
            'Seiten' => Route::has('site.services') && Route::has('site.objects') && Route::has('site.tenants') && Route::has('site.edit') && Route::has('site.asset'),
            'Ansichten' => view()->exists('site.edit') && view()->exists('site.tenants') && view()->exists('public.home'),
            'Einstellungen' => preg_match('/^#[0-9a-f]{6}$/', SiteSettings::accent()) === 1 && count(SiteSettings::services()) > 0,
            'Ordner beschreibbar' => (is_dir($dir) || @mkdir($dir, 0755, true)) && is_writable($dir),
        ];
        $this->line('Selbsttest Paket 16 (Webseite gestalten)');
        $failed = 0;
        foreach ($checks as $label => $ok) {
            $failed += $ok ? 0 : 1;
            $this->line(($ok ? '  [OK]     ' : '  [FEHLER] ').$label);
        }
        if ($failed > 0) {
            $this->error($failed.' Prüfung(en) fehlgeschlagen.');

            return self::FAILURE;
        }
        $this->info('Alle Prüfungen in Ordnung.');

        return self::SUCCESS;
    }
}
