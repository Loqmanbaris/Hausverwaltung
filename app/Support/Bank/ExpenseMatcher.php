<?php

namespace App\Support\Bank;

use App\Models\BankTransaction;
use App\Models\Receipt;
use App\Models\RecurringExpense;
use App\Models\User;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Abgleich der Abbuchungen mit Belegen (Paket 9).
 * Punkte: Rechnungsnummer im Verwendungszweck 40, Betrag gleich 50, Lieferant im Namen oder Zweck 30, Datum bis 10 Tage 10.
 * Ab 80 Punkten (z. B. Betrag und Lieferant) und eindeutig gilt ein Vorschlag als sicher.
 */
class ExpenseMatcher
{
    public const SURE = 80;

    public const NO_RECEIPT = [
        'umbuchung' => 'Umbuchung zwischen eigenen Konten',
        'privat' => 'Privat (keine Vermietung)',
        'tilgung' => 'Darlehenstilgung (keine Werbungskosten)',
        'kontoauszug' => 'Kontoauszug genügt als Beleg (z. B. Bankgebühren)',
        'sonstiges' => 'Sonstiges',
    ];

    /** Schon verknüpfter Betrag eines Belegs (in Cent). */
    private static function linkedCents(array $receiptIds): array
    {
        return DB::table('bank_transaction_receipts')->whereIn('receipt_id', $receiptIds)
            ->selectRaw('receipt_id, sum(amount) as s')->groupBy('receipt_id')->pluck('s', 'receipt_id')
            ->map(fn ($s) => (int) round((float) $s * 100))->all();
    }

    /**
     * @return array<int, array{receipt: Receipt, score: int, reasons: string[], open: int}>
     */
    public static function suggestions(BankTransaction $tx): array
    {
        $date = $tx->booking_date->format('Y-m-d');
        $amount = (int) round(abs((float) $tx->amount) * 100);
        $hay = ' '.BankMatcher::normalize(($tx->counterparty_name ?? '').' '.($tx->purpose ?? '')).' ';
        $hayCompact = preg_replace('/[^a-z0-9]/', '', strtolower((string) $tx->purpose));

        $receipts = Receipt::query()
            ->where('owner_id', $tx->owner_id)
            ->whereNotNull('amount_gross')
            ->where('amount_gross', '>', 0)
            ->whereBetween('receipt_date', [date('Y-m-d', strtotime($date.' -75 days')), date('Y-m-d', strtotime($date.' +20 days'))])
            ->with('property')
            ->get();
        $linked = self::linkedCents($receipts->pluck('id')->all());

        $out = [];
        foreach ($receipts as $r) {
            $total = (int) round((float) $r->amount_gross * 100);
            $open = $total - ($linked[$r->id] ?? 0);
            if ($open <= 0) {
                continue;
            }
            $score = 0;
            $reasons = [];
            $inv = preg_replace('/[^a-z0-9]/', '', strtolower((string) $r->invoice_number));
            if (strlen((string) $inv) >= 4 && str_contains((string) $hayCompact, (string) $inv)) {
                $score += 40;
                $reasons[] = 'Rechnungsnummer';
            }
            if ($open === $amount || $total === $amount) {
                $score += 50;
                $reasons[] = 'Betrag gleich';
            }
            $vendorHit = array_filter(BankMatcher::tokens($r->vendor_name), fn ($t) => mb_strlen($t) >= 4 && str_contains($hay, ' '.$t.' '));
            if ($vendorHit !== []) {
                $score += 30;
                $reasons[] = 'Lieferant';
            }
            $days = abs((int) (new \DateTimeImmutable($date))->diff(new \DateTimeImmutable($r->receipt_date->format('Y-m-d')))->days);
            if ($days <= 10) {
                $score += 10;
                $reasons[] = 'Datum nah';
            }
            if ($score >= 50) {
                $out[] = ['receipt' => $r, 'score' => $score, 'reasons' => $reasons, 'open' => $open];
            }
        }
        usort($out, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $out;
    }

    /** Passende wiederkehrende Ausgabe (Lieferant im Namen oder Zweck), um einen fehlenden Beleg vorzubelegen. */
    public static function recurring(BankTransaction $tx): ?RecurringExpense
    {
        $hay = ' '.BankMatcher::normalize(($tx->counterparty_name ?? '').' '.($tx->purpose ?? '')).' ';
        foreach (RecurringExpense::query()->where('owner_id', $tx->owner_id)->where('active', true)->get() as $e) {
            $hit = array_filter(BankMatcher::tokens($e->vendor_name), fn ($t) => mb_strlen($t) >= 4 && str_contains($hay, ' '.$t.' '));
            if ($hit !== []) {
                return $e;
            }
        }

        return null;
    }

    public static function isSure(array $suggestions): bool
    {
        return $suggestions !== []
            && $suggestions[0]['score'] >= self::SURE
            && (count($suggestions) === 1 || $suggestions[1]['score'] < $suggestions[0]['score']);
    }

    /** Noch nicht mit Belegen belegter Betrag einer Abbuchung (Cent, positiv). */
    public static function remainingCents(BankTransaction $tx): int
    {
        $linked = (float) DB::table('bank_transaction_receipts')->where('bank_transaction_id', $tx->id)->sum('amount');

        return (int) round(abs((float) $tx->amount) * 100) - (int) round($linked * 100);
    }

    /** @throws RuntimeException */
    public static function link(BankTransaction $tx, Receipt $receipt, ?int $cents, User $user): void
    {
        if ((float) $tx->amount >= 0 || ! in_array($tx->status, ['ausgabe', 'beleg'], true)) {
            throw new RuntimeException('Nur offene Abbuchungen können mit Belegen verknüpft werden.');
        }
        if ((int) $receipt->owner_id !== (int) $tx->owner_id) {
            throw new RuntimeException('Der Beleg gehört zu einem anderen Eigentümer.');
        }
        $remaining = self::remainingCents($tx);
        $cents ??= $remaining;
        if ($cents <= 0 || $cents > $remaining) {
            throw new RuntimeException('Der Betrag muss größer als 0 und höchstens '.Money::format($remaining / 100).' sein.');
        }
        if (DB::table('bank_transaction_receipts')->where('bank_transaction_id', $tx->id)->where('receipt_id', $receipt->id)->exists()) {
            throw new RuntimeException('Dieser Beleg ist schon mit der Abbuchung verknüpft.');
        }

        DB::transaction(function () use ($tx, $receipt, $cents, $user) {
            DB::table('bank_transaction_receipts')->insert([
                'bank_transaction_id' => $tx->id,
                'receipt_id' => $receipt->id,
                'amount' => number_format($cents / 100, 2, '.', ''),
                'created_by' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            self::refreshStatus($tx);
            Audit::log('bank.receipt_linked', $tx, 'Abbuchung vom '.$tx->booking_date->format('d.m.Y').' ('.Money::format($tx->amount).') mit Beleg Nr. '.$receipt->id.' verknüpft');
        });
    }

    public static function markNoReceipt(BankTransaction $tx, string $reason, string $note): void
    {
        if ((float) $tx->amount >= 0 || DB::table('bank_transaction_receipts')->where('bank_transaction_id', $tx->id)->exists()) {
            throw new RuntimeException('Zu dieser Abbuchung gibt es schon einen Beleg. Bitte zuerst zurücksetzen.');
        }
        $label = self::NO_RECEIPT[$reason] ?? self::NO_RECEIPT['sonstiges'];
        $tx->update(['status' => 'kein_beleg', 'status_note' => mb_substr($label.($note !== '' ? ': '.$note : ''), 0, 190)]);
        Audit::log('bank.no_receipt', $tx, 'Abbuchung vom '.$tx->booking_date->format('d.m.Y').' ('.Money::format($tx->amount).') ohne Beleg: '.$label);
    }

    public static function reset(BankTransaction $tx): void
    {
        DB::transaction(function () use ($tx) {
            DB::table('bank_transaction_receipts')->where('bank_transaction_id', $tx->id)->delete();
            $tx->update(['status' => 'ausgabe', 'status_note' => null]);
            Audit::log('bank.receipt_reset', $tx, 'Belegzuordnung zurückgesetzt: Abbuchung vom '.$tx->booking_date->format('d.m.Y').' ('.Money::format($tx->amount).')');
        });
    }

    public static function refreshStatus(BankTransaction $tx): void
    {
        if ((float) $tx->amount >= 0 || $tx->status === 'kein_beleg') {
            return;
        }
        $status = self::remainingCents($tx) <= 0 ? 'beleg' : 'ausgabe';
        if ($status !== $tx->status) {
            $tx->update(['status' => $status]);
        }
    }

    public static function openCountFor(User $user): int
    {
        try {
            return (int) BankTransaction::visibleTo($user)->where('status', 'ausgabe')->count();
        } catch (Throwable) {
            return 0;
        }
    }
}
