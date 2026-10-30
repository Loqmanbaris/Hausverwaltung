<?php

namespace App\Console\Commands;

use App\Support\Tag\Vorlagen;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

/** Selbsttest Paket 46 (Planvorlagen in „Mein Tag“). */
class PlanSelbsttest extends Command
{
    protected $signature = 'plan:selbsttest';

    protected $description = 'Selbsttest Planvorlagen (Paket 46)';

    public function handle(): int
    {
        $titles = [];
        $dup = false;
        $valid = true;
        foreach (Vorlagen::TEMPLATES as $t) {
            foreach ($t['entries'] as $e) {
                $dup = $dup || in_array($e['title'], $titles, true);
                $titles[] = $e['title'];
                $valid = $valid && in_array($e['kind'], ['todo', 'termin'], true) && (! isset($e['time']) || preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $e['time']) === 1)
                    && (! isset($e['repeat']) || \App\Support\Tag\MeinTag::parseRepeat($e['repeat'])[0] !== null) && (isset($e['date']) || isset($e['next']) || isset($e['nth_monday']));
            }
        }
        $checks = [
            'Drei Vorlagen' => array_keys(Vorlagen::TEMPLATES) === ['datev', 'woche', 'nebenkosten'],
            'Einträge gültig, keine doppelten Titel' => $valid && ! $dup && count($titles) === 15,
            'Nächster Wochentag' => Vorlagen::nextWeekday('2026-10-30', 2) === '2026-11-03' && Vorlagen::nextWeekday('2026-11-02', 1) === '2026-11-09',
            'Zweiter Montag im Monat' => Vorlagen::nthMonday('2026-10-30', 2) === '2026-11-09' && Vorlagen::nthMonday('2026-11-10', 2) === '2026-12-14',
            'Vergangenes entfällt' => Vorlagen::dateOf(['date' => '2026-11-04'], '2026-11-05') === null && Vorlagen::dateOf(['date' => '2026-11-04'], '2026-10-30') === '2026-11-04',
            'Seite' => Route::has('tag.template'),
        ];
        $this->line('Selbsttest Paket 46 (Planvorlagen)');
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
