<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Dashboard\Layout;
use Illuminate\Console\Command;

/** Selbsttest Paket 20 (Farben auf der Übersicht). */
class FarbenSelbsttest extends Command
{
    protected $signature = 'farben:selbsttest';

    protected $description = 'Selbsttest Farben auf der Übersicht (Paket 20)';

    public function handle(): int
    {
        $user = User::query()->orderBy('id')->first();
        $colorsOk = collect(Layout::BLOCKS)->keys()->every(fn ($k) => isset(Layout::COLORS[Layout::DEFAULT_COLORS[$k] ?? 'x']));
        $checks = [
            'Farben vollständig' => $colorsOk && collect(Layout::MONEY_TILES)->every(fn ($c) => Layout::style($c) !== ''),
            'Ansichten' => view()->exists('dashboard-layout') && view()->exists('dashboard-blocks.money'),
            'Einstellungen lesen' => $user === null || collect(Layout::blocks($user))->every(fn ($b) => isset($b['color'], $b['style'])),
        ];
        $this->line('Selbsttest Paket 20 (Farben)');
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
