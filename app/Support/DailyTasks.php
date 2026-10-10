<?php

namespace App\Support;

use App\Support\Backup\BackupService;
use App\Support\Rent\RentLedger;

/** Aufgaben, die einmal täglich laufen (aufgerufen über den Cronjob bei IONOS). */
class DailyTasks
{
    /** Ist die letzte Sicherung jünger als das, wird nicht noch einmal gesichert. */
    private const BACKUP_MIN_AGE_HOURS = 12;

    /**
     * @return string[] Kurze Meldungen für die Antwort und das Protokoll
     *
     * @throws \Throwable wenn eine Aufgabe scheitert
     */
    public static function run(): array
    {
        $lines = [];

        $last = BackupService::lastBackupAt();
        if ($last !== null && (time() - $last) < self::BACKUP_MIN_AGE_HOURS * 3600) {
            $lines[] = 'Sicherung: übersprungen, die letzte ist erst '.number_format((time() - $last) / 3600, 1, ',', '').' Stunden alt';
        } else {
            $result = (new BackupService)->run();
            $deleted = BackupService::prune(BackupService::directory());
            $lines[] = 'Sicherung: erstellt '.$result['file'].' ('.BackupService::formatSize($result['size']).'), '.count($deleted).' alte gelöscht';
        }

        // Soll-Mieten für den laufenden Monat anlegen und Zahlungen neu zuordnen (Paket 5a)
        $rent = RentLedger::syncAll();
        $lines[] = 'Mieten: '.$rent['leases'].' Verträge geprüft, '.$rent['created'].' neue Soll-Posten'.($rent['changed'] > 0 ? ', '.$rent['changed'].' angepasst' : '');

        // Erfüllte Ratenvereinbarungen abschließen (Paket 14)
        if (\Illuminate\Support\Facades\Schema::hasTable('installment_plans')) {
            $n = \App\Support\Interest\Raten::refreshAll();
            if ($n > 0) {
                $lines[] = 'Raten: '.$n.' Vereinbarung(en) erfüllt';
            }
        }

        // Erinnerung an ablaufende Dokumente per E-Mail (Paket 22)
        if (\Illuminate\Support\Facades\Schema::hasTable('documents')) {
            $n = \App\Support\Ablage\Ablage::sendReminders();
            if ($n > 0) {
                $lines[] = 'Dokumente: Erinnerung für '.$n.' Dokument(e) gesendet';
            }
        }

        // Erinnerung an fällige Wartungen (Paket 24)
        if (\Illuminate\Support\Facades\Schema::hasTable('maintenance_tasks')) {
            $n = \App\Support\Technik\Wartung::sendReminders();
            if ($n > 0) {
                $lines[] = 'Wartung: Erinnerung für '.$n.' Termin(e) gesendet';
            }
        }

        // Erledigte Anfragen aus dem Kontaktformular nach sechs Monaten löschen (Paket 11)
        if (\Illuminate\Support\Facades\Schema::hasTable('inquiries')) {
            $n = \App\Models\Inquiry::query()->where('status', 'erledigt')->where('updated_at', '<', now()->subMonths(6))->delete();
            if ($n > 0) {
                $lines[] = 'Anfragen: '.$n.' alte erledigte gelöscht';
            }
        }

        return $lines;
    }
}
