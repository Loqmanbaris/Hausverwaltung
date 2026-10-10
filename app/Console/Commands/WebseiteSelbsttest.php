<?php

namespace App\Console\Commands;

use App\Support\Listings\Listings;
use App\Support\Listings\Occupancy;
use App\Support\Listings\PhotoStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** Selbsttest Paket 11 (Webseite und Inserate). */
class WebseiteSelbsttest extends Command
{
    protected $signature = 'webseite:selbsttest';

    protected $description = 'Selbsttest Webseite und Inserate (Paket 11)';

    public function handle(): int
    {
        $calendar = false;
        try {
            $calendar = count(Occupancy::calendar(0, date('Y-m'), 2)) === 2;
        } catch (Throwable) {
        }
        $dir = PhotoStore::root();
        $checks = [
            'Tabellen' => Schema::hasTable('listings') && Schema::hasTable('listing_photos') && Schema::hasTable('unit_bookings') && Schema::hasTable('inquiries'),
            'Öffentliche Seiten' => Route::has('public.wohnen') && Route::has('public.montage') && Route::has('public.listing') && Route::has('public.send') && Route::has('public.photo'),
            'Verwaltung' => Route::has('listings.index') && Route::has('listings.portal') && Route::has('bookings.index') && Route::has('inquiries.index'),
            'Vorlagen' => view()->exists('public.listing') && view()->exists('listings.form') && view()->exists('bookings.index'),
            'Belegungskalender' => $calendar,
            'Merkmale' => count(Listings::FEATURES) >= 10,
            'Fotoordner beschreibbar' => (is_dir($dir) || @mkdir($dir, 0755, true)) && is_writable($dir),
        ];
        $this->line('Selbsttest Paket 11 (Webseite und Inserate)');
        $failed = 0;
        foreach ($checks as $label => $ok) {
            $failed += $ok ? 0 : 1;
            $this->line(($ok ? '  [OK]     ' : '  [FEHLER] ').$label);
        }
        $this->line(PhotoStore::available() ? '  [OK]     Bildbearbeitung (GD)' : '  [HINWEIS] Bildbearbeitung (GD) fehlt, Fotos können nicht hochgeladen werden');
        if ($failed > 0) {
            $this->error($failed.' Prüfung(en) fehlgeschlagen.');

            return self::FAILURE;
        }
        $this->info('Alle Prüfungen in Ordnung.');

        return self::SUCCESS;
    }
}
