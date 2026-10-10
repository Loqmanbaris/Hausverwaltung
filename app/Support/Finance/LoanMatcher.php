<?php

namespace App\Support\Finance;

use App\Models\BankTransaction;
use App\Models\Loan;
use App\Models\LoanEvent;
use App\Models\User;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Abbuchungen auf dem Konto den Darlehen zuordnen (Paket 23). */
class LoanMatcher
{
    /** Ziffern einer Darlehensnummer (mind. 5), um sie im Verwendungszweck zu finden. */
    private static function digits(?string $s): string
    {
        return (string) preg_replace('/\D+/', '', (string) $s);
    }

    /**
     * Passende Darlehen für eine Abbuchung mit Bewertung.
     *
     * @return array<int, array{loan: Loan, score: int, reasons: string[], expected: ?float}>
     */
    public static function suggestions(BankTransaction $tx, ?Collection $loans = null): array
    {
        if ((float) $tx->amount >= 0) {
            return [];
        }
        $loans ??= Loan::query()->where('owner_id', $tx->owner_id)->where('status', 'aktiv')->with('events')->get();
        $amount = abs((float) $tx->amount);
        $purpose = self::digits($tx->purpose.' '.$tx->end_to_end.' '.$tx->mandate_ref);
        $out = [];
        foreach ($loans as $loan) {
            $score = 0;
            $reasons = [];
            if ($loan->lender_iban_hash && $tx->counterparty_iban_hash && hash_equals($loan->lender_iban_hash, $tx->counterparty_iban_hash)) {
                $score += 2;
                $reasons[] = 'IBAN der Bank';
            }
            $acc = self::digits($loan->account_no);
            if (strlen($acc) >= 5 && str_contains($purpose, $acc)) {
                $score += 2;
                $reasons[] = 'Darlehensnummer im Verwendungszweck';
            }
            $exp = LoanPlan::expectedAround($loan, $tx->booking_date->format('Y-m-d'), 20);
            if ($exp && abs($exp['payment'] - $amount) <= max(1.0, $exp['payment'] * 0.01)) {
                $score += 2;
                $reasons[] = 'Betrag passt zur Rate';
            }
            if ($score >= 2) {
                $out[] = ['loan' => $loan, 'score' => $score, 'reasons' => $reasons, 'expected' => $exp['payment'] ?? null];
            }
        }
        usort($out, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $out;
    }

    /** Sicher = Bank oder Darlehensnummer erkannt UND Betrag passt, und nur ein Darlehen kommt in Frage. */
    public static function sure(array $suggestions): ?Loan
    {
        if ($suggestions === [] || $suggestions[0]['score'] < 4 || ! in_array('Betrag passt zur Rate', $suggestions[0]['reasons'], true)) {
            return null;
        }
        if (count($suggestions) > 1 && $suggestions[1]['score'] >= $suggestions[0]['score']) {
            return null;
        }

        return $suggestions[0]['loan'];
    }

    public static function assign(BankTransaction $tx, Loan $loan, ?User $user, string $how = 'von Hand'): LoanEvent
    {
        if ((float) $tx->amount >= 0 || $tx->status !== 'ausgabe' || (int) $tx->owner_id !== (int) $loan->owner_id) {
            throw new RuntimeException('Diese Abbuchung kann nicht zugeordnet werden.');
        }
        if (DB::table('bank_transaction_receipts')->where('bank_transaction_id', $tx->id)->exists()) {
            throw new RuntimeException('Zu dieser Abbuchung gibt es schon einen Beleg. Bitte zuerst zurücksetzen.');
        }

        return DB::transaction(function () use ($tx, $loan, $user, $how) {
            $e = LoanEvent::create([
                'loan_id' => $loan->id, 'type' => 'zahlung', 'date' => $tx->booking_date->format('Y-m-d'), 'amount' => abs((float) $tx->amount),
                'bank_transaction_id' => $tx->id, 'note' => mb_substr((string) $tx->purpose, 0, 190) ?: null, 'created_by' => $user?->id,
            ]);
            $tx->update(['status' => 'darlehen', 'status_note' => mb_substr('Darlehen: '.$loan->name, 0, 190)]);
            Audit::log('loan.payment', $tx, 'Abbuchung vom '.$tx->booking_date->format('d.m.Y').' ('.Money::format($tx->amount).') dem Darlehen „'.$loan->name.'“ zugeordnet ('.$how.')', label: $user ? null : 'System');

            return $e;
        });
    }

    public static function unassign(BankTransaction $tx): void
    {
        DB::transaction(function () use ($tx) {
            LoanEvent::query()->where('bank_transaction_id', $tx->id)->delete();
            $tx->update(['status' => 'ausgabe', 'status_note' => null]);
        });
        Audit::log('loan.payment_reset', $tx, 'Darlehenszuordnung zurückgesetzt: Abbuchung vom '.$tx->booking_date->format('d.m.Y').' ('.Money::format($tx->amount).')');
    }

    /** Offene Abbuchungen automatisch zuordnen, wenn es sicher ist. @return int Anzahl */
    public static function autoAssign(?int $ownerId = null, ?array $txIds = null): int
    {
        $q = BankTransaction::query()->where('status', 'ausgabe')->where('amount', '<', 0)
            ->when($ownerId, fn ($w) => $w->where('owner_id', $ownerId))
            ->when($txIds !== null, fn ($w) => $w->whereIn('id', $txIds))
            ->whereNotExists(fn ($w) => $w->select(DB::raw(1))->from('bank_transaction_receipts')->whereColumn('bank_transaction_receipts.bank_transaction_id', 'bank_transactions.id'));
        $loansByOwner = [];
        $n = 0;
        foreach ($q->orderBy('booking_date')->limit(2000)->get() as $tx) {
            $loansByOwner[$tx->owner_id] ??= Loan::query()->where('owner_id', $tx->owner_id)->where('status', 'aktiv')->with('events')->get();
            if ($loansByOwner[$tx->owner_id]->isEmpty()) {
                continue;
            }
            $loan = self::sure(self::suggestions($tx, $loansByOwner[$tx->owner_id]));
            if ($loan) {
                self::assign($tx, $loan, null, 'automatisch');
                $n++;
            }
        }

        return $n;
    }
}
