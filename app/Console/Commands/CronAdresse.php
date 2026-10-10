<?php

namespace App\Console\Commands;

use App\Support\CronToken;
use Illuminate\Console\Command;

class CronAdresse extends Command
{
    protected $signature = 'cron:adresse';

    protected $description = 'Zeigt die Adresse für den Cronjob bei IONOS (legt den geheimen Schlüssel beim ersten Mal an)';

    public function handle(): int
    {
        $url = CronToken::url();
        $parts = parse_url($url);

        $this->line('Domain: '.($parts['host'] ?? ''));
        $this->line('Pfad:   '.($parts['path'] ?? ''));
        $this->line('Ganze Adresse: '.$url);

        return self::SUCCESS;
    }
}
