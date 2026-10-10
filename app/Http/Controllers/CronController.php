<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\CronToken;
use App\Support\DailyTasks;
use Throwable;

/** Wird vom Cronjob bei IONOS aufgerufen und führt die täglichen Aufgaben aus. */
class CronController extends Controller
{
    public function run(string $token)
    {
        $expected = CronToken::get();

        if ($expected === null || ! hash_equals($expected, $token)) {
            Audit::log('cron.rejected', 'Cron-Adresse mit falschem Schlüssel aufgerufen', null, null, null, 'Cron');

            abort(404);
        }

        @set_time_limit(300);
        ignore_user_abort(true);

        try {
            $lines = DailyTasks::run();
        } catch (Throwable $e) {
            Audit::log('cron.failed', 'Tägliche Aufgaben fehlgeschlagen: '.$e->getMessage(), null, null, null, 'Cron');

            return response('FEHLER: '.$e->getMessage(), 500, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        Audit::log('cron.run', 'Tägliche Aufgaben ausgeführt: '.implode('; ', $lines), null, null, null, 'Cron');

        return response("OK\n".implode("\n", $lines), 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
