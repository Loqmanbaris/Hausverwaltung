<?php

namespace App\Console\Commands;

use App\Support\Interessenten\Interessenten;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/** Selbsttest Paket 31 (Interessenten). */
class InteressentenSelbsttest extends Command
{
    protected $signature = 'interessenten:selbsttest';

    protected $description = 'Selbsttest Interessenten und Selbstauskunft (Paket 31)';

    public function handle(): int
    {
        [$ok, $err] = Interessenten::clean(['adults' => '2', 'children' => '1', 'net_income' => '3.200,50', 'move_in' => '2026-12-01', 'credit_ok' => 'ja', 'occupation' => 'Lehrer']);
        [, $bad] = Interessenten::clean(['adults' => '99', 'net_income' => 'viel', 'move_in' => '2026-13-45']);
        $checks = [
            'Tabellen' => Schema::hasTable('applicants') && Schema::hasTable('applicant_viewings') && Schema::hasTable('applicant_disclosures'),
            'Namen trennen' => Interessenten::splitName('Max Peter Mustermann') === ['Max Peter', 'Mustermann'] && Interessenten::splitName('Mustermann') === [null, 'Mustermann'],
            'Angaben prüfen' => $err === [] && $ok['adults'] === 2 && $ok['net_income'] === '3200.50' && $ok['credit_ok'] === 'ja',
            'Falsche Angaben abweisen' => isset($bad['adults'], $bad['net_income'], $bad['move_in']),
            'Link-Schlüssel' => Interessenten::byToken('zu-kurz') === null && Interessenten::byToken(str_repeat('a', 48)) === null,
            'Seiten' => Route::has('applicants.index') && Route::has('applicants.show') && Route::has('disclosure.form') && Route::has('disclosure.submit'),
            'Ansichten' => view()->exists('applicants.index') && view()->exists('applicants.show') && view()->exists('applicants.public') && view()->exists('applicants.letter'),
        ];
        $this->line('Selbsttest Paket 31 (Interessenten)');
        $failed = 0;
        foreach ($checks as $label => $okc) {
            $failed += $okc ? 0 : 1;
            $this->line(($okc ? '  [OK]     ' : '  [FEHLER] ').$label);
        }
        if ($failed > 0) {
            $this->error($failed.' Prüfung(en) fehlgeschlagen.');

            return self::FAILURE;
        }
        $this->info('Alle Prüfungen in Ordnung.');

        return self::SUCCESS;
    }
}
