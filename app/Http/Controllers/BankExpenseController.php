<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\BankTransaction;
use App\Models\OwnerBankAccount;
use App\Models\Receipt;
use App\Support\Bank\ExpenseMatcher;
use App\Support\Bank\Monatsfilter;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

/** Abbuchungen aus dem Kontoauszug mit Belegen abgleichen (Paket 9). */
class BankExpenseController extends Controller
{
    use ScopesOwners;

    public function index(Request $request)
    {
        $ownerIds = $this->visibleOwnerIds();
        $state = (string) $request->query('stand', 'offen');
        $accountId = (int) $request->query('konto');
        $month = Monatsfilter::current($request);

        $query = Monatsfilter::apply(BankTransaction::query()->whereIn('owner_id', $ownerIds), $month)->where('amount', '<', 0)
            ->when($accountId > 0, fn ($q) => $q->where('owner_bank_account_id', $accountId));
        match ($state) {
            'zugeordnet' => $query->where('status', 'beleg'),
            'kein_beleg' => $query->where('status', 'kein_beleg'),
            'darlehen' => $query->where('status', 'darlehen'),
            'alle' => $query,
            default => $query->where('status', 'ausgabe'),
        };
        $txs = $query->with('owner')->orderByDesc('booking_date')->orderByDesc('id')->limit(300)->get();

        $links = DB::table('bank_transaction_receipts as l')->join('receipts as r', 'r.id', '=', 'l.receipt_id')
            ->whereIn('l.bank_transaction_id', $txs->pluck('id'))
            ->get(['l.bank_transaction_id', 'l.receipt_id', 'l.amount', 'r.vendor_name', 'r.receipt_date'])->groupBy('bank_transaction_id');

        $loans = \Illuminate\Support\Facades\Schema::hasTable('loans')
            ? \App\Models\Loan::query()->whereIn('owner_id', $ownerIds)->where('status', 'aktiv')->with('events')->orderBy('name')->get()->groupBy('owner_id')
            : collect();
        $rows = [];
        foreach ($txs as $tx) {
            $s = $tx->status === 'ausgabe' ? ExpenseMatcher::suggestions($tx) : [];
            $rows[] = [
                'tx' => $tx,
                'suggestions' => $s,
                'sure' => ExpenseMatcher::isSure($s) && ExpenseMatcher::remainingCents($tx) === (int) round(abs((float) $tx->amount) * 100),
                'remaining' => $tx->status === 'ausgabe' ? ExpenseMatcher::remainingCents($tx) : 0,
                'recurring' => $tx->status === 'ausgabe' && $s === [] ? ExpenseMatcher::recurring($tx) : null,
                'links' => $links->get($tx->id, collect()),
                'loans' => $loans->get($tx->owner_id, collect()),
                'loanSug' => $tx->status === 'ausgabe' && $loans->has($tx->owner_id) ? (\App\Support\Finance\LoanMatcher::suggestions($tx, $loans->get($tx->owner_id))[0] ?? null) : null,
            ];
        }

        $counts = Monatsfilter::apply(BankTransaction::query()->whereIn('owner_id', $ownerIds), $month)->where('amount', '<', 0)
            ->when($accountId > 0, fn ($q) => $q->where('owner_bank_account_id', $accountId))
            ->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $accounts = OwnerBankAccount::query()->whereIn('owner_id', $ownerIds)->with('owner')->get();

        $months = Monatsfilter::options($ownerIds, $month);

        return view('bank.expenses', compact('rows', 'state', 'accountId', 'counts', 'accounts', 'month', 'months'));
    }

    public function link(Request $request, BankTransaction $tx)
    {
        $this->assertOwnerAccess((int) $tx->owner_id);
        $data = $request->validate([
            'receipt_id' => ['required', 'integer'],
            'amount' => ['nullable', 'string', 'max:20'],
        ], ['receipt_id.*' => 'Bitte einen Beleg wählen oder die Beleg-Nr. eintragen.']);

        $receipt = Receipt::query()->find((int) $data['receipt_id']);
        if ($receipt === null || ! in_array((int) $receipt->owner_id, $this->visibleOwnerIds(), true)) {
            return $this->back($request, $tx)->withErrors(['tx' => 'Beleg Nr. '.(int) $data['receipt_id'].' wurde nicht gefunden.']);
        }
        $cents = null;
        if (trim((string) ($data['amount'] ?? '')) !== '') {
            $m = Money::parse($data['amount']);
            if ($m === null) {
                return $this->back($request, $tx)->withErrors(['tx' => 'Der Betrag ist ungültig.']);
            }
            $cents = (int) round((float) $m * 100);
        }
        try {
            ExpenseMatcher::link($tx, $receipt, $cents, $this->me());
        } catch (RuntimeException $e) {
            return $this->back($request, $tx)->withErrors(['tx' => $e->getMessage()]);
        }

        return $this->back($request, $tx)->with('status', 'Abbuchung vom '.$tx->booking_date->format('d.m.Y').' mit Beleg Nr. '.$receipt->id.' verknüpft.');
    }

    public function acceptSure(Request $request)
    {
        $done = 0;
        foreach (BankTransaction::query()->whereIn('id', array_map('intval', (array) $request->input('ids', [])))
            ->whereIn('owner_id', $this->visibleOwnerIds())->where('status', 'ausgabe')->get() as $tx) {
            $s = ExpenseMatcher::suggestions($tx);
            if (! ExpenseMatcher::isSure($s) || $s[0]['open'] !== ExpenseMatcher::remainingCents($tx)) {
                continue;
            }
            try {
                ExpenseMatcher::link($tx, $s[0]['receipt'], null, $this->me());
                $done++;
            } catch (RuntimeException) {
                continue;
            }
        }

        return redirect()->route('bank.expenses', array_filter(['konto' => $request->integer('konto') ?: null]))
            ->with('status', $done.' Abbuchung'.($done === 1 ? '' : 'en').' mit Belegen verknüpft.');
    }

    public function noReceipt(Request $request, BankTransaction $tx)
    {
        $this->assertOwnerAccess((int) $tx->owner_id);
        $data = $request->validate(['reason' => ['required', Rule::in(array_keys(ExpenseMatcher::NO_RECEIPT))], 'note' => ['nullable', 'string', 'max:150']]);
        try {
            ExpenseMatcher::markNoReceipt($tx, $data['reason'], trim((string) ($data['note'] ?? '')));
        } catch (RuntimeException $e) {
            return $this->back($request, $tx)->withErrors(['tx' => $e->getMessage()]);
        }

        return $this->back($request, $tx)->with('status', 'Abbuchung vom '.$tx->booking_date->format('d.m.Y').' als „ohne Beleg“ markiert.');
    }

    public function reset(Request $request, BankTransaction $tx)
    {
        $this->assertOwnerAccess((int) $tx->owner_id);
        abort_unless((float) $tx->amount < 0, 404);
        ExpenseMatcher::reset($tx);

        return $this->back($request, $tx)->with('status', 'Abbuchung ist wieder offen.');
    }

    private function back(Request $request, BankTransaction $tx)
    {
        return redirect()->to(route('bank.expenses', array_filter(['stand' => $request->input('stand') ?: null, 'konto' => $request->input('konto') ?: null])).'#abbuchung-'.$tx->id);
    }
}
