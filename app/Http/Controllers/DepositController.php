<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\Deposit;
use App\Models\DepositEntry;
use App\Models\Lease;
use App\Support\Audit;
use App\Support\Kaution\Kaution;
use App\Support\Money;
use App\Support\Pdf\HtmlPdf;
use App\Support\Rent\Erhoehung\Regeln;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/** Kautionsverwaltung: Übersicht, Raten, Buchungen, Abrechnung beim Auszug (Paket 34). */
class DepositController extends Controller
{
    use ScopesOwners;

    public function index(Request $request)
    {
        $user = $this->me();
        $tab = in_array($request->query('stand'), ['laufend', 'rueckgabe', 'abgerechnet'], true) ? $request->query('stand') : 'laufend';
        $ownerId = (int) $request->query('eigentuemer') ?: null;
        if ($ownerId) {
            $this->assertOwnerAccess($ownerId);
        }
        $leases = Lease::query()->visibleTo($user)->where('deposit_amount', '>', 0)
            ->when($ownerId, fn ($q) => $q->where('leases.owner_id', $ownerId))->with(['unit.property', 'tenant', 'deposit'])->get();
        $today = date('Y-m-d');
        $rows = [];
        foreach ($leases as $l) {
            $s = Kaution::state($l, $l->deposit, $today);
            $ended = $l->end_date !== null && $l->end_date->format('Y-m-d') < $today;
            $kind = $s['settled'] ? 'abgerechnet' : ($ended ? 'rueckgabe' : 'laufend');
            if ($kind !== $tab) {
                continue;
            }
            $rows[] = ['lease' => $l, 's' => $s, 'ended' => $ended];
        }
        usort($rows, fn ($a, $b) => ($b['s']['overdue'] <=> $a['s']['overdue']) ?: strcmp((string) $a['lease']->title(), (string) $b['lease']->title()));

        return view('deposits.index', ['rows' => $rows, 'tab' => $tab, 'owners' => $this->visibleOwners()->get(['owners.id', 'owners.name']), 'ownerId' => $ownerId, 'today' => $today]);
    }

    public function show(Lease $lease)
    {
        $this->assertOwnerAccess((int) $lease->owner_id);
        $lease->load(['unit.property', 'tenant', 'deposit.entries']);

        return view('deposits.show', $this->data($lease));
    }

    public function update(Request $request, Lease $lease)
    {
        $this->assertOwnerAccess((int) $lease->owner_id);
        $d = $request->validate([
            'form' => ['required', Rule::in(array_keys(Deposit::FORMS))], 'institution' => ['nullable', 'string', 'max:150'], 'account_note' => ['nullable', 'string', 'max:190'],
            'interest_rate' => ['nullable', 'string', 'max:10'], 'installments' => ['required', 'integer', 'between:1,3'], 'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $rate = trim((string) ($d['interest_rate'] ?? '')) === '' ? null : str_replace(',', '.', $d['interest_rate']);
        if ($rate !== null && (! is_numeric($rate) || (float) $rate < 0 || (float) $rate > 15)) {
            return back()->withErrors(['interest_rate' => 'Bitte einen Zinssatz zwischen 0 und 15 Prozent eingeben.'])->withInput();
        }
        $dep = Kaution::forLease($lease);
        $dep->update(['form' => $d['form'], 'institution' => $d['institution'] ?? null, 'account_note' => $d['account_note'] ?? null, 'interest_rate' => $rate, 'installments' => (int) $d['installments'], 'note' => $d['note'] ?? null]);
        Audit::log('deposit.updated', $dep, 'Kautionsangaben gespeichert: '.$lease->title());

        return redirect()->route('deposit.show', $lease)->with('status', 'Gespeichert.');
    }

    public function entryStore(Request $request, Lease $lease)
    {
        $this->assertOwnerAccess((int) $lease->owner_id);
        $d = $request->validate(['entry_on' => ['required', 'date_format:Y-m-d'], 'kind' => ['required', Rule::in(array_keys(Deposit::KINDS))], 'amount' => ['required', 'string', 'max:20'], 'note' => ['nullable', 'string', 'max:190']]);
        $amount = Money::parse($d['amount']);
        if ($amount === null || (float) $amount <= 0) {
            return back()->withErrors(['amount' => 'Bitte einen gültigen Betrag eingeben (zum Beispiel 600,00).'])->withInput();
        }
        try {
            Kaution::addEntry(Kaution::forLease($lease), $d['entry_on'], $d['kind'], Regeln::cents($amount), $d['note'] ?? null, $this->me());
        } catch (RuntimeException $e) {
            return back()->withErrors(['entry' => $e->getMessage()])->withInput();
        }

        return redirect()->route('deposit.show', $lease)->with('status', 'Die Buchung ist gespeichert.');
    }

    public function entryDestroy(Lease $lease, DepositEntry $entry)
    {
        $this->assertOwnerAccess((int) $lease->owner_id);
        $dep = Deposit::query()->where('lease_id', $lease->id)->firstOrFail();
        abort_unless($entry->deposit_id === $dep->id, 404);
        if ($dep->status === 'abgerechnet') {
            return back()->withErrors(['entry' => 'Die Kaution ist abgerechnet. Zum Ändern zuerst die Abrechnung zurücknehmen.']);
        }
        $entry->delete();
        Audit::log('deposit.entry_removed', $dep, 'Kautionsbuchung gelöscht: '.$lease->title());

        return back()->with('status', 'Die Buchung ist gelöscht.');
    }

    public function import(Lease $lease)
    {
        $this->assertOwnerAccess((int) $lease->owner_id);
        $dep = Kaution::forLease($lease);
        if ($lease->deposit_amount === null || $lease->deposit_received_on === null || $dep->entries()->exists()) {
            return back()->withErrors(['entry' => 'Beim Vertrag fehlt der Betrag oder das Datum, oder es gibt schon Buchungen.']);
        }
        Kaution::addEntry($dep, $lease->deposit_received_on->format('Y-m-d'), 'einzahlung', Regeln::cents($lease->deposit_amount), 'Übernommen aus dem Mietvertrag', $this->me());

        return back()->with('status', 'Die Zahlung aus dem Mietvertrag ist übernommen.');
    }

    public function preview(Request $request, Lease $lease)
    {
        $this->assertOwnerAccess((int) $lease->owner_id);
        $lease->load(['unit.property', 'tenant', 'deposit.entries']);
        [$on, $w, $err] = $this->settleInput($request);

        return view('deposits.show', array_merge($this->data($lease), ['preview' => Kaution::settlement(Kaution::state($lease, $lease->deposit, $on)['held'], $w), 'form' => ['on' => $on, 'lines' => $request->input('lines', [])], 'settleError' => $err]));
    }

    public function settle(Request $request, Lease $lease)
    {
        $this->assertOwnerAccess((int) $lease->owner_id);
        [$on, $w, $err] = $this->settleInput($request);
        if ($err) {
            return back()->withErrors(['settle' => $err])->withInput();
        }
        $dep = Kaution::forLease($lease);
        try {
            $res = Kaution::settle($dep, $on, $w, $this->me());
        } catch (RuntimeException $e) {
            return back()->withErrors(['settle' => $e->getMessage()])->withInput();
        }

        return redirect()->route('deposit.show', $lease)->with('status', 'Die Kaution ist abgerechnet.'.($res['claim'] > 0 ? ' Es bleibt eine Nachforderung von '.Regeln::eur($res['claim']).'.' : ''));
    }

    public function reopen(Lease $lease)
    {
        $this->assertOwnerAccess((int) $lease->owner_id);
        try {
            Kaution::reopen(Kaution::forLease($lease), $this->me());
        } catch (RuntimeException $e) {
            return back()->withErrors(['settle' => $e->getMessage()]);
        }

        return back()->with('status', 'Die Abrechnung ist zurückgenommen.');
    }

    public function letter(Lease $lease)
    {
        $this->assertOwnerAccess((int) $lease->owner_id);

        return view('deposits.letter', $this->letterData($lease));
    }

    public function pdf(Lease $lease)
    {
        $this->assertOwnerAccess((int) $lease->owner_id);
        $html = view('deposits.letter', $this->letterData($lease))->render();
        Audit::log('deposit.pdf', $lease, 'Kautionsabrechnung als PDF heruntergeladen: '.$lease->title());

        return response(HtmlPdf::render($html, 'Kautionsabrechnung'), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="Kautionsabrechnung.pdf"', 'Cache-Control' => 'private, no-store']);
    }

    // ------------------------------------------------------------------ Hilfen

    private function data(Lease $lease): array
    {
        $dep = $lease->deposit;

        return [
            'lease' => $lease, 'd' => $dep ?? new Deposit(['form' => 'konto', 'installments' => 1, 'status' => 'offen']), 's' => Kaution::state($lease, $dep),
            'claims' => Kaution::openClaims($lease), 'entries' => $dep ? $dep->entries : collect(), 'preview' => null, 'form' => null, 'settleError' => null,
        ];
    }

    private function letterData(Lease $lease): array
    {
        $lease->load(['unit.property', 'tenant', 'deposit.entries', 'owner']);
        abort_if($lease->deposit === null || $lease->deposit->status !== 'abgerechnet', 404);

        return ['lease' => $lease, 'd' => $lease->deposit, 's' => Kaution::state($lease, $lease->deposit, $lease->deposit->settled_on->format('Y-m-d')), 'entries' => $lease->deposit->entries, 'owner' => $lease->owner];
    }

    /** @return array{0: string, 1: array<int, array{reason: string, cents: int}>, 2: ?string} */
    private function settleInput(Request $request): array
    {
        $on = (string) $request->input('settled_on', date('Y-m-d'));
        $err = null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $on) !== 1) {
            $on = date('Y-m-d');
            $err = 'Bitte ein gültiges Datum angeben.';
        }
        $w = [];
        foreach ((array) $request->input('lines', []) as $line) {
            $reason = trim((string) ($line['reason'] ?? ''));
            $raw = trim((string) ($line['amount'] ?? ''));
            if ($reason === '' && $raw === '') {
                continue;
            }
            $amt = Money::parse($raw);
            if ($reason === '' || $amt === null || (float) $amt <= 0) {
                $err = 'Jeder Einbehalt braucht einen Grund und einen Betrag.';

                continue;
            }
            $w[] = ['reason' => $reason, 'cents' => Regeln::cents($amt)];
        }

        return [$on, $w, $err];
    }
}
