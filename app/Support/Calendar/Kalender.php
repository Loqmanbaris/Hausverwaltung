<?php

namespace App\Support\Calendar;

use App\Models\CalendarFeed;
use App\Models\Lease;
use App\Models\LeaseRent;
use App\Models\User;
use App\Support\Dashboard\Cockpit;
use DateTimeImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/** Termine für das Kalender-Abo (Paket 33): dieselben Daten wie die Übersicht, aber für das ganze nächste Jahr. */
class Kalender
{
    public const DAYS = 400;

    /** Schlüssel => Bezeichnung */
    public const CATEGORIES = [
        'miete' => 'Mieten, Mietverträge und Mieterhöhungen',
        'vertraege' => 'Laufende Verträge (Kündigungsfristen)',
        'technik' => 'Wartung, Handwerker und Zähler',
        'finanzen' => 'Darlehen, Basiszins und Nebenkostenabrechnung',
        'dokumente' => 'Ablaufende Dokumente',
        'interessenten' => 'Besichtigungen',
    ];

    /** Art des Termins aus dem Link, auf den er zeigt. */
    public static function category(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $map = [
            '#^/laufende-vertraege#' => 'vertraege',
            '#^/(wartung|auftraege|zaehler|heizung|verbrauch|objekte/\d+/schaeden)#' => 'technik',
            '#^/(finanzierung|nebenkosten)#' => 'finanzen',
            '#^/mahnwesen#' => str_contains($url, '#basiszins') ? 'finanzen' : 'miete',
            '#^/dokumente#' => 'dokumente',
            '#^/interessenten#' => 'interessenten',
        ];
        foreach ($map as $re => $cat) {
            if (preg_match($re, $path)) {
                return $cat;
            }
        }

        return 'miete';
    }

    /**
     * @param  ?array<int, string>  $categories  null oder leer = alle
     * @return array<int, array{uid: string, summary: string, description: string, url: string, start: DateTimeImmutable, all_day: bool, warn: bool, category: string}>
     */
    public static function events(User $user, ?array $categories = null, ?string $today = null): array
    {
        $today ??= date('Y-m-d');
        $from = date('Y-m-d', strtotime($today.' -7 days'));
        $to = date('Y-m-d', strtotime($today.' +'.self::DAYS.' days'));
        $raw = Cockpit::events($user, null, $from, self::DAYS + 7);

        // Zusätzlich: Vertragsenden und Mietänderungen für das ganze Jahr
        foreach (self::leaseEvents($user, $from, $to) as $e) {
            $raw[] = $e;
        }

        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'crm';
        $out = [];
        $seen = [];
        foreach ($raw as $r) {
            $cat = self::category($r['url']);
            if ($categories !== null && $categories !== [] && ! in_array($cat, $categories, true)) {
                continue;
            }
            /** @var DateTimeImmutable $d */
            $d = $r['date'];
            $allDay = $d->format('H:i:s') === '00:00:00';
            $uid = substr(sha1($r['label']."\n".$r['detail']."\n".$d->format('Y-m-d H:i')), 0, 32).'@'.$host;
            if (isset($seen[$uid])) {
                continue;
            }
            $seen[$uid] = true;
            $out[] = [
                'uid' => $uid,
                'summary' => $r['label'].($r['detail'] !== '' ? ': '.$r['detail'] : ''),
                'description' => $r['detail'],
                'url' => $r['url'],
                'start' => $d,
                'all_day' => $allDay,
                'warn' => ($r['tone'] ?? 'info') === 'warn',
                'category' => self::CATEGORIES[$cat] ?? $cat,
            ];
        }
        usort($out, fn ($a, $b) => $a['start'] <=> $b['start']);

        return $out;
    }

    /** Vertragsenden und kommende Mietänderungen (Staffel, Erhöhung, Index). */
    private static function leaseEvents(User $user, string $from, string $to): array
    {
        $out = [];
        $push = function ($date, string $label, string $detail, string $url, string $tone = 'info') use (&$out) {
            $out[] = ['date' => DateTimeImmutable::createFromInterface($date), 'label' => $label, 'detail' => $detail, 'url' => $url, 'tone' => $tone];
        };
        try {
            foreach (Lease::visibleTo($user)->whereBetween('end_date', [$from, $to])->with(['unit', 'tenant'])->get() as $l) {
                $push($l->end_date, 'Vertrag endet', $l->unit?->label.' – '.$l->tenant?->display_name, route('leases.show', $l), 'warn');
            }
            foreach (LeaseRent::query()->whereBetween('valid_from', [$from, $to])->whereNotIn('reason', ['beginn', 'vorauszahlung', 'korrektur'])
                ->whereHas('lease', fn ($q) => $q->visibleTo($user))->with(['lease.unit', 'lease.tenant'])->get() as $r) {
                $push($r->valid_from, $r->reasonLabel(), $r->lease?->unit?->label.' – '.$r->lease?->tenant?->display_name, route('leases.show', $r->lease_id));
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $out;
    }

    // ------------------------------------------------------------------ Abo

    /** Legt das Abo an oder erzeugt einen neuen Link (der alte wird ungültig). */
    public static function createFeed(User $user): CalendarFeed
    {
        $token = Str::random(40);
        $feed = CalendarFeed::query()->firstOrNew(['user_id' => $user->id]);
        $feed->fill(['token_hash' => hash('sha256', $token), 'token_enc' => Crypt::encryptString($token), 'last_fetched_at' => null])->save();

        return $feed;
    }

    public static function byToken(string $token): ?CalendarFeed
    {
        if (preg_match('/^[A-Za-z0-9]{40}$/', $token) !== 1) {
            return null;
        }

        return CalendarFeed::query()->where('token_hash', hash('sha256', $token))->with('user')->first();
    }

    public static function url(CalendarFeed $feed): ?string
    {
        $t = $feed->token();

        return $t ? route('calendar.feed', $t) : null;
    }
}
