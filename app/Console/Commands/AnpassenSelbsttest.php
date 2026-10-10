<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Dashboard\Cockpit;
use App\Support\Dashboard\Layout;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/** Selbsttest Paket 19 (Übersicht selbst gestalten). */
class AnpassenSelbsttest extends Command
{
    protected $signature = 'anpassen:selbsttest';

    protected $description = 'Selbsttest Übersicht selbst gestalten (Paket 19)';

    public function handle(): int
    {
        $user = User::query()->where('role', 'admin')->orderBy('id')->first();
        $ok = true;
        $error = null;
        if ($user) {
            try {
                $ok = count(Layout::blocks($user)) === count(Layout::BLOCKS) && is_array(Cockpit::build($user)['expenses'] ?? []);
            } catch (\Throwable $e) {
                $ok = false;
                $error = $e->getMessage();
            }
        }
        $views = ['dashboard', 'dashboard-layout'];
        foreach (array_keys(Layout::BLOCKS) as $k) {
            $views[] = 'dashboard-blocks.'.$k;
        }
        $checks = [
            'Spalten' => Schema::hasColumn('users', 'dashboard_layout') && Schema::hasColumn('users', 'dashboard_note'),
            'Seiten' => Route::has('dashboard.layout') && Route::has('dashboard.layout.update') && Route::has('dashboard.layout.reset') && Route::has('dashboard.note'),
            'Ansichten' => collect($views)->every(fn ($v) => view()->exists($v)),
            'Schnellzugriff-Ziele' => collect(Layout::QUICK)->every(fn ($q) => Route::has($q[1])),
            'Einstellungen lesen' => $ok,
        ];
        $this->line('Selbsttest Paket 19 (Übersicht anpassen)');
        $failed = 0;
        foreach ($checks as $label => $pass) {
            $failed += $pass ? 0 : 1;
            $this->line(($pass ? '  [OK]     ' : '  [FEHLER] ').$label);
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
