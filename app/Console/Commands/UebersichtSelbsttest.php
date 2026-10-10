<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Dashboard\Cockpit;
use Illuminate\Console\Command;

/** Selbsttest Paket 18 (neue Übersicht). */
class UebersichtSelbsttest extends Command
{
    protected $signature = 'uebersicht:selbsttest';

    protected $description = 'Selbsttest neue Übersicht (Paket 18)';

    public function handle(): int
    {
        $user = User::query()->where('role', 'admin')->orderBy('id')->first();
        $data = null;
        $error = null;
        if ($user) {
            try {
                $data = Cockpit::build($user);
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }
        $checks = [
            'Ansichten vorhanden' => view()->exists('partials.cockpit') && view()->exists('partials.cockpit-chart') && view()->exists('dashboard'),
            'Zahlen berechnen' => $user === null || ($data !== null && is_array($data['todo']) && is_array($data['dates']) && is_array($data['chart'])),
        ];
        $this->line('Selbsttest Paket 18 (Übersicht)');
        $failed = 0;
        foreach ($checks as $label => $ok) {
            $failed += $ok ? 0 : 1;
            $this->line(($ok ? '  [OK]     ' : '  [FEHLER] ').$label);
        }
        if ($error) {
            $this->line('  '.$error);
        }
        if ($failed > 0) {
            $this->error($failed.' Prüfung(en) fehlgeschlagen.');

            return self::FAILURE;
        }
        $this->info('Alle Prüfungen in Ordnung.');

        return self::SUCCESS;
    }
}
