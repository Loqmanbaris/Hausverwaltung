<?php

namespace App\Console\Commands;

use App\Support\Backup\BackupService;
use Illuminate\Console\Command;
use Throwable;

class BackupRun extends Command
{
    protected $signature = 'backup:run {--ohne-aufraeumen : Alte Sicherungen nicht löschen}';

    protected $description = 'Erstellt eine verschlüsselte Sicherung und räumt alte Sicherungen auf';

    public function handle(BackupService $service): int
    {
        try {
            $result = $service->run();
        } catch (Throwable $e) {
            $this->error('Sicherung fehlgeschlagen: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Sicherung erstellt: '.$result['file'].' ('.BackupService::formatSize($result['size']).', '
            .$result['tables'].' Tabellen, '.$result['rows'].' Zeilen)');

        if (! $this->option('ohne-aufraeumen')) {
            $deleted = BackupService::prune(BackupService::directory());
            $this->line(count($deleted).' alte Sicherung(en) gelöscht.');
        }

        return self::SUCCESS;
    }
}
