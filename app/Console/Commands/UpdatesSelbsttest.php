<?php

namespace App\Console\Commands;

use App\Support\Updates\UpdatePackage;
use App\Support\Updates\Updater;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** Prüft die Update-Seite (Paket 6): Tabelle, Schlüssel, Signaturprüfung, Schreibrechte. */
class UpdatesSelbsttest extends Command
{
    protected $signature = 'updates:selbsttest';

    protected $description = 'Selbsttest der Update-Seite (Paket 6)';

    /** Kleines, mit dem echten Schlüssel signiertes Testpaket ohne Dateien. */
    private const PROBE = '{"format":"crm-update-1","key":"2026-10","payload":"H4sIAAAAAAACA6tWykvMTVWyUiooyk9KVdJRKsksyQHxA6D8tMyc1GIlq+hYHaXczPSixJLM\/Dwovyi1sDSzKLU4Hq6mFgAyDhFZTwAAAA==","signature":"Sl10sVPLbQAjYrgf0lCbVW7b+akv2fBv3Zj1GH9SEUp6GAFiAXkL7cqd3qSovnPmrhhFGkGX3MAPbN9tTRUSWPrzfm36iLBDnMNwSZPsv+HTyzLZDdQ3cx\/q86wElrpFZj+iyOsy\/J75c1QysYaPpT3FgVjuQzEibE8b\/\/1bJ2zqCuBINVdlgp3HKRIip2Ry5LHwRXgeWLsOHQ0j6aSUioa\/oHbo6rOe2tQpOKirmMjfCTYrIVqkB2CWqM4NF2iPNFkASku+t9lU0BskM9rRtgG8CTKEIY+SbymkyjxWuz1kFU+3obfHyB6ptYcMBEkc60Yt0eOioIQu4IGYZbUAm4Eu9DDPtTjIxuA74qIKjQGzj0ewnWzcNOXs2WqvyUBZwN5WiwjQHd4CUBbEGoUbuu5BWE2bjrOqWNqiZnE8iI1M0LjSkCyWzJAxdZKl0k0a1ggI6P+NIi8yuxr7bYZCAlTV0TpBVcVkw7\/+6cQFNStjKPLlOb7ci1rKdAeUC\/hc"}';

    private int $failed = 0;

    public function handle(): int
    {
        $this->line('Selbsttest Paket 6 (Update-Seite)');

        $this->check('Tabelle system_updates', Schema::hasTable('system_updates') && Schema::hasColumns('system_updates', ['name', 'status', 'backup_dir', 'selftest']));
        foreach (['updates.index', 'updates.upload', 'updates.show', 'updates.install', 'updates.result', 'updates.rollback'] as $r) {
            $this->check('Seite '.$r, Route::has($r));
        }
        foreach (['updates.index', 'updates.show', 'updates.result'] as $v) {
            $this->check('Vorlage '.$v, view()->exists($v));
        }

        try {
            $p = UpdatePackage::fromSigned(self::PROBE);
            $this->check('Signatur eines echten Pakets wird erkannt', $p->name === 'probe');
        } catch (Throwable $e) {
            $this->check('Signatur eines echten Pakets: '.$e->getMessage(), false);
        }

        $fake = json_decode(self::PROBE, true);
        $fake['payload'] = base64_encode(gzencode(json_encode(['name' => 'boese', 'title' => 'x', 'files' => []])));
        try {
            UpdatePackage::fromSigned(json_encode($fake));
            $this->check('Verändertes Paket wird abgelehnt', false);
        } catch (Throwable) {
            $this->check('Verändertes Paket wird abgelehnt', true);
        }

        $bad = false;
        try {
            UpdatePackage::assertPath('../.env');
        } catch (Throwable) {
            $bad = true;
        }
        $this->check('Nicht erlaubte Dateien werden abgelehnt', $bad);

        $dir = Updater::storageDir();
        if (! is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        $this->check('Ablage für Updates beschreibbar', is_dir($dir) && is_writable($dir));
        $this->check('Programmdateien beschreibbar (app, resources/views, routes, database/migrations)',
            is_writable(base_path('app')) && is_writable(base_path('resources/views')) && is_writable(base_path('routes')) && is_writable(base_path('database/migrations')));

        if ($this->failed > 0) {
            $this->error($this->failed.' Prüfung(en) fehlgeschlagen.');

            return self::FAILURE;
        }
        $this->info('Alle Prüfungen in Ordnung.');

        return self::SUCCESS;
    }

    private function check(string $label, bool $ok): void
    {
        if (! $ok) {
            $this->failed++;
        }
        $this->line(($ok ? '  [OK]     ' : '  [FEHLER] ').$label);
    }
}
