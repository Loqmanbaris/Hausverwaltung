<?php

namespace App\Console\Commands;

use App\Support\Handover\HandoverService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/** Selbsttest Paket 13 (Übergabeprotokoll). */
class UebergabeSelbsttest extends Command
{
    protected $signature = 'uebergabe:selbsttest';

    protected $description = 'Selbsttest Übergabeprotokoll (Paket 13)';

    public function handle(): int
    {
        $checks = [
            'Tabellen' => Schema::hasTable('handover_protocols') && Schema::hasTable('handover_photos') && Schema::hasTable('handover_readings'),
            'Seiten' => Route::has('handover.store') && Route::has('handover.edit') && Route::has('handover.sign') && Route::has('handover.print'),
            'Ansichten' => view()->exists('handover.edit') && view()->exists('handover.print') && view()->exists('handover.sign'),
            'Prüfpunkte' => count(HandoverService::itemsFor('Bad')) > count(HandoverService::BASE_ITEMS) && count(HandoverService::STATES) === 4,
            'Bildbearbeitung (GD)' => function_exists('imagecreatefromstring'),
        ];
        $this->line('Selbsttest Paket 13 (Übergabeprotokoll)');
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
