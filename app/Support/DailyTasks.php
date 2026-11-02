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

        // Sicherungen bei OneDrive ablegen, wenn eingerichtet (Paket 40)
        $od = \App\Support\Backup\OneDrive::config();
        if ($od !== null && $od['auto']) {
            try {
                $sent = \App\Support\Backup\OneDrive::syncPending();
                $lines[] = $sent === [] ? 'OneDrive: alles aktuell' : 'OneDrive: '.count($sent).' Sicherung(en) hochgeladen';
            } catch (\Throwable $e) {
                // Ein Fehler beim Upload darf den übrigen Lauf nicht verhindern. Er steht im Status und im Protokoll.
                $lines[] = 'OneDrive: Upload fehlgeschlagen ('.$e->getMessage().')';
                try {
                    \App\Support\Mail\Notify::message('Sicherung: OneDrive-Upload fehlgeschlagen', $e->getMessage()."\n\nDie Sicherung liegt weiter auf dem Server. Prüfe unter Verwaltung → Sicherungen die OneDrive-Einstellungen.");
                } catch (\Throwable) {
                }
            }
        }

        // Automatischer Kontoabruf per FinTS (Paket 48) – vor dem Mietabgleich, damit neue Zahlungen gleich zählen
        if (\Illuminate\Support\Facades\Schema::hasTable('bank_connections') && \App\Support\Bank\Kontoabruf::productId() !== '') {
            foreach (\App\Models\BankConnection::query()->with('account')->where('active', true)->whereNotNull('tan_mode')->whereIn('status', ['neu', 'bereit', 'fehler'])->get() as $conn) {
                $r = \App\Support\Bank\Kontoabruf::start($conn, null);
                $iban = substr(\App\Support\Stammdaten::normalizeIban((string) $conn->account?->iban), -4);
                if ($r['status'] === 'fertig') {
                    $lines[] = 'Kontoabruf …'.$iban.': '.$r['text'];
                } else {
                    // Eine Freigabe kann der nächtliche Lauf nicht abwarten: offenen Stand verwerfen, Hinweis senden
                    if ($r['status'] === 'tan') {
                        $conn->forceFill(['pending' => null, 'last_message' => 'Die Bank verlangt eine neue Freigabe. Bitte unter Umsätze → Kontoabruf „Jetzt abrufen“ tippen und in der App freigeben.'])->save();
                    }
                    $lines[] = 'Kontoabruf …'.$iban.': '.($r['status'] === 'tan' ? 'Freigabe nötig' : 'Fehler – '.$r['text']);
                    try {
                        \App\Support\Mail\Notify::message('Kontoabruf: '.($r['status'] === 'tan' ? 'Freigabe nötig' : 'fehlgeschlagen'),
                            ($r['status'] === 'tan' ? 'Die Bank verlangt für den automatischen Kontoabruf eine neue Freigabe (etwa alle 90 Tage).' : 'Der automatische Kontoabruf ist fehlgeschlagen: '.$r['text'])
                            ."\n\nBitte im CRM unter Umsätze → Kontoabruf „Jetzt abrufen“ tippen und in der S-pushTAN-App freigeben.");
                    } catch (\Throwable) {
                    }
                }
            }
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

        // „Mein Tag“ morgens per E-Mail (Paket 42)
        if (\Illuminate\Support\Facades\Schema::hasTable('personal_items')) {
            $n = \App\Support\Tag\MeinTag::sendDayMails();
            if ($n > 0) {
                $lines[] = 'Mein Tag: '.$n.' E-Mail(s) gesendet';
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

        // Erinnerung an Kündigungsfristen laufender Verträge (Paket 32)
        if (\Illuminate\Support\Facades\Schema::hasTable('service_contracts')) {
            $n = \App\Support\Vertraege\Vertraege::sendReminders();
            if ($n > 0) {
                $lines[] = 'Verträge: Erinnerung für '.$n.' Vertrag/Verträge gesendet';
            }
        }

        // Daten von Interessenten nach Ablauf der Löschfrist löschen (Paket 31)
        if (\Illuminate\Support\Facades\Schema::hasTable('applicants')) {
            $n = \App\Support\Interessenten\Interessenten::purge();
            if ($n > 0) {
                $lines[] = 'Interessenten: '.$n.' nach Ablauf der Löschfrist gelöscht';
            }
        }

        return $lines;
    }
}
