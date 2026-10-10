<?php

namespace App\Support\Technik;

use App\Models\MaintenanceLog;
use App\Models\MaintenanceTask;
use App\Models\Property;
use App\Models\User;
use App\Support\Audit;
use App\Support\Finance\LoanPlan;
use DateTimeImmutable;
use Illuminate\Support\Collection;

/** Wartung und Prüfpflichten (Paket 24). Die Fristen sind übliche Werte – im Einzelfall Landesrecht und Herstellerangaben beachten. */
class Wartung
{
    /** Schlüssel => [Bezeichnung, Abstand in Monaten, Hinweis, Gruppe] */
    public const CATALOG = [
        'rauchmelder' => ['Rauchwarnmelder prüfen', 12, 'Jährliche Funktionsprüfung (Landesbauordnung, DIN 14676). Pflicht des Eigentümers, kann übertragen werden.', 'Rauchmelder und Heizung'],
        'heizung' => ['Heizungswartung', 12, 'Jährlich empfohlen, oft Voraussetzung für Herstellergarantie. Kosten sind umlagefähig.', 'Rauchmelder und Heizung'],
        'schornsteinfeger' => ['Schornsteinfeger (Kehrung/Messung)', 12, 'Termine laut Feuerstättenbescheid des Bezirksschornsteinfegers.', 'Rauchmelder und Heizung'],
        'feuerstaettenschau' => ['Feuerstättenschau', 42, 'Zweimal in sieben Jahren durch den Bezirksschornsteinfeger.', 'Rauchmelder und Heizung'],
        'legionellen' => ['Trinkwasser: Legionellenprüfung', 36, 'Pflicht bei Großanlagen (Speicher über 400 l oder über 3 l Leitungsinhalt), alle drei Jahre (TrinkwV).', 'Trinkwasser und Gas'],
        'gas' => ['Gasleitung: Gebrauchsfähigkeitsprüfung', 144, 'Empfohlen alle 12 Jahre (DVGW-TRGI).', 'Trinkwasser und Gas'],
        'rueckstau' => ['Rückstausicherung warten', 6, 'Halbjährlich (DIN EN 13564), wenn vorhanden.', 'Trinkwasser und Gas'],
        'echeck' => ['Elektroanlage prüfen (E-Check)', 48, 'Empfohlen alle vier Jahre, bei Gewerbe oft Pflicht (DGUV V3).', 'Elektro und Aufzug'],
        'aufzug' => ['Aufzug: Prüfung durch ZÜS', 12, 'Hauptprüfung alle zwei Jahre, dazwischen Zwischenprüfung (BetrSichV).', 'Elektro und Aufzug'],
        'blitzschutz' => ['Blitzschutzanlage prüfen', 48, 'Je nach Schutzklasse alle 2 bis 4 Jahre.', 'Elektro und Aufzug'],
        'feuerloescher' => ['Feuerlöscher prüfen', 24, 'Alle zwei Jahre durch Sachkundigen.', 'Sonstiges'],
        'dachrinne' => ['Dachrinnen reinigen', 12, '', 'Sonstiges'],
        'spielplatz' => ['Spielplatz: Sichtprüfung', 12, 'Jährliche Hauptinspektion (DIN EN 1176).', 'Sonstiges'],
        'eigen' => ['Eigene Wartung', 12, '', 'Sonstiges'],
    ];

    public const INTERVALS = [1 => 'monatlich', 3 => 'vierteljährlich', 6 => 'halbjährlich', 12 => 'jährlich', 24 => 'alle 2 Jahre', 36 => 'alle 3 Jahre', 42 => 'alle 3½ Jahre', 48 => 'alle 4 Jahre', 60 => 'alle 5 Jahre', 120 => 'alle 10 Jahre', 144 => 'alle 12 Jahre'];

    public static function intervalLabel(?int $months): string
    {
        if (! $months) {
            return 'einmalig';
        }

        return self::INTERVALS[$months] ?? 'alle '.$months.' Monate';
    }

    public static function nextAfter(string $done, int $months): string
    {
        return LoanPlan::addMonths(new DateTimeImmutable($done), $months);
    }

    /** Als erledigt eintragen; nächster Termin = erledigt + Abstand. */
    public static function complete(MaintenanceTask $t, string $doneOn, ?string $note, ?User $user, ?int $orderId = null): MaintenanceLog
    {
        $log = MaintenanceLog::create(['maintenance_task_id' => $t->id, 'done_on' => $doneOn, 'work_order_id' => $orderId, 'note' => $note, 'user_id' => $user?->id]);
        $t->last_done = $doneOn;
        if ($t->interval_months) {
            $t->next_due = self::nextAfter($doneOn, (int) $t->interval_months);
        } else {
            $t->active = false;
        }
        $t->reminded_at = null;
        $t->save();
        Audit::log('maintenance.done', $t, $t->title.' erledigt am '.date('d.m.Y', strtotime($doneOn)).' ('.$t->place().')', actor: $user);

        return $log;
    }

    /** Standard-Prüfungen für ein Objekt anlegen. @param string[] $types */
    public static function createDefaults(Property $p, array $types, string $firstDue, ?User $user): int
    {
        $n = 0;
        foreach ($types as $type) {
            if (! isset(self::CATALOG[$type]) || $type === 'eigen') {
                continue;
            }
            if (MaintenanceTask::query()->where('property_id', $p->id)->whereNull('unit_id')->where('type', $type)->where('active', true)->exists()) {
                continue;
            }
            [$title, $months] = self::CATALOG[$type];
            MaintenanceTask::create(['owner_id' => $p->owner_id, 'property_id' => $p->id, 'type' => $type, 'title' => $title, 'interval_months' => $months, 'next_due' => $firstDue, 'created_by' => $user?->id]);
            $n++;
        }

        return $n;
    }

    public static function due(User $user, ?int $ownerId = null, ?string $today = null, int $days = 60): Collection
    {
        $today ??= date('Y-m-d');

        return MaintenanceTask::visibleTo($user)->where('active', true)
            ->when($ownerId, fn ($q) => $q->where('maintenance_tasks.owner_id', $ownerId))
            ->where('next_due', '<=', date('Y-m-d', strtotime($today.' +'.$days.' days')))
            ->with(['property', 'unit'])->orderBy('next_due')->get();
    }

    /** Täglich: Erinnerung an fällige Wartungen per E-Mail. */
    public static function sendReminders(?string $today = null): int
    {
        $today ??= date('Y-m-d');
        $due = MaintenanceTask::query()->where('active', true)->whereNull('reminded_at')
            ->whereRaw('next_due <= DATE_ADD(?, INTERVAL remind_days DAY)', [$today])->with(['property', 'unit', 'vendor'])->orderBy('next_due')->limit(100)->get();
        if ($due->isEmpty()) {
            return 0;
        }
        $to = \App\Support\Mail\MailSettings::copyAddress();
        if (! \App\Support\Mail\MailService::validAddress($to) || (\App\Support\Mail\MailService::$fake === null && ! \App\Support\Mail\MailSettings::configured())) {
            return 0;
        }
        $lines = [];
        foreach ($due as $t) {
            $lines[] = '- '.$t->title.' ('.$t->place().'): '.($t->next_due->format('Y-m-d') < $today ? 'überfällig seit ' : 'fällig am ').$t->next_due->format('d.m.Y').($t->vendor ? ', Firma: '.$t->vendor->name : '');
        }
        $body = "Guten Tag,\n\nfolgende Wartungen und Prüfungen stehen an:\n\n".implode("\n", $lines)
            ."\n\nIm CRM kannst du direkt einen Auftrag an die Firma schicken oder die Wartung als erledigt eintragen:\n".route('maintenance.index');
        $log = \App\Support\Mail\MailService::send([$to], count($due) === 1 ? 'Wartung fällig: '.$due->first()->title : count($due).' Wartungen fällig', $body, [], ['kind' => 'hinweis'], null, false);
        if ($log->status !== 'gesendet') {
            return 0;
        }
        MaintenanceTask::query()->whereIn('id', $due->pluck('id'))->update(['reminded_at' => now()]);

        return $due->count();
    }
}
