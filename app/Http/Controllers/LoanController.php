<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\BankTransaction;
use App\Models\Loan;
use App\Models\LoanEvent;
use App\Models\Property;
use App\Models\PropertyValue;
use App\Support\Audit;
use App\Support\Bank\BankMatcher;
use App\Support\Finance\Kennzahlen;
use App\Support\Finance\LoanMatcher;
use App\Support\Finance\LoanPlan;
use App\Support\Money;
use App\Support\Stammdaten;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/** Finanzierung: Übersicht, Darlehen, Ereignisse, Werte je Objekt, Kontoabgleich (Paket 23). */
class LoanController extends Controller
{
    use ScopesOwners;

    private static function pct(mixed $v): ?float
    {
        $v = trim(str_replace([' ', '%'], '', (string) $v));
        if ($v === '') {
            return null;
        }
        $v = str_replace(',', '.', $v);

        return is_numeric($v) ? round((float) $v, 3) : -1.0;
    }

    public function index(Request $request)
    {
        $year = (int) $request->query('jahr', date('Y'));
        $year = max(2000, min((int) date('Y') + 1, $year));
        $ownerId = (int) $request->query('eigentuemer') ?: null;
        if ($ownerId) {
            $this->assertOwnerAccess($ownerId);
        }
        $data = Kennzahlen::overview($this->me(), $year, $ownerId);
        $loans = Loan::visibleTo($this->me())->when($ownerId, fn ($q) => $q->where('loans.owner_id', $ownerId))->with(['property', 'owner', 'events'])->orderBy('status')->orderBy('name')->get();

        return view('finance.index', [
            'data' => $data, 'loans' => $loans, 'year' => $year, 'ownerId' => $ownerId,
            'owners' => $this->visibleOwners()->get(['owners.id', 'owners.name']),
            'ending' => Kennzahlen::fixedEnding($this->me(), 24, $ownerId),
        ]);
    }

    public function create(Request $request)
    {
        $loan = new Loan(['kind' => 'annuitaet', 'interval_months' => 1, 'property_id' => (int) $request->query('objekt') ?: null]);
        if ($loan->property_id) {
            $p = Property::query()->find($loan->property_id);
            abort_if($p === null, 404);
            $this->assertOwnerAccess((int) $p->owner_id);
            $loan->owner_id = $p->owner_id;
        }

        return view('finance.loan-form', $this->formData($loan));
    }

    private function formData(Loan $loan): array
    {
        return [
            'loan' => $loan,
            'owners' => $this->visibleOwners()->get(['owners.id', 'owners.name']),
            'properties' => Property::visibleTo($this->me())->orderBy('name')->get(['properties.id', 'properties.name', 'properties.owner_id']),
        ];
    }

    private function validated(Request $request, ?Loan $loan = null): array
    {
        $data = $request->validate([
            'owner_id' => ['required', 'integer'],
            'property_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:120'],
            'lender' => ['nullable', 'string', 'max:120'],
            'account_no' => ['nullable', 'string', 'max:60'],
            'lender_iban' => ['nullable', 'string', 'max:40'],
            'kind' => ['required', Rule::in(array_keys(Loan::KINDS))],
            'principal' => ['required', 'string', 'max:20'],
            'paid_out_on' => ['nullable', 'date'],
            'interest_rate' => ['required', 'string', 'max:10'],
            'installment' => ['nullable', 'string', 'max:20'],
            'initial_repayment' => ['nullable', 'string', 'max:10'],
            'interval_months' => ['required', Rule::in(array_keys(Loan::INTERVALS))],
            'first_payment_on' => ['required', 'date'],
            'fixed_until' => ['nullable', 'date'],
            'free_until' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'special_repayment_pct' => ['nullable', 'string', 'max:10'],
            'status' => ['nullable', Rule::in(['aktiv', 'getilgt'])],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [
            'name.required' => 'Bitte eine Bezeichnung eingeben, z. B. „Sparkasse Hauptstraße“.',
            'principal.required' => 'Bitte den Darlehensbetrag eingeben.',
            'interest_rate.required' => 'Bitte den Zinssatz eingeben (0 bei zinslosen Privatdarlehen).',
            'first_payment_on.required' => 'Bitte das Datum der ersten Rate eingeben.',
        ]);
        $this->assertOwnerAccess((int) $data['owner_id']);
        if (! empty($data['property_id'])) {
            $p = Property::query()->find((int) $data['property_id']);
            if ($p === null || (int) $p->owner_id !== (int) $data['owner_id']) {
                throw \Illuminate\Validation\ValidationException::withMessages(['property_id' => 'Das Objekt gehört nicht zu diesem Eigentümer.']);
            }
        }
        $errors = [];
        $principal = Money::parse($data['principal']);
        if ($principal === null || (float) $principal <= 0) {
            $errors['principal'] = 'Bitte einen gültigen Darlehensbetrag eingeben.';
        }
        $rate = self::pct($data['interest_rate']);
        if ($rate === null || $rate < 0 || $rate > 25) {
            $errors['interest_rate'] = 'Zinssatz bitte in Prozent, z. B. 3,25.';
        }
        $inst = isset($data['installment']) && trim((string) $data['installment']) !== '' ? Money::parse($data['installment']) : null;
        if (isset($data['installment']) && trim((string) $data['installment']) !== '' && ($inst === null || (float) $inst <= 0)) {
            $errors['installment'] = 'Bitte eine gültige Rate eingeben.';
        }
        $rep = self::pct($data['initial_repayment'] ?? null);
        if ($rep !== null && ($rep < 0 || $rep > 50)) {
            $errors['initial_repayment'] = 'Anfängliche Tilgung bitte in Prozent, z. B. 2.';
        }
        $sp = self::pct($data['special_repayment_pct'] ?? null);
        if ($sp !== null && ($sp < 0 || $sp > 100)) {
            $errors['special_repayment_pct'] = 'Sondertilgung bitte in Prozent pro Jahr, z. B. 5.';
        }
        if (in_array($data['kind'], ['annuitaet'], true) && $inst === null && $rep === null) {
            $errors['installment'] = 'Bitte die Rate oder die anfängliche Tilgung angeben.';
        }
        if ($data['kind'] === 'tilgung' && $inst === null) {
            $errors['installment'] = 'Bitte den Tilgungsbetrag je Zahlung angeben.';
        }
        if ($errors !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }
        $iban = Stammdaten::normalizeIban((string) ($data['lender_iban'] ?? ''));

        return [
            'owner_id' => (int) $data['owner_id'],
            'property_id' => ! empty($data['property_id']) ? (int) $data['property_id'] : null,
            'name' => trim($data['name']),
            'lender' => $data['lender'] ?? null,
            'account_no' => $data['account_no'] ?? null,
            'lender_iban' => $iban !== '' ? $iban : null,
            'lender_iban_hash' => $iban !== '' ? BankMatcher::ibanHash($iban) : null,
            'kind' => $data['kind'],
            'principal' => $principal,
            'paid_out_on' => $data['paid_out_on'] ?? null,
            'interest_rate' => $rate,
            'installment' => $inst,
            'initial_repayment' => $rep,
            'interval_months' => (int) $data['interval_months'],
            'first_payment_on' => $data['first_payment_on'],
            'fixed_until' => $data['fixed_until'] ?? null,
            'free_until' => $data['free_until'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'special_repayment_pct' => $sp,
            'status' => $data['status'] ?? ($loan?->status ?? 'aktiv'),
            'note' => $data['note'] ?? null,
        ];
    }

    public function store(Request $request)
    {
        $loan = Loan::create($this->validated($request) + ['created_by' => $this->me()->id]);
        Audit::log('loan.created', $loan, 'Darlehen angelegt: '.$loan->name);
        $n = LoanMatcher::autoAssign((int) $loan->owner_id);

        return redirect()->route('loans.show', $loan)->with('status', 'Darlehen angelegt.'.($n > 0 ? ' '.$n.' Abbuchung(en) aus den Kontoauszügen wurden zugeordnet.' : ''));
    }

    public function show(Loan $loan)
    {
        $this->assertOwnerAccess((int) $loan->owner_id);
        $loan->load(['events.transaction', 'property', 'owner']);
        $today = date('Y-m-d');
        $schedule = LoanPlan::schedule($loan);

        // Zahlungen laut Konto je Jahr gegenüber Plan
        $paid = $loan->events->where('type', 'zahlung')->groupBy(fn ($e) => (int) $e->date->format('Y'))->map(fn ($g) => round((float) $g->sum('amount'), 2));

        return view('finance.loan-show', [
            'loan' => $loan,
            'schedule' => $schedule,
            'years' => LoanPlan::byYear($loan),
            'paid' => $paid,
            'balance' => LoanPlan::balanceOn($loan, $today),
            'next' => LoanPlan::next($loan, $today),
            'payoff' => LoanPlan::payoffDate($loan),
            'fixedBalance' => $loan->fixed_until ? LoanPlan::balanceOn($loan, $loan->fixed_until->format('Y-m-d')) : null,
            'installment' => LoanPlan::installment($loan),
        ]);
    }

    public function edit(Loan $loan)
    {
        $this->assertOwnerAccess((int) $loan->owner_id);

        return view('finance.loan-form', $this->formData($loan));
    }

    public function update(Request $request, Loan $loan)
    {
        $this->assertOwnerAccess((int) $loan->owner_id);
        $loan->update($this->validated($request, $loan));
        LoanPlan::forget();
        Audit::log('loan.updated', $loan, 'Darlehen geändert: '.$loan->name);
        LoanMatcher::autoAssign((int) $loan->owner_id);

        return redirect()->route('loans.show', $loan)->with('status', 'Gespeichert.');
    }

    public function destroy(Loan $loan)
    {
        $this->assertOwnerAccess((int) $loan->owner_id);
        foreach ($loan->events()->whereNotNull('bank_transaction_id')->get() as $e) {
            BankTransaction::query()->whereKey($e->bank_transaction_id)->update(['status' => 'ausgabe', 'status_note' => null]);
        }
        $name = $loan->name;
        $loan->delete();
        Audit::log('loan.deleted', null, 'Darlehen gelöscht: '.$name);

        return redirect()->route('finance.index')->with('status', 'Darlehen gelöscht. Zugeordnete Abbuchungen sind wieder offen.');
    }

    public function eventStore(Request $request, Loan $loan)
    {
        $this->assertOwnerAccess((int) $loan->owner_id);
        $data = $request->validate([
            'type' => ['required', Rule::in(['sondertilgung', 'zinsaenderung', 'saldo', 'jahreszinsen', 'zahlung'])],
            'date' => ['required_unless:type,jahreszinsen', 'nullable', 'date'],
            'year' => ['required_if:type,jahreszinsen', 'nullable', 'integer', 'between:2000,2100'],
            'amount' => ['nullable', 'string', 'max:20'],
            'new_rate' => ['nullable', 'string', 'max:10'],
            'new_installment' => ['nullable', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:190'],
        ], ['date.required_unless' => 'Bitte ein Datum angeben.', 'year.required_if' => 'Bitte das Jahr angeben.']);
        $amount = Money::parse($data['amount'] ?? '');
        $newRate = self::pct($data['new_rate'] ?? null);
        $newInst = Money::parse($data['new_installment'] ?? '');
        if (in_array($data['type'], ['sondertilgung', 'saldo', 'jahreszinsen', 'zahlung'], true) && ($amount === null || (float) $amount < 0)) {
            return back()->withInput()->withErrors(['amount' => 'Bitte einen gültigen Betrag eingeben.']);
        }
        if ($data['type'] === 'zinsaenderung' && $newRate === null && $newInst === null) {
            return back()->withInput()->withErrors(['new_rate' => 'Bitte neuen Zinssatz und/oder neue Rate angeben.']);
        }
        if ($newRate !== null && ($newRate < 0 || $newRate > 25)) {
            return back()->withInput()->withErrors(['new_rate' => 'Zinssatz bitte in Prozent, z. B. 3,25.']);
        }
        $date = $data['type'] === 'jahreszinsen' ? $data['year'].'-12-31' : $data['date'];
        if ($data['type'] === 'jahreszinsen') {
            $loan->events()->where('type', 'jahreszinsen')->whereYear('date', (int) $data['year'])->delete();
        }
        $e = $loan->events()->create([
            'type' => $data['type'], 'date' => $date, 'amount' => $amount, 'new_rate' => $newRate, 'new_installment' => $newInst,
            'note' => $data['note'] ?? null, 'created_by' => $this->me()->id,
        ]);
        LoanPlan::forget();
        Audit::log('loan.event', $loan, 'Darlehen „'.$loan->name.'“: '.$e->typeLabel().' am '.$e->date->format('d.m.Y').($amount !== null ? ' ('.Money::format($amount).')' : ''));

        return redirect()->to(route('loans.show', $loan).'#ereignisse')->with('status', $e->typeLabel().' eingetragen. Der Plan ist neu berechnet.');
    }

    public function eventDestroy(Loan $loan, LoanEvent $event)
    {
        $this->assertOwnerAccess((int) $loan->owner_id);
        abort_unless((int) $event->loan_id === (int) $loan->id, 404);
        if ($event->bank_transaction_id) {
            $tx = BankTransaction::query()->find($event->bank_transaction_id);
            if ($tx) {
                LoanMatcher::unassign($tx);

                return redirect()->to(route('loans.show', $loan).'#ereignisse')->with('status', 'Zuordnung entfernt, die Abbuchung ist wieder offen.');
            }
        }
        $event->delete();
        LoanPlan::forget();

        return redirect()->to(route('loans.show', $loan).'#ereignisse')->with('status', 'Eintrag gelöscht.');
    }

    /** Abbuchung von Hand einem Darlehen zuordnen (Kontoauszüge → Ausgaben zuordnen). */
    public function assignTx(Request $request, BankTransaction $tx)
    {
        $this->assertOwnerAccess((int) $tx->owner_id);
        $data = $request->validate(['loan_id' => ['required', 'integer']]);
        $loan = Loan::query()->where('owner_id', $tx->owner_id)->findOrFail((int) $data['loan_id']);
        try {
            LoanMatcher::assign($tx, $loan, $this->me());
        } catch (RuntimeException $e) {
            return back()->withErrors(['bank' => $e->getMessage()]);
        }

        return redirect()->to(route('bank.expenses', array_filter(['stand' => $request->input('stand'), 'konto' => $request->input('konto')])).'#abbuchung-'.$tx->id)->with('status', 'Dem Darlehen „'.$loan->name.'“ zugeordnet.');
    }

    public function unassignTx(Request $request, BankTransaction $tx)
    {
        $this->assertOwnerAccess((int) $tx->owner_id);
        abort_unless($tx->status === 'darlehen', 404);
        LoanMatcher::unassign($tx);

        return back()->with('status', 'Zuordnung zum Darlehen entfernt.');
    }

    public function match(Request $request)
    {
        $n = 0;
        foreach ($this->visibleOwnerIds() as $id) {
            $n += LoanMatcher::autoAssign($id);
        }

        return back()->with('status', $n === 0 ? 'Keine weiteren Abbuchungen sicher erkannt. Unter „Kontoauszüge → Ausgaben zuordnen“ kannst du Raten von Hand zuordnen.' : $n.' Abbuchung(en) den Darlehen zugeordnet.');
    }

    // ------------------------------------------------------------ Werte je Objekt

    public function valuesEdit(Property $property)
    {
        $this->assertOwnerAccess((int) $property->owner_id);

        return view('finance.values', ['property' => $property, 'v' => PropertyValue::for((int) $property->id)]);
    }

    public function valuesUpdate(Request $request, Property $property)
    {
        $this->assertOwnerAccess((int) $property->owner_id);
        $data = $request->validate([
            'purchase_price' => ['nullable', 'string', 'max:20'], 'purchase_costs' => ['nullable', 'string', 'max:20'], 'purchase_date' => ['nullable', 'date'],
            'equity' => ['nullable', 'string', 'max:20'], 'market_value' => ['nullable', 'string', 'max:20'], 'market_value_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $v = PropertyValue::for((int) $property->id);
        $errors = [];
        foreach (['purchase_price', 'purchase_costs', 'equity', 'market_value'] as $k) {
            $raw = trim((string) ($data[$k] ?? ''));
            $parsed = $raw === '' ? null : Money::parse($raw);
            if ($raw !== '' && ($parsed === null || (float) $parsed < 0)) {
                $errors[$k] = 'Bitte einen gültigen Betrag eingeben.';
            }
            $v->{$k} = $parsed;
        }
        if ($errors !== []) {
            return back()->withInput()->withErrors($errors);
        }
        $v->purchase_date = $data['purchase_date'] ?? null;
        $v->market_value_date = $data['market_value_date'] ?? ($v->isDirty('market_value') && $v->market_value !== null ? date('Y-m-d') : $v->market_value_date);
        $v->note = $data['note'] ?? null;
        $v->save();
        Audit::log('property.values', $property, 'Kaufpreis und Wert geändert: '.$property->name);

        return redirect()->to(route('properties.show', $property).'#finanzierung')->with('status', 'Gespeichert.');
    }
}
