<?php

namespace App\Console\Commands;

use App\Support\Contracts\ContractRenderer;
use App\Support\Contracts\Vorlagen;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/** Selbsttest Paket 12 (Mietverträge erstellen und unterschreiben). */
class VertraegeSelbsttest extends Command
{
    protected $signature = 'vertraege:selbsttest';

    protected $description = 'Selbsttest Mietverträge erstellen (Paket 12)';

    public function handle(): int
    {
        $render = ContractRenderer::render([['key' => 'a', 'title' => 'T', 'text' => "(#) A {{x}}\n\n[[wenn y]](#) B[[/wenn]]\n\n(#) C"]], ['x' => '1', 'y' => '']);
        $checks = [
            'Tabellen' => Schema::hasTable('contract_templates') && Schema::hasTable('lease_contracts'),
            'Seiten' => Route::has('contracts.create') && Route::has('contracts.sign') && Route::has('contracts.print') && Route::has('contract-templates.edit') && Route::has('status.cron'),
            'Vorlagen' => count(Vorlagen::defaults('wohnen')) >= 10 && count(Vorlagen::defaults('gewerbe')) >= 6 && count(Vorlagen::defaults('montage')) >= 6,
            'Textbaustein' => $render[0]['paragraphs'] === ['(1) A 1', '(2) C'],
            'Ansichten' => view()->exists('contracts.sign') && view()->exists('contracts.print') && view()->exists('contract-templates.edit'),
        ];
        $this->line('Selbsttest Paket 12 (Mietverträge)');
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
