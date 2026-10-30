<?php

namespace App\Console\Commands;

use App\Models\PersonalItem;
use App\Support\Tag\MeinTag;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/** Selbsttest Paket 44 (wiederkehrende Einträge in Mein Tag). */
class SerieSelbsttest extends Command
{
    protected $signature = 'serie:selbsttest';

    protected $description = 'Selbsttest wiederkehrende Einträge (Paket 44)';

    public function handle(): int
    {
        $mk = fn (string $due, ?string $rule, int $every = 1, ?string $until = null) => new PersonalItem(['kind' => 'todo', 'title' => 'T', 'due_on' => $due, 'repeat' => $rule, 'repeat_every' => $every, 'repeat_until' => $until]);
        $occ = fn (PersonalItem $i, string $from, string $to) => implode(' ', MeinTag::occurrences($i, $from, $to));
        $checks = [
            'Spalten und Tabelle' => Schema::hasColumn('personal_items', 'repeat') && Schema::hasTable('personal_item_marks'),
            'Einzeltermin' => $occ($mk('2026-11-10', null), '2026-11-09', '2026-11-15') === '2026-11-10' && $occ($mk('2026-11-20', null), '2026-11-09', '2026-11-15') === '',
            'Jede und jede zweite Woche' => $occ($mk('2026-11-09', 'weekly'), '2026-11-01', '2026-12-01') === '2026-11-09 2026-11-16 2026-11-23 2026-11-30'
                && $occ($mk('2026-11-09', 'weekly', 2), '2026-11-01', '2026-12-15') === '2026-11-09 2026-11-23 2026-12-07',
            'Monatsende wird gekürzt' => $occ($mk('2027-01-31', 'monthly'), '2027-01-01', '2027-03-31') === '2027-01-31 2027-02-28 2027-03-31',
            'Zweiter Montag im Monat' => $occ($mk('2026-11-09', 'nth'), '2026-11-01', '2027-01-31') === '2026-11-09 2026-12-14 2027-01-11',
            'Alle sechs Monate, erster Montag' => $occ($mk('2027-01-04', 'nth', 6), '2027-01-01', '2028-12-31') === '2027-01-04 2027-07-05 2028-01-03 2028-07-03',
            'Jährlich, 29. Februar' => $occ($mk('2028-02-29', 'yearly'), '2028-01-01', '2030-12-31') === '2028-02-29 2029-02-28 2030-02-28',
            'Ende einschließlich' => $occ($mk('2026-11-09', 'weekly', 1, '2026-11-23'), '2026-11-01', '2026-12-31') === '2026-11-09 2026-11-16 2026-11-23',
            'Wiederholung im Kalender-Abo' => MeinTag::icsRule($mk('2026-11-09', 'nth')) === 'FREQ=MONTHLY;INTERVAL=1;BYDAY=2MO' && MeinTag::icsRule($mk('2026-11-09', null)) === null,
            'Formularwert' => MeinTag::parseRepeat('nth:6') === ['nth', 6] && MeinTag::parseRepeat('x') === [null, 1],
            'Seiten' => Route::has('tag.skip') && Route::has('tag.done'),
            'Ansicht' => view()->exists('tag.index'),
        ];
        $this->line('Selbsttest Paket 44 (wiederkehrende Einträge)');
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
