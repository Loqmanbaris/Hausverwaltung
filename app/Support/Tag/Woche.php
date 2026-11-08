<?php

namespace App\Support\Tag;

use App\Models\PersonalItem;
use App\Models\PersonalItemMark;
use App\Models\RentPayment;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\Mail\MailService;
use App\Support\Mail\MailSettings;
use App\Support\Push\Push;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Wochenüberblick (montags 07:30 per E-Mail und aufs Handy, außerdem montags als Karte in „Mein Tag“):
 * was letzte Woche erledigt wurde und eingegangen ist, was offen ist und was diese Woche ansteht.
 * Dazu der 5-Minuten-Takt für Erinnerungen aufs Handy.
 */
class Woche
{
    public const SEND_AT = '07:30';

    /** Montag der Woche, in der $day liegt. */
    public static function monday(string $day): string
    {
        $d = new DateTimeImmutable($day, MeinTag::tz());

        return $d->modify('-'.(((int) $d->format('N')) - 1).' days')->format('Y-m-d');
    }

    /**
     * @return array{from: string, to: string, kw: int, todos_done: int, payments: int, payments_sum: float, orders_done: int, orders_new: int,
     *   orders_open: int, overdue: int, rent: ?array, bank_open: int, mail_unread: int, week: array<string, array>, week_count: int}
     */
    public static function overview(User $user, string $monday): array
    {
        $from = date('Y-m-d', strtotime($monday.' -7 days'));
        $to = date('Y-m-d', strtotime($monday.' -1 day'));
        $safe = function (callable $f, $default = 0) {
            try {
                return $f();
            } catch (Throwable $e) {
                report($e);

                return $default;
            }
        };
        $todosDone = $safe(fn () => PersonalItem::query()->where('user_id', $user->id)->whereNotNull('done_at')
            ->whereBetween('done_at', [$from.' 00:00:00', $to.' 23:59:59'])->count()
            + PersonalItemMark::query()->where('kind', 'done')->whereBetween('on_date', [$from, $to])
                ->whereIn('personal_item_id', PersonalItem::query()->where('user_id', $user->id)->select('id'))->count());
        $pay = $safe(fn () => RentPayment::visibleTo($user)->whereBetween('paid_on', [$from, $to])->where('amount', '>', 0)
            ->selectRaw('count(*) as n, coalesce(sum(amount), 0) as s')->first(), null);
        $week = array_filter($safe(fn () => MeinTag::entriesWithOrders($user, $monday, date('Y-m-d', strtotime($monday.' +6 days'))), []), fn ($r) => $r !== []);

        return [
            'from' => $from, 'to' => $to, 'kw' => (int) date('W', strtotime($monday)),
            'todos_done' => (int) $todosDone,
            'payments' => (int) ($pay->n ?? 0),
            'payments_sum' => round((float) ($pay->s ?? 0), 2),
            'orders_done' => (int) $safe(fn () => WorkOrder::visibleTo($user)->whereBetween('done_on', [$from, $to])->count()),
            'orders_new' => (int) $safe(fn () => WorkOrder::visibleTo($user)->whereBetween('work_orders.created_at', [$from.' 00:00:00', $to.' 23:59:59'])->count()),
            'orders_open' => (int) $safe(fn () => WorkOrder::visibleTo($user)->whereIn('status', WorkOrder::OPEN)->count()),
            // Stand „offen“: heute (bzw. Ende der Woche, wenn sie schon vorbei ist)
            'overdue' => (int) $safe(fn () => MeinTag::overdue($user, min((new DateTimeImmutable('now', MeinTag::tz()))->format('Y-m-d'), date('Y-m-d', strtotime($monday.' +6 days'))))->count()),
            'rent' => MeinTag::rentStatus($user, $monday),
            'bank_open' => (int) $safe(fn () => \App\Support\Bank\BankService::openCountFor($user)),
            'mail_unread' => (int) $safe(fn () => \App\Models\ReceivedEmail::unreadCountFor($user)),
            'week' => $week,
            'week_count' => array_sum(array_map('count', $week)),
        ];
    }

    public static function money(float $v): string
    {
        return number_format($v, 2, ',', '.').' €';
    }

    public static function mailBody(User $user, array $o): string
    {
        $days = ['', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
        $l = ['Guten Morgen '.($user->name ?: '').',', '', 'dein Wochenüberblick für KW '.$o['kw'].':', '',
            'LETZTE WOCHE ('.date('d.m.', strtotime($o['from'])).' – '.date('d.m.', strtotime($o['to'])).')',
            '  ✓ '.$o['todos_done'].' To-dos erledigt',
            '  € '.$o['payments'].' Zahlungen eingegangen, zusammen '.self::money($o['payments_sum']),
            '  🔧 '.$o['orders_done'].' Aufträge erledigt, '.$o['orders_new'].' neu', '',
            'NOCH OFFEN'];
        $open = array_filter([
            $o['overdue'] > 0 ? '  '.$o['overdue'].' überfällige To-dos' : null,
            $o['rent'] && $o['rent']['open'] > 0 ? '  '.$o['rent']['open'].' von '.$o['rent']['total'].' Mieten diesen Monat ('.self::money($o['rent']['open_sum']).')' : null,
            $o['bank_open'] > 0 ? '  '.$o['bank_open'].' Kontoumsätze zuordnen' : null,
            $o['orders_open'] > 0 ? '  '.$o['orders_open'].' offene Aufträge' : null,
            $o['mail_unread'] > 0 ? '  '.$o['mail_unread'].' ungelesene E-Mails' : null,
        ]);
        $l = array_merge($l, $open === [] ? ['  nichts – alles erledigt 🎉'] : $open, ['', 'DIESE WOCHE']);
        if ($o['week'] === []) {
            $l[] = '  keine Termine, To-dos oder Fristen';
        }
        foreach ($o['week'] as $d => $rows) {
            $l[] = '  '.$days[(int) date('N', strtotime($d))].' '.date('d.m.', strtotime($d)).':';
            foreach (array_slice($rows, 0, 8) as $r) {
                $kind = $r['source'] === 'auftrag' ? 'Auftrag' : ($r['source'] === 'frist' ? 'Frist' : ($r['kind'] === 'todo' ? 'To-do' : 'Termin'));
                $l[] = '    '.($r['time'] ? $r['time'].'  ' : '       ').$kind.': '.$r['title'];
            }
            if (count($rows) > 8) {
                $l[] = '    … und '.(count($rows) - 8).' weitere';
            }
        }
        $l[] = '';
        $l[] = 'Alles im Detail: '.route('tag.week');

        return implode("\n", $l);
    }

    /** Einmal je Nutzer und Schlüssel (z. B. Erinnerung oder Woche); true = jetzt eingetragen, also senden. */
    public static function once(User $user, string $key): bool
    {
        try {
            DB::table('tag_notifications')->insert(['user_id' => $user->id, 'key' => mb_substr($key, 0, 120), 'sent_at' => now()]);

            return true;
        } catch (Throwable) {
            return false; // schon gesendet (eindeutiger Schlüssel)
        }
    }

    /**
     * Läuft alle 5 Minuten (auto-deploy.sh → tag:takt): Erinnerungen aufs Handy, montags ab 07:30 der Wochenüberblick.
     *
     * @return array{push: int, week: int}
     */
    public static function tick(?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now', MeinTag::tz());
        $out = ['push' => 0, 'week' => 0];
        if (! Schema::hasTable('tag_notifications') || ! Schema::hasTable('personal_items')) {
            return $out;
        }
        $today = $now->format('Y-m-d');
        $users = User::query()->where('is_active', true)->get();

        // Erinnerungen aufs Handy: alles, was jetzt fällig ist, genau einmal
        $withPush = Push::$fake !== null ? $users : $users->filter(fn ($u) => Push::hasDevice($u));
        foreach ($withPush as $u) {
            foreach (MeinTag::dueReminders($u, $now) as $i) {
                $day = $i->due_on instanceof \DateTimeInterface ? $i->due_on->format('Y-m-d') : (string) $i->due_on;
                if (! self::once($u, 'erinnerung:'.$i->id.':'.$day)) {
                    continue;
                }
                $out['push'] += Push::toUser($u, [
                    'title' => ($i->start_time ? $i->start_time.' Uhr – ' : '').$i->title,
                    'body' => $i->kind === 'todo' ? 'To-do'.($day !== $today ? ' am '.date('d.m.', strtotime($day)) : ' heute') : 'Termin'.($day !== $today ? ' am '.date('d.m.', strtotime($day)) : ' heute'),
                    'url' => route('tag.index', ['datum' => $day]),
                    'tag' => 'erinnerung-'.$i->id,
                ]) > 0 ? 1 : 0;
            }
        }

        // Wochenüberblick: montags ab 07:30 (bis zum Abend nachholen, falls der Server kurz weg war)
        if ($now->format('N') === '1' && $now->format('H:i') >= self::SEND_AT && $now->format('H') < '20') {
            foreach ($users as $u) {
                $wantsMail = (bool) ($u->week_mail ?? false) && MailService::validAddress((string) $u->email) && (MailService::$fake !== null || MailSettings::configured());
                $wantsPush = Push::$fake !== null || Push::hasDevice($u);
                if ((! $wantsMail && ! $wantsPush) || ! self::once($u, 'woche:'.$today)) {
                    continue;
                }
                $o = self::overview($u, $today);
                if ($wantsMail) {
                    try {
                        MailService::send([$u->email], 'Wochenüberblick KW '.$o['kw'], self::mailBody($u, $o), [], ['kind' => 'hinweis'], null, false);
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
                if ($wantsPush) {
                    $open = ($o['rent']['open'] ?? 0) + $o['overdue'] + $o['bank_open'];
                    Push::toUser($u, [
                        'title' => 'Wochenüberblick KW '.$o['kw'],
                        'body' => $o['todos_done'].' erledigt · '.$o['payments'].' Zahlungen ('.self::money($o['payments_sum']).') · '.$open.' offen · '.$o['week_count'].' Einträge diese Woche',
                        'url' => route('tag.week'),
                        'tag' => 'woche',
                    ]);
                }
                $out['week']++;
            }
        }

        return $out;
    }
}
