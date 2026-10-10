<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/** Selbsttest Paket 24 (Wartung und Handwerker). */
class TechnikSelbsttest extends Command
{
    protected $signature = 'technik:selbsttest';

    protected $description = 'Selbsttest Wartung und Handwerker (Paket 24)';

    public function handle(): int
    {
        $checks = [
            'Tabellen' => Schema::hasTable('maintenance_tasks') && Schema::hasTable('maintenance_logs') && Schema::hasTable('work_orders') && Schema::hasTable('work_order_photos') && Schema::hasTable('work_order_notes'),
            'Seiten' => Route::has('workorders.index') && Route::has('maintenance.index') && Route::has('vendor.order') && Route::has('workorders.from-handover'),
            'Ansichten' => view()->exists('workorders.show') && view()->exists('workorders.vendor') && view()->exists('workorders.print') && view()->exists('maintenance.index') && view()->exists('dashboard-blocks.technik'),
            'Bildbearbeitung (GD)' => function_exists('imagecreatefromstring') && function_exists('imagejpeg'),
        ];
        $this->line('Selbsttest Paket 24 (Wartung und Handwerker)');
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
