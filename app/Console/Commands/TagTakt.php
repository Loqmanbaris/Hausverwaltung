<?php

namespace App\Console\Commands;

use App\Support\Tag\Woche;
use Illuminate\Console\Command;

/** Alle 5 Minuten (auto-deploy.sh): Erinnerungen aufs Handy und montags 07:30 der Wochenüberblick. */
class TagTakt extends Command
{
    protected $signature = 'tag:takt';

    protected $description = 'Erinnerungen aufs Handy schicken, montags den Wochenüberblick';

    public function handle(): int
    {
        if (app()->isDownForMaintenance()) {
            return self::SUCCESS;
        }
        $r = Woche::tick();
        $this->info($r['push'].' Erinnerung(en), '.$r['week'].' Wochenüberblick(e).');

        return self::SUCCESS;
    }
}
