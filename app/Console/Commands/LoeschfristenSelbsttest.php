<?php

namespace App\Console\Commands;

use App\Support\Datenschutz\Loeschfristen;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/** Selbsttest Paket 35 (Löschfristen). */
class LoeschfristenSelbsttest extends Command
{
    protected $signature = 'loeschfristen:selbsttest';

    protected $description = 'Selbsttest Löschfristen für ehemalige Mieter (Paket 35)';

    public function handle(): int
    {
        $checks = [
            'Spalten' => Schema::hasColumn('tenants', 'retention_hold') && Schema::hasColumn('tenants', 'contact_cleaned_at') && Schema::hasColumn('tenants', 'erased_at'),
            'Fristen im erlaubten Bereich' => Loeschfristen::months() >= 1 && Loeschfristen::months() <= 120 && Loeschfristen::years() >= 1 && Loeschfristen::years() <= 30,
            'Stufe 1 enthält Ausweis und Bonität' => in_array('ausweis', Loeschfristen::STAGE1_CATEGORIES, true) && in_array('bonitaet', Loeschfristen::STAGE1_CATEGORIES, true) && ! in_array('mietvertrag', Loeschfristen::STAGE1_CATEGORIES, true),
            'Seiten' => Route::has('retention.index') && Route::has('retention.clean'),
            'Ansicht' => view()->exists('retention.index'),
        ];
        $this->line('Selbsttest Paket 35 (Löschfristen)');
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
