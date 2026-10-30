<?php

namespace App\Console\Commands;

use App\Support\Portal\Portal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/** Selbsttest Paket 36 (Mieterportal). */
class PortalSelbsttest extends Command
{
    protected $signature = 'portal:selbsttest';

    protected $description = 'Selbsttest Mieterportal (Paket 36)';

    public function handle(): int
    {
        $checks = [
            'Tabellen' => Schema::hasTable('tenant_portal_users') && Schema::hasTable('tenant_portal_tokens') && Schema::hasColumn('documents', 'shared_with_tenant'),
            'E-Mail vereinheitlichen' => Portal::normalize('  Max@Example.DE ') === 'max@example.de',
            'Zahlen lesen' => Portal::parseNumber('1.234,567') === '1234.567' && Portal::parseNumber('123,4') === '123.4' && Portal::parseNumber('1.234') === '1234' && Portal::parseNumber('1234.5') === '1234.5',
            'Falsche Zahlen abweisen' => Portal::parseNumber('abc') === null && Portal::parseNumber('') === null && Portal::parseNumber('-5') === null,
            'Link-Schlüssel' => Portal::peek('zu-kurz') === null && Portal::peek(str_repeat('a', 48)) === null,
            'Seiten' => Route::has('portal.login') && Route::has('portal.home') && Route::has('portal.enter') && Route::has('portal.admin.enable'),
            'Ansichten' => view()->exists('portal.login') && view()->exists('portal.home') && view()->exists('portal.admin-card'),
        ];
        $this->line('Selbsttest Paket 36 (Mieterportal)');
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
