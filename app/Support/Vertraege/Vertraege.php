<?php

namespace App\Support\Vertraege;

use App\Models\ServiceContract;
use App\Models\User;
use App\Support\Audit;
use App\Support\Mail\MailService;
use App\Support\Mail\MailSettings;
use App\Support\Rent\Erhoehung\Regeln;
use RuntimeException;

/** Laufende Verträge: Zustand, Erinnerungen, Kündigung (Paket 32). */
class Vertraege
{
    public static function info(ServiceContract $c, ?string $today = null): array
    {
        return Laufzeit::info([
            'status' => (string) $c->status,
            'term_end' => $c->term_end?->format('Y-m-d'),
            'auto_renew' => (bool) $c->auto_renew,
            'renew_months' => (int) $c->renew_months,
            'notice_value' => (int) $c->notice_value,
            'notice_unit' => (string) $c->notice_unit,
            'remind_days' => (int) $c->remind_days,
        ], $today ?? date('Y-m-d'));
    }

    /**
     * Verträge, um die man sich kümmern sollte: Kündigungsfrist nah, Vertrag läuft aus oder ist abgelaufen.
     *
     * @return array<int, array{contract: ServiceContract, info: array}>
     */
    public static function attention(User $user, ?int $ownerId, ?string $today = null): array
    {
        $today ??= date('Y-m-d');
        $out = [];
        $rows = ServiceContract::query()->visibleTo($user)->where('status', 'aktiv')->whereNotNull('term_end')
            ->when($ownerId, fn ($q) => $q->where('service_contracts.owner_id', $ownerId))->with(['property', 'unit', 'owner'])->get();
        foreach ($rows as $c) {
            $i = self::info($c, $today);
            if (in_array($i['state'], ['dringend', 'bald', 'abgelaufen'], true)) {
                $out[] = ['contract' => $c, 'info' => $i];
            }
        }
        usort($out, fn ($a, $b) => strcmp((string) $a['info']['action_date'], (string) $b['info']['action_date']));

        return $out;
    }

    /** Termine in den nächsten $days Tagen (Kündigungsfrist oder Vertragsende). */
    public static function upcoming(User $user, ?int $ownerId, int $days, ?string $today = null): array
    {
        $today ??= date('Y-m-d');
        $limit = date('Y-m-d', strtotime($today.' +'.$days.' days'));
        $out = [];
        $rows = ServiceContract::query()->visibleTo($user)->where('status', 'aktiv')->whereNotNull('term_end')
            ->when($ownerId, fn ($q) => $q->where('service_contracts.owner_id', $ownerId))->with(['property', 'unit'])->get();
        foreach ($rows as $c) {
            $i = self::info($c, $today);
            if ($i['action_date'] !== null && $i['action_date'] >= $today && $i['action_date'] <= $limit) {
                $out[] = ['contract' => $c, 'info' => $i];
            }
        }

        return $out;
    }

    /**
     * Sammel-E-Mail an die Kopie-Adresse: einmal bei Beginn der Erinnerungsfrist, noch einmal sieben Tage vorher.
     * Wird ein Termin geändert, beginnt die Erinnerung neu. Gibt die Zahl der erwähnten Verträge zurück.
     */
    public static function sendReminders(?string $today = null): int
    {
        $today ??= date('Y-m-d');
        $to = MailSettings::copyAddress();
        if (! MailService::validAddress($to) || (MailService::$fake === null && ! MailSettings::configured())) {
            return 0;
        }
        $lines = [];
        $mark = [];
        foreach (ServiceContract::query()->where('status', 'aktiv')->whereNotNull('term_end')->with(['property', 'unit'])->get() as $c) {
            $i = self::info($c, $today);
            if ($i['action_date'] === null || ! in_array($i['state'], ['bald', 'dringend', 'abgelaufen'], true)) {
                continue;
            }
            $stage = $c->reminded_for?->format('Y-m-d') === $i['action_date'] ? (int) $c->reminded_stage : 0;
            $need = in_array($i['state'], ['dringend', 'abgelaufen'], true) ? 2 : 1;
            if ($stage >= $need) {
                continue;
            }
            $what = $i['state'] === 'abgelaufen' ? 'abgelaufen seit '.Regeln::de($i['action_date']).' – bitte neuen Vertrag klären oder als beendet eintragen'
                : ($c->auto_renew ? 'Kündigung bis '.Regeln::de($i['action_date']).' (zum '.Regeln::de($i['next_end']).')' : 'Vertrag endet am '.Regeln::de($i['action_date']));
            $lines[] = '- '.$c->title.($c->provider ? ' ('.$c->provider.')' : '').', '.$c->place().': '.$what;
            $mark[] = [$c->id, $i['action_date'], $need];
        }
        if ($lines === []) {
            return 0;
        }
        $body = "Guten Tag,\n\nbei folgenden Verträgen steht eine Frist an:\n\n".implode("\n", $lines)
            ."\n\nIm CRM kannst du den Vertrag ansehen, die Kündigung vermerken oder das Laufzeitende ändern:\n".route('svc.index');
        $log = MailService::send([$to], count($lines) === 1 ? 'Vertrag: Frist beachten' : count($lines).' Verträge: Fristen beachten', $body, [], ['kind' => 'hinweis'], null, false);
        if ($log->status !== 'gesendet') {
            return 0;
        }
        foreach ($mark as [$id, $date, $stage]) {
            ServiceContract::query()->whereKey($id)->update(['reminded_for' => $date, 'reminded_stage' => $stage]);
        }

        return count($mark);
    }

    // ------------------------------------------------------------------ Statuswechsel

    public static function cancel(ServiceContract $c, string $cancelledOn, ?string $effective): ServiceContract
    {
        if ($c->status !== 'aktiv') {
            throw new RuntimeException('Der Vertrag läuft nicht mehr.');
        }
        $c->update(['status' => 'gekuendigt', 'cancelled_on' => $cancelledOn, 'cancel_effective' => $effective ?: self::info($c)['next_end']]);
        Audit::log('contract.cancelled', $c, 'Vertrag gekündigt: '.$c->title.', '.$c->place());

        return $c->refresh();
    }

    public static function end(ServiceContract $c): ServiceContract
    {
        $c->update(['status' => 'beendet']);
        Audit::log('contract.ended', $c, 'Vertrag als beendet eingetragen: '.$c->title.', '.$c->place());

        return $c->refresh();
    }

    public static function reopen(ServiceContract $c): ServiceContract
    {
        $c->update(['status' => 'aktiv', 'cancelled_on' => null, 'cancel_effective' => null, 'reminded_for' => null, 'reminded_stage' => 0]);
        Audit::log('contract.reopened', $c, 'Vertrag wieder auf „läuft“ gesetzt: '.$c->title.', '.$c->place());

        return $c->refresh();
    }

    /** Neues Laufzeitende nach Verlängerung oder neuem Vertrag. */
    public static function renew(ServiceContract $c, string $newEnd): ServiceContract
    {
        $c->update(['term_end' => $newEnd, 'status' => 'aktiv', 'cancelled_on' => null, 'cancel_effective' => null, 'reminded_for' => null, 'reminded_stage' => 0]);
        Audit::log('contract.renewed', $c, 'Laufzeitende geändert auf '.Regeln::de($newEnd).': '.$c->title.', '.$c->place());

        return $c->refresh();
    }
}
