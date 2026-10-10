<?php

namespace App\Console\Commands;

use App\Support\Setup\Einrichtung;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

/** Selbsttest Paket 25 (Einrichtung prüfen). */
class EinrichtungSelbsttest extends Command
{
    protected $signature = 'einrichtung:selbsttest';

    protected $description = 'Selbsttest Einrichtung prüfen (Paket 25)';

    public function handle(): int
    {
        $ok = false;
        try {
            $ok = count(Einrichtung::checks()) > 15 && Einrichtung::summary()['total'] > 0;
        } catch (\Throwable $e) {
            $this->line('  '.$e->getMessage());
        }
        $checks = ['Seite' => Route::has('setup.index') && view()->exists('setup.index'), 'Prüfungen laufen' => $ok];
        $this->line('Selbsttest Paket 25 (Einrichtung prüfen)');
        $failed = 0;
        foreach ($checks as $label => $pass) {
            $failed += $pass ? 0 : 1;
            $this->line(($pass ? '  [OK]     ' : '  [FEHLER] ').$label);
        }
        if ($failed > 0) {
            $this->error($failed.' Prüfung(en) fehlgeschlagen.');

            return self::FAILURE;
        }
        $this->info('Alle Prüfungen in Ordnung.');

        return self::SUCCESS;
    }
}
