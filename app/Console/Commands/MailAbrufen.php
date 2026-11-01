<?php

namespace App\Console\Commands;

use App\Support\Mail\Inbox;
use App\Support\Mail\MailSettings;
use Illuminate\Console\Command;

/** Neue E-Mails aus dem Postfach in den Posteingang holen (läuft alle 5 Minuten über auto-deploy.sh). */
class MailAbrufen extends Command
{
    protected $signature = 'mail:abrufen {--cron : Still beenden, wenn der Empfang nicht eingerichtet ist}';

    protected $description = 'Neue E-Mails aus dem Postfach in den Posteingang holen';

    public function handle(): int
    {
        if ($this->option('cron') && (! MailSettings::receiveConfigured() || app()->isDownForMaintenance())) {
            return self::SUCCESS;
        }
        $r = Inbox::fetch();
        if ($r['fehler']) {
            $this->error('Fehler: '.$r['fehler']);

            return self::FAILURE;
        }
        $this->info($r['neu'].' neue E-Mail(s)'.($r['rest'] > 0 ? ', '.$r['rest'].' folgen beim nächsten Lauf' : '').'.');

        return self::SUCCESS;
    }
}
