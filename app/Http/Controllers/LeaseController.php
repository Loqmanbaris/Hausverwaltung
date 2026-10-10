<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\Lease;
use App\Models\LeaseRent;
use App\Models\Owner;
use App\Models\Property;
use App\Models\RentPayment;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\Audit;
use App\Support\Money;
use App\Support\Rent\Mieten;
use App\Support\Rent\RentLedger;
use App\Support\Rent\RentMath;
use App\Support\Stammdaten;
use Closure;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Mietverträge, Mietstand (Erhöhung, Staffel, Index, Vorauszahlung) und Zahlungseingänge je Vertrag.
 * Mitarbeiter sehen und bearbeiten nur Verträge der freigegebenen Eigentümer. Löschen nur Administratoren.
 */
class LeaseController extends Controller
{
    use ScopesOwners;

    public function index(Request $request)
    {
        $user = $this->me();
        $ownerId = (int) $request->query('eigentuemer');
        $state = (string) $request->query('stand', 'aktuell');
        $today = date('Y-m-d');

        $leases = Lease::visibleTo($user)
            ->with(['unit.property', 'tenant', 'owner', 'rents'])
            ->when($ownerId > 0, fn ($q) => $q->where('leases.owner_id', $ownerId))
            ->when($state === 'aktuell', fn ($q) => $q->where(fn ($w) => $w->whereNull('leases.end_date')->orWhere('leases.end_date', '>=', $today)))
            ->when($state === 'beendet', fn ($q) => $q->where('leases.end_date', '<', $today))
            ->get()
            ->sortBy(fn ($l) => [$l->owner?->name, $l->unit?->property?->name, $l->unit?->label, $l->start_date->format('Y-m-d')])
            ->values();

        // Einheiten ohne laufenden Vertrag (Leerstand)
        $busy = Lease::query()->activeOn($today)->pluck('unit_id')->all();
        $vacant = Unit::visibleTo($user)
            ->with('property.owner')
            ->whereNotIn('units.id', $busy)
            ->when($ownerId > 0, fn ($q) => $q->whereIn('units.property_id', Property::query()->where('owner_id', $ownerId)->select('id')))
            ->get()
            ->sortBy(fn ($u) => [$u->property?->owner?->name, $u->property?->name, $u->label])
            ->values();

        $owners = $this->visibleOwners()->get(['owners.id', 'owners.name']);

        return view('leases.index', compact('leases', 'vacant', 'owners', 'ownerId', 'state', 'today'));
    }

    public function create(Request $request)
    {
        $unitId = (int) $request->query('einheit');
        $tenantId = (int) $request->query('mieter');
        $lease = new Lease([
            'unit_id' => $unitId ?: null,
            'tenant_id' => $tenantId ?: null,
            'lease_type' => 'wohnen',
            'due_day' => 3,
            'vat_rate' => 0,
            'adjustment_type' => 'fest',
            'start_date' => date('Y-m-01', strtotime('first day of next month')),
        ]);

        if ($unitId) {
            $unit = Unit::visibleTo($this->me())->with('property.owner')->find($unitId);
            if ($unit) {
                $s = Mieten::suggest($unit->usage_type, $unit->property?->owner?->tax_status);
                $lease->lease_type = $s['type'];
                $lease->vat_rate = $s['vat'];
                if ($s['type'] === 'montage') {
                    $lease->payment_term_days = 7;
                }
            }
        }

        return view('leases.form', $this->formData($lease));
    }

    public function store(Request $request)
    {
        [$data, $rent] = $this->validated($request, null);

        $lease = DB::transaction(function () use ($data, $rent) {
            $lease = Lease::create($data + ['created_by' => $this->me()->id]);
            $lease->rents()->create($rent + ['valid_from' => $lease->start_date->format('Y-m-d'), 'reason' => 'beginn', 'created_by' => $this->me()->id]);
            $lease->load(['unit.property', 'tenant']);
            Audit::log('lease.created', $lease, 'Mietvertrag angelegt: '.$lease->title(), null, $this->auditValues($data + $rent));

            return $lease;
        });

        RentLedger::sync($lease);

        return redirect()->route('leases.show', $lease)->with('status', 'Mietvertrag wurde angelegt. Die Soll-Mieten bis heute sind berechnet.');
    }

    public function show(Lease $lease)
    {
        $this->assertOwnerAccess((int) $lease->owner_id);
        $lease->load(['unit.property', 'tenant', 'owner', 'rents.lease']);

        return view('leases.show', [
            'lease' => $lease,
            'statement' => RentLedger::statement($lease),
            'balance' => RentLedger::balance($lease),
            'current' => $lease->currentRent(),
            'payments' => $lease->payments()->with('allocations.charge')->get()->sortByDesc(fn ($p) => [$p->paid_on->format('Y-m-d'), $p->id])->values(),
            'today' => date('Y-m-d'),
            'payers' => \App\Models\LeasePayerAccount::query()->where('lease_id', $lease->id)->orderBy('name')->get(),
            'notices' => \App\Models\DunningNotice::query()->where('lease_id', $lease->id)->orderByDesc('issued_on')->orderByDesc('id')->get(),
        ]);
    }

    public function edit(Lease $lease)
    {
        $this->assertOwnerAccess((int) $lease->owner_id);

        return view('leases.form', $this->formData($lease->load(['unit.property', 'tenant'])));
    }

    public function update(Request $request, Lease $lease)
    {
        $this->assertOwnerAccess((int) $lease->owner_id);
        [$data] = $this->validated($request, $lease);

        DB::transaction(function () use ($lease, $data) {
            $keys = array_keys($data);
            $norm = fn (array $v) => array_map(fn ($x) => $x instanceof \DateTimeInterface ? $x->format('Y-m-d') : $x, $v);
            $before = $norm($lease->only($keys));

            $first = $lease->rents()->orderBy('valid_from')->first();
            if ($first && $first->valid_from->format('Y-m-d') !== $data['start_date']) {
                $clash = $lease->rents()->where('id', '!=', $first->id)->where('valid_from', '<=', $data['start_date'])->exists();
                if ($clash) {
                    throw ValidationException::withMessages(['start_date' => 'Der neue Beginn liegt nach einer späteren Mietänderung. Bitte zuerst die Mietänderung entfernen.']);
                }
                $first->update(['valid_from' => $data['start_date']]);
            }

            $lease->update($data);
            $lease->refresh();
            [$old, $new] = Audit::diff($before, $norm($lease->only($keys)));
            if ($new !== null) {
                Audit::log('lease.updated', $lease, 'Mietvertrag geändert: '.$lease->load(['unit.property', 'tenant'])->title(), $old, $new);
            }
        });

        $r = RentLedger::sync($lease);

        return redirect()->route('leases.show', $lease)->with('status', 'Änderungen wurden gespeichert.'.$this->syncNote($r));
    }

    public function destroy(Lease $lease)
    {
        if (RentPayment::query()->where('lease_id', $lease->id)->exists()) {
            return back()->withErrors(['lease' => 'Zu diesem Vertrag sind Zahlungen erfasst. Er kann nicht gelöscht werden. Trage stattdessen ein Vertragsende ein.']);
        }

        $lease->load(['unit.property', 'tenant']);
        DB::transaction(function () use ($lease) {
            Audit::log('lease.deleted', $lease, 'Mietvertrag gelöscht: '.$lease->title());
            if (\Illuminate\Support\Facades\Schema::hasTable('dunning_notices')) {
                DB::table('dunning_notices')->where('lease_id', $lease->id)->delete();
            }
            DB::table('rent_charges')->where('lease_id', $lease->id)->delete();
            $lease->delete();
        });

        return redirect()->route('leases.index')->with('status', 'Mietvertrag wurde gelöscht.');
    }

    /* ---------------- Mietänderung ---------------- */

    public function rentCreate(Request $request, Lease $lease)
    {
        $this->assertOwnerAccess((int) $lease->owner_id);
        $lease->load(['unit.property', 'tenant', 'rents']);
        $current = $lease->currentRent();
        $latest = $lease->rents->last();

        $proposal = null;
        $indexInput = trim((string) $request->query('index'));
        if ($lease->adjustment_type === 'index' && $indexInput !== '') {
            $newIndex = Money::parse($indexInput);
            if ($newIndex === null || (float) $newIndex <= 0 || $lease->index_base_value === null) {
                $proposal = ['error' => 'Der Indexwert ist ungültig, oder beim Vertrag fehlt der Ausgangswert.'];
            } else {
                // Ein Jahr Wartezeit ab der letzten Änderung der Kaltmiete (neue Vorauszahlungen zählen nicht)
                $lastChange = $lease->rents->filter(fn ($r) => in_array($r->reason, ['beginn', 'index', 'erhoehung', 'staffel', 'korrektur'], true))->last();
                $proposal = RentMath::indexProposal(
                    (string) $latest->rent_cold,
                    (string) $lease->index_base_value,
                    $newIndex,
                    ($lastChange ?? $latest)->valid_from->format('Y-m-d'),
                    new DateTimeImmutable('today')
                ) + ['index' => $newIndex, 'month' => (string) $request->query('index_monat')];
            }
        }

        $reason = (string) $request->query('art', match ($lease->adjustment_type) {
            'staffel' => 'staffel',
            'index' => 'index',
            default => 'erhoehung',
        });
        if (! array_key_exists($reason, Mieten::REASONS) || $reason === 'beginn') {
            $reason = 'erhoehung';
        }

        $defaultFrom = $proposal && ! isset($proposal['error'])
            ? substr($proposal['earliest'], 0, 7)
            : date('Y-m', strtotime('first day of next month'));

        return view('leases.rent', compact('lease', 'current', 'latest', 'proposal', 'reason', 'defaultFrom'));
    }

    public function rentStore(Request $request, Lease $lease)
    {
        $this->assertOwnerAccess((int) $lease->owner_id);
        $lease->load(['unit.property', 'tenant', 'rents']);

        $raw = $request->validate([
            'reason' => ['required', Rule::in(array_diff(array_keys(Mieten::REASONS), ['beginn']))],
            'valid_from' => ['required', 'date_format:Y-m'],
            'index_value' => ['nullable', 'string', 'max:20'],
            'index_month' => ['nullable', 'date_format:Y-m'],
            'note' => ['nullable', 'string', 'max:2000'],
        ] + $this->componentRules(), $this->messages(), $this->attributes());

        $from = $raw['valid_from'].'-01';
        $start = $lease->start_date->format('Y-m-d');
        if ($from <= $start) {
            throw ValidationException::withMessages(['valid_from' => 'Eine Mietänderung muss nach dem Vertragsbeginn ('.$lease->start_date->format('d.m.Y').') liegen.']);
        }
        if ($lease->end_date && $from > $lease->end_date->format('Y-m-d')) {
            throw ValidationException::withMessages(['valid_from' => 'Der Vertrag endet vorher ('.$lease->end_date->format('d.m.Y').').']);
        }
        if ($lease->rents->contains(fn ($r) => $r->valid_from->format('Y-m-d') === $from)) {
            throw ValidationException::withMessages(['valid_from' => 'Für diesen Monat gibt es schon eine Mietänderung. Bitte diese zuerst entfernen.']);
        }

        $rent = $this->components($raw);
        $index = null;
        if ($raw['reason'] === 'index') {
            $index = Money::parse($raw['index_value'] ?? null);
            if ($index === null || (float) $index <= 0 || empty($raw['index_month'])) {
                throw ValidationException::withMessages(['index_value' => 'Bei einer Indexanpassung bitte den neuen Indexwert und seinen Monat angeben.']);
            }
        }

        $isPast = $from < date('Y-m-01');

        DB::transaction(function () use ($lease, $raw, $rent, $from, $index) {
            $row = $lease->rents()->create($rent + [
                'valid_from' => $from,
                'reason' => $raw['reason'],
                'index_value' => $index,
                'index_month' => $index ? $raw['index_month'].'-01' : null,
                'note' => trim((string) ($raw['note'] ?? '')) ?: null,
                'created_by' => $this->me()->id,
            ]);

            if ($index !== null) {
                $old = ['index_base_value' => (string) $lease->index_base_value, 'index_base_month' => $lease->index_base_month?->format('Y-m')];
                $lease->update(['index_base_value' => $index, 'index_base_month' => $raw['index_month'].'-01']);
                Audit::log('lease.index_base', $lease, 'Ausgangswert für die nächste Indexanpassung geändert', $old, ['index_base_value' => $index, 'index_base_month' => $raw['index_month']]);
            }

            Audit::log('lease.rent_added', $lease, $row->reasonLabel().' ab '.$row->valid_from->format('m/Y').': '.$lease->title(), null, $this->auditValues($rent));
        });

        $r = RentLedger::sync($lease);

        return redirect()->route('leases.show', $lease)->with('status', 'Mietänderung ab '.substr($from, 5, 2).'/'.substr($from, 0, 4).' wurde gespeichert.'.($isPast ? ' Sie liegt in der Vergangenheit, die betroffenen Monate wurden neu berechnet.' : '').$this->syncNote($r));
    }

    public function rentDestroy(Lease $lease, LeaseRent $rent)
    {
        $this->assertOwnerAccess((int) $lease->owner_id);
        abort_unless((int) $rent->lease_id === (int) $lease->id, 404);
        $lease->load(['unit.property', 'tenant']);

        $first = $lease->rents()->orderBy('valid_from')->first();
        if ($first && (int) $first->id === (int) $rent->id) {
            return back()->withErrors(['rent' => 'Die Miete zum Vertragsbeginn kann nicht entfernt werden. Ändere sie über „Mietänderung“ mit Art „Korrektur“ oder bearbeite den Vertrag.']);
        }
        if ($rent->valid_from->format('Y-m-d') < date('Y-m-01') && ! $this->me()->isAdmin()) {
            return back()->withErrors(['rent' => 'Mietänderungen, die schon gelten, kann nur der Administrator entfernen.']);
        }

        DB::transaction(function () use ($lease, $rent) {
            Audit::log('lease.rent_removed', $lease, $rent->reasonLabel().' ab '.$rent->valid_from->format('m/Y').' entfernt: '.$lease->title(), $this->auditValues($rent->only(array_keys(Mieten::COMPONENTS))), null);
            $rent->delete();
        });

        $r = RentLedger::sync($lease);

        return redirect()->route('leases.show', $lease)->with('status', 'Mietänderung wurde entfernt.'.$this->syncNote($r));
    }

    /* ---------------- Zahlungen ---------------- */

    public function paymentStore(Request $request, Lease $lease)
    {
        $this->assertOwnerAccess((int) $lease->owner_id);
        $payment = $this->createPayment($request, $lease);

        return redirect()->route('leases.show', $lease)->with('status', 'Zahlung über '.Money::format($payment->amount).' wurde erfasst.');
    }

    public function paymentDestroy(RentPayment $payment)
    {
        $lease = $payment->lease()->with(['unit.property', 'tenant'])->first();

        DB::transaction(function () use ($payment, $lease) {
            Audit::log('rent_payment.deleted', $payment, 'Zahlung storniert: '.Money::format($payment->amount).' vom '.$payment->paid_on->format('d.m.Y').', '.$lease->title());
            $payment->delete();
        });

        RentLedger::sync($lease);

        // Stammt die Zahlung aus dem Kontoauszug, ist der Umsatz wieder (teilweise) offen
        if ($payment->bank_transaction_id) {
            $tx = \App\Models\BankTransaction::query()->find($payment->bank_transaction_id);
            if ($tx) {
                \App\Support\Bank\BankService::refreshStatus($tx);
            }
        }

        return redirect()->route('leases.show', $lease)->with('status', 'Zahlung wurde storniert.'.($payment->bank_transaction_id ? ' Der Kontoumsatz ist wieder zum Zuordnen offen.' : ''));
    }

    /** Wird auch von der Mieteingangs-Liste benutzt. */
    public function createPayment(Request $request, Lease $lease): RentPayment
    {
        $raw = $request->validate([
            'paid_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'amount' => ['required', function (string $a, mixed $v, Closure $fail) {
                $m = Money::parse($v);
                if ($m === null || (float) $m == 0.0) {
                    $fail('Der Betrag ist ungültig (z. B. 750,00). Für eine Rückbuchung ein Minus davor setzen.');
                }
            }],
            'method' => ['required', Rule::in(array_keys(Mieten::METHODS))],
            'reference' => ['nullable', 'string', 'max:190'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], $this->messages() + [
            'paid_on.before_or_equal' => 'Das Zahldatum darf nicht in der Zukunft liegen.',
        ], $this->attributes());

        $lease->loadMissing(['unit.property', 'tenant']);

        $payment = DB::transaction(function () use ($raw, $lease) {
            $payment = RentPayment::create([
                'lease_id' => $lease->id,
                'owner_id' => $lease->owner_id,
                'paid_on' => $raw['paid_on'],
                'amount' => Money::parse($raw['amount']),
                'method' => $raw['method'],
                'source' => 'manual',
                'reference' => trim((string) ($raw['reference'] ?? '')) ?: null,
                'note' => trim((string) ($raw['note'] ?? '')) ?: null,
                'created_by' => $this->me()->id,
            ]);
            Audit::log('rent_payment.created', $payment, 'Zahlung erfasst: '.Money::format($payment->amount).' vom '.$payment->paid_on->format('d.m.Y').', '.$lease->title());

            return $payment;
        });

        RentLedger::sync($lease);

        return $payment;
    }

    /* ---------------- Hilfen ---------------- */

    /** @return array{0: array, 1: array} [Vertragsdaten, Mietstand bei Beginn] */
    private function validated(Request $request, ?Lease $current): array
    {
        $user = $this->me();
        $unitIds = Unit::visibleTo($user)->pluck('units.id')->map(fn ($i) => (int) $i)->all();

        $rules = [
            'lease_type' => ['required', Rule::in(array_keys(Mieten::LEASE_TYPES))],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'due_mode' => ['required', Rule::in(['tag', 'tage'])],
            'due_day' => ['required_if:due_mode,tag', 'nullable', 'integer', 'between:1,28'],
            'payment_term_days' => ['required_if:due_mode,tage', 'nullable', 'integer', 'between:0,60'],
            'vat_rate' => ['required', Rule::in(array_keys(Mieten::VAT_RATES))],
            'adjustment_type' => ['required', Rule::in(array_keys(Mieten::ADJUSTMENT))],
            'index_base_value' => ['nullable', 'string', 'max:20'],
            'index_base_month' => ['nullable', 'date_format:Y-m'],
            'deposit_amount' => ['nullable', 'string', 'max:20'],
            'deposit_received_on' => ['nullable', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
        if ($current === null) {
            $rules['unit_id'] = ['required', 'integer', Rule::in($unitIds)];
            $rules['tenant_id'] = ['required', 'integer'];
            $rules += $this->componentRules();
        }

        $raw = $request->validate($rules, $this->messages(), $this->attributes());

        $unit = $current ? $current->unit()->with('property')->first() : Unit::query()->with('property')->findOrFail((int) $raw['unit_id']);
        $ownerId = (int) $unit->property->owner_id;

        $errors = [];
        if ($current === null) {
            $tenant = Tenant::visibleTo($user)->find((int) $raw['tenant_id']);
            if ($tenant === null) {
                $errors['tenant_id'] = 'Bitte einen Mieter aus der Liste wählen.';
            } elseif ((int) $tenant->owner_id !== $ownerId) {
                $errors['tenant_id'] = 'Der Mieter ist beim Eigentümer „'.($tenant->owner?->name ?? '–').'“ angelegt, die Einheit gehört aber zu „'.(Owner::withTrashed()->find($ownerId)?->name ?? '–').'“. Bitte den Mieter beim richtigen Eigentümer anlegen.';
            }
        }

        $indexBase = null;
        if ($raw['adjustment_type'] === 'index') {
            $indexBase = Money::parse($raw['index_base_value'] ?? null);
            if ($indexBase === null || (float) $indexBase <= 0 || empty($raw['index_base_month'])) {
                $errors['index_base_value'] = 'Bei einer Indexmiete bitte den Indexwert bei Vertragsbeginn und seinen Monat angeben (Verbraucherpreisindex, Statistisches Bundesamt).';
            }
        }

        $deposit = null;
        if (trim((string) ($raw['deposit_amount'] ?? '')) !== '') {
            $deposit = Money::parse($raw['deposit_amount']);
            if ($deposit === null || (float) $deposit < 0) {
                $errors['deposit_amount'] = 'Die Kaution ist kein gültiger Betrag.';
            }
        }

        // Keine zwei Verträge gleichzeitig für dieselbe Einheit
        $start = $raw['start_date'];
        $end = $raw['end_date'] ?? null;
        $overlap = Lease::query()
            ->with('tenant')
            ->where('unit_id', $unit->id)
            ->when($current, fn ($q) => $q->where('id', '!=', $current->id))
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $start))
            ->when($end, fn ($q) => $q->where('start_date', '<=', $end))
            ->first();
        if ($overlap) {
            $errors['start_date'] = 'Für diese Einheit gibt es im selben Zeitraum schon einen Vertrag ('.($overlap->tenant?->display_name ?? 'Mieter').', ab '.$overlap->start_date->format('d.m.Y').($overlap->end_date ? ' bis '.$overlap->end_date->format('d.m.Y') : ', unbefristet').'). Bitte dort zuerst ein Vertragsende eintragen.';
        }

        if ($current === null && ! isset($errors['rent_cold'])) {
            $rentTry = $this->components($raw);
            if (array_sum(array_map('floatval', $rentTry)) <= 0) {
                $errors['rent_cold'] = 'Bitte mindestens die Kaltmiete eintragen.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $data = [
            'lease_type' => $raw['lease_type'],
            'start_date' => $start,
            'end_date' => $end ?: null,
            'due_day' => $raw['due_mode'] === 'tag' ? (int) $raw['due_day'] : 3,
            'payment_term_days' => $raw['due_mode'] === 'tage' ? (int) $raw['payment_term_days'] : null,
            'vat_rate' => $raw['vat_rate'],
            'adjustment_type' => $raw['adjustment_type'],
            'index_base_value' => $indexBase,
            'index_base_month' => $indexBase !== null ? $raw['index_base_month'].'-01' : null,
            'deposit_amount' => $deposit,
            'deposit_received_on' => ($raw['deposit_received_on'] ?? null) ?: null,
            'note' => trim((string) ($raw['note'] ?? '')) ?: null,
        ];
        if ($current === null) {
            $data['unit_id'] = (int) $unit->id;
            $data['tenant_id'] = (int) $raw['tenant_id'];
            $data['owner_id'] = $ownerId;

            return [$data, $this->components($raw)];
        }

        return [$data, []];
    }

    private function componentRules(): array
    {
        $money = function (string $attribute, mixed $value, Closure $fail): void {
            if (trim((string) $value) === '') {
                return;
            }
            $m = Money::parse($value);
            if ($m === null || (float) $m < 0) {
                $fail('„'.(Mieten::COMPONENTS[$attribute] ?? $attribute).'“ ist kein gültiger Betrag (z. B. 650,00).');
            }
        };

        $rules = [];
        foreach (array_keys(Mieten::COMPONENTS) as $col) {
            $rules[$col] = ['nullable', 'string', 'max:20', $money];
        }

        return $rules;
    }

    /** @return array<string, string> Bestandteile als Dezimalzahl, leere Felder = 0 */
    private function components(array $raw): array
    {
        $out = [];
        foreach (array_keys(Mieten::COMPONENTS) as $col) {
            $out[$col] = Money::parse($raw[$col] ?? null) ?? '0.00';
        }

        return $out;
    }

    private function auditValues(array $values): array
    {
        return array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : (is_scalar($v) || $v === null ? $v : (string) $v), $values);
    }

    private function syncNote(array $r): string
    {
        $n = $r['changed'] + $r['removed'];

        return $n > 0 ? ' '.$n.' Soll-Posten wurde'.($n === 1 ? '' : 'n').' neu berechnet.' : '';
    }

    private function formData(Lease $lease): array
    {
        $user = $this->me();
        $ownerIds = $this->visibleOwnerIds();

        $properties = Property::query()
            ->whereIn('owner_id', $ownerIds)
            ->with(['owner', 'units' => fn ($q) => $q->orderBy('label')])
            ->orderBy('name')
            ->get();

        $tenants = Tenant::visibleTo($user)->with('owner')->orderBy('display_name')->get(['id', 'owner_id', 'display_name']);

        // Vorschläge je Einheit (Vertragsart und Umsatzsteuer)
        $suggest = [];
        foreach ($properties as $p) {
            foreach ($p->units as $u) {
                $suggest[$u->id] = Mieten::suggest($u->usage_type, $p->owner?->tax_status) + ['owner' => (int) $p->owner_id];
            }
        }

        return [
            'lease' => $lease,
            'properties' => $properties,
            'tenants' => $tenants,
            'suggest' => $suggest,
            'rent' => $lease->exists ? null : $lease->rents()->first(),
        ];
    }

    private function messages(): array
    {
        return Stammdaten::messages() + [
            'date_format' => '„:attribute“ ist kein gültiges Datum.',
            'between.numeric' => '„:attribute“ muss zwischen :min und :max liegen.',
            'after_or_equal' => 'Das Vertragsende darf nicht vor dem Beginn liegen.',
            'unit_id.in' => 'Bitte eine Einheit aus der Liste wählen.',
            'due_day.required_if' => 'Bitte den Fälligkeitstag angeben.',
            'payment_term_days.required_if' => 'Bitte das Zahlungsziel in Tagen angeben.',
        ];
    }

    private function attributes(): array
    {
        return Stammdaten::attributes() + Mieten::COMPONENTS + [
            'unit_id' => 'Einheit',
            'tenant_id' => 'Mieter',
            'lease_type' => 'Vertragsart',
            'start_date' => 'Beginn',
            'end_date' => 'Ende',
            'due_day' => 'Fällig am',
            'payment_term_days' => 'Zahlungsziel',
            'vat_rate' => 'Umsatzsteuer',
            'adjustment_type' => 'Mietanpassung',
            'index_base_value' => 'Indexwert',
            'index_base_month' => 'Monat des Indexwerts',
            'deposit_amount' => 'Kaution',
            'deposit_received_on' => 'Kaution erhalten am',
            'note' => 'Notiz',
            'valid_from' => 'Gilt ab',
            'reason' => 'Art der Änderung',
            'index_value' => 'Neuer Indexwert',
            'index_month' => 'Monat des Indexwerts',
            'paid_on' => 'Zahldatum',
            'amount' => 'Betrag',
            'method' => 'Zahlungsart',
            'reference' => 'Verwendungszweck',
        ];
    }
}
