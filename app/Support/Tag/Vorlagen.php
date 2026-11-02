<?php

namespace App\Support\Tag;

use App\Models\PersonalItem;
use App\Models\User;

/**
 * Planvorlagen für „Mein Tag“ (Paket 46): fertige Listen, die man mit einem Knopf in den eigenen Plan übernimmt.
 * Ein Eintrag hat entweder einen festen Tag ('date') oder den nächsten Wochentag ab morgen ('next', 1 = Montag bis 7 = Sonntag).
 * Einträge in der Vergangenheit werden übersprungen. Zweimal übernehmen legt nichts doppelt an.
 */
class Vorlagen
{
    public const TEMPLATES = [
        'datev' => [
            'title' => 'DATEV einrichten (mit Steuerberater)',
            'text' => 'Anruf beim Steuerberater, dann Konten im CRM eintragen und einen Probe-Export prüfen lassen.',
            'entries' => [
                ['kind' => 'termin', 'title' => 'Steuerberater anrufen: DATEV-Konten abstimmen', 'next' => 2, 'time' => '10:00', 'end' => '10:30', 'remind' => '60',
                    'notes' => 'Liste „DATEV-Abstimmung-Steuerberater“ durchgehen: Gegenkonto, Konten der Kostenarten, Erlöskonten Miete, offene Steuerfragen (Kaution-Nachforderung, Erhaltung oder AfA, Verwaltungshonorar).'],
                ['kind' => 'todo', 'title' => 'DATEV: Gegenkonto und Einstellungen im CRM eintragen', 'next' => 3, 'time' => '09:00', 'remind' => '15', 'notes' => 'DATEV-Export → Einstellungen.'],
                ['kind' => 'todo', 'title' => 'DATEV: Konten der Kostenarten eintragen', 'next' => 3, 'time' => '10:00', 'remind' => '15', 'notes' => 'DATEV-Export → Konten der Kostenarten (23 Kostenarten).'],
                ['kind' => 'todo', 'title' => 'DATEV: Erlöskonten für die Mieten eintragen', 'next' => 4, 'time' => '09:00', 'remind' => '15', 'notes' => 'DATEV-Export → Export Mieten → Erlöskonten.'],
                ['kind' => 'todo', 'title' => 'DATEV: Probe-Export für einen Monat erzeugen und prüfen lassen', 'next' => 5, 'time' => '09:00', 'remind' => '15', 'notes' => 'Datei und Belege (ZIP) an den Steuerberater, danach in „Einrichtung prüfen“ den Haken „DATEV abgestimmt“ setzen.'],
            ],
        ],
        'woche' => [
            'title' => 'Wochenroutine Hausverwaltung',
            'text' => 'Feste Aufgaben jede Woche und jeden Monat, jeweils mit Erinnerung. Beginn am nächsten passenden Tag.',
            'entries' => [
                ['kind' => 'todo', 'title' => 'Kontoauszüge importieren, Mieteingang prüfen', 'next' => 1, 'time' => '08:00', 'end' => '09:00', 'remind' => '15', 'repeat' => 'weekly:1'],
                ['kind' => 'todo', 'title' => 'Schäden und Aufträge: Handwerker nachfassen', 'next' => 2, 'time' => '08:00', 'end' => '09:00', 'remind' => '15', 'repeat' => 'weekly:1'],
                ['kind' => 'todo', 'title' => 'Belege erfassen, Ausgaben abgleichen', 'next' => 3, 'time' => '08:00', 'end' => '09:00', 'remind' => '15', 'repeat' => 'weekly:1'],
                ['kind' => 'todo', 'title' => 'Wochenabschluss: Einrichtung und Sicherung prüfen', 'next' => 5, 'time' => '08:00', 'end' => '08:45', 'remind' => '15', 'repeat' => 'weekly:1', 'notes' => 'Danach die nächste Woche in „Mein Tag“ planen.'],
                ['kind' => 'todo', 'title' => 'Mahnlauf: Zahlungserinnerungen prüfen', 'nth_monday' => 2, 'time' => '09:00', 'end' => '09:30', 'remind' => '15', 'repeat' => 'nth:1', 'notes' => 'Miete ist seit dem 3. Werktag fällig. Mahnwesen im CRM.'],
                ['kind' => 'todo', 'title' => 'Basiszinssatz im CRM eintragen', 'date' => '2027-01-04', 'time' => '09:30', 'end' => '10:00', 'remind' => '15', 'repeat' => 'nth:6', 'notes' => 'Neuen Basiszinssatz der Bundesbank unter Mahnwesen eintragen (gilt ab 1.1. und 1.7.).'],
            ],
        ],
        'nebenkosten' => [
            'title' => 'Nebenkostenabrechnung 2025',
            'text' => 'Schritte bis zur Frist am 31.12.2026. Die Abrechnung muss bis dahin beim Mieter eingegangen sein.',
            'entries' => [
                ['kind' => 'todo', 'title' => 'NK 2025: Belege vollständig machen', 'date' => '2026-11-04', 'time' => '11:00', 'remind' => '60', 'notes' => 'Belege mit Objekt und umlagefähiger Kostenart. „Umsätze → Ausgaben zuordnen“ zeigt Abbuchungen ohne Beleg.'],
                ['kind' => 'todo', 'title' => 'NK 2025: Einstellungen und Zähler je Objekt', 'date' => '2026-11-05', 'time' => '11:00', 'remind' => '60', 'notes' => 'Nebenkosten → Vorbereitung je Objekt: Heizungsart, Brennstoff, Zählerstände Anfang und Ende 2025.'],
                ['kind' => 'todo', 'title' => 'NK 2025: Abrechnungen erstellen', 'date' => '2026-11-16', 'time' => '10:00', 'end' => '12:00', 'remind' => '1440', 'repeat' => 'yearly:1', 'notes' => 'CO2-Menge und CO2-Kosten von den Versorger-Rechnungen eintragen. Frist 31.12. (jedes Jahr für das Vorjahr).'],
                ['kind' => 'todo', 'title' => 'NK 2025: prüfen und versenden', 'date' => '2026-11-30', 'time' => '09:00', 'remind' => '1440', 'notes' => 'Spätestens Mitte Dezember versenden, entscheidend ist der Zugang beim Mieter bis 31.12.'],
            ],
        ],
    ];

    /** Nächster Wochentag nach $today (1 = Montag bis 7 = Sonntag). */
    public static function nextWeekday(string $today, int $isoDay): string
    {
        $d = strtotime($today.' +1 day');
        for ($i = 0; $i < 7; $i++) {
            if ((int) date('N', $d) === $isoDay) {
                return date('Y-m-d', $d);
            }
            $d = strtotime('+1 day', $d);
        }

        return date('Y-m-d', strtotime($today.' +1 day'));
    }

    /** n-ter Montag des Monats, der nach $today liegt (im aktuellen, sonst im nächsten Monat). */
    public static function nthMonday(string $today, int $n): string
    {
        $first = strtotime(date('Y-m-01', strtotime($today)));
        for ($m = 0; $m < 3; $m++) {
            $month = strtotime('+'.$m.' month', $first);
            $day = strtotime(date('Y-m-d', $month));
            $firstMonday = (8 - (int) date('N', $day)) % 7 + 1;
            $date = date('Y-m', $month).'-'.sprintf('%02d', $firstMonday + ($n - 1) * 7);
            if ($date > $today) {
                return $date;
            }
        }

        return $today;
    }

    public static function dateOf(array $e, string $today): ?string
    {
        if (isset($e['date'])) {
            return $e['date'] > $today ? $e['date'] : null;
        }
        if (isset($e['next'])) {
            return self::nextWeekday($today, (int) $e['next']);
        }
        if (isset($e['nth_monday'])) {
            return self::nthMonday($today, (int) $e['nth_monday']);
        }

        return null;
    }

    /** @return array{created: int, existing: int, past: int} */
    public static function apply(User $user, string $key, ?string $today = null): array
    {
        $today ??= (new \DateTimeImmutable('now', MeinTag::tz()))->format('Y-m-d');
        $tpl = self::TEMPLATES[$key] ?? null;
        abort_if($tpl === null, 404);
        $out = ['created' => 0, 'existing' => 0, 'past' => 0];
        foreach ($tpl['entries'] as $e) {
            $day = self::dateOf($e, $today);
            if ($day === null) {
                $out['past']++;

                continue;
            }
            if (PersonalItem::query()->where('user_id', $user->id)->where('title', $e['title'])->exists()) {
                $out['existing']++; // gleicher Titel schon da: nicht doppelt anlegen, auch nicht an einem anderen Tag

                continue;
            }
            [$rule, $every] = MeinTag::parseRepeat($e['repeat'] ?? null);
            PersonalItem::query()->create([
                'user_id' => $user->id, 'kind' => $e['kind'], 'title' => $e['title'], 'notes' => $e['notes'] ?? null, 'due_on' => $day,
                'start_time' => $e['time'] ?? null, 'end_time' => $e['end'] ?? null, 'remind' => $e['remind'] ?? null,
                'repeat' => $rule, 'repeat_every' => $every,
            ]);
            $out['created']++;
        }

        return $out;
    }
}
