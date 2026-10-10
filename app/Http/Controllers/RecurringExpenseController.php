<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\Property;
use App\Models\RecurringExpense;
use App\Models\Vendor;
use App\Support\Audit;
use App\Support\Kostenarten;
use App\Support\Money;
use App\Support\Receipts\ReceiptRules;
use App\Support\Receipts\RecurrenceSchedule;
use App\Support\Receipts\RecurringOverview;
use App\Support\Stammdaten;
use Closure;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Wiederkehrende Ausgaben (Grundsteuer, Versicherung, Darlehensraten ...).
 * Das System zeigt, wann sie fällig sind und ob es dazu schon einen Beleg gibt.
 * Mitarbeiter sehen und bearbeiten nur Ausgaben der für sie freigegebenen Eigentümer. Gelöscht wird nichts.
 */
class RecurringExpenseController extends Controller
{
    use ScopesOwners;

    public function index(Request $request)
    {
        $user = $this->me();
        $all = $request->boolean('alle');

        $templates = RecurringExpense::query()
            ->visibleTo($user)
            ->when(! $all, fn ($q) => $q->where('active', true))
            ->with(['owner', 'property', 'unit'])
            ->orderBy('owner_id')
            ->orderBy('id')
            ->get();

        return view('recurring.index', [
            'rows' => RecurringOverview::forUser($user),
            'templates' => $templates,
            'all' => $all,
            'today' => new DateTimeImmutable('today'),
        ]);
    }

    public function create(Request $request)
    {
        $expense = new RecurringExpense([
            'owner_id' => (int) $request->query('eigentuemer') ?: null,
            'frequency' => 'yearly',
            'due_month' => (int) date('n'),
            'due_day' => 1,
            'start_date' => date('Y-m-d'),
            'active' => true,
        ]);

        return view('recurring.form', $this->formData($expense));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, $this->visibleOwnerIds(), null);

        $expense = DB::transaction(function () use ($data) {
            $expense = RecurringExpense::create($data + ['created_by' => $this->me()->id, 'active' => true]);
            Audit::log('recurring.created', $expense, 'Wiederkehrende Ausgabe angelegt: '.$expense->title().', '.$expense->scheduleLabel(), null, $data);

            return $expense;
        });

        return redirect()->route('recurring.index')->with('status', 'Wiederkehrende Ausgabe wurde angelegt.');
    }

    public function edit(RecurringExpense $recurring)
    {
        $this->assertOwnerAccess($recurring->owner_id);

        return view('recurring.form', $this->formData($recurring));
    }

    public function update(Request $request, RecurringExpense $recurring)
    {
        $this->assertOwnerAccess($recurring->owner_id);

        $request->merge(['owner_id' => $recurring->owner_id]);
        $data = $this->validated($request, [(int) $recurring->owner_id], $recurring);
        unset($data['owner_id']);
        $data['active'] = $request->boolean('active');

        DB::transaction(function () use ($recurring, $data) {
            $keys = array_keys($data);
            $norm = fn (array $v) => array_map(function ($x) {
                if ($x instanceof \DateTimeInterface) {
                    return $x->format('Y-m-d');
                }

                return is_bool($x) ? (int) $x : $x;
            }, $v);

            $before = $norm($recurring->only($keys));
            $recurring->update($data);
            $recurring->refresh();
            [$old, $new] = Audit::diff($before, $norm($recurring->only($keys)));

            if ($new !== null) {
                Audit::log('recurring.updated', $recurring, 'Wiederkehrende Ausgabe geändert: '.$recurring->title(), $old, $new);
            }
        });

        return redirect()->route('recurring.index')->with('status', 'Änderungen wurden gespeichert.');
    }

    /** @param int[] $ownerIds */
    private function validated(Request $request, array $ownerIds, ?RecurringExpense $current): array
    {
        $amountRule = function (string $attribute, mixed $value, Closure $fail): void {
            if (Money::parse($value) === null) {
                $fail('Der Betrag ist ungültig (z. B. 123,45).');
            }
        };

        $raw = $request->validate([
            'owner_id' => ['required', 'integer', Rule::in($ownerIds)],
            'owner_level' => ['nullable', 'boolean'],
            'property_id' => ['nullable', 'integer'],
            'unit_id' => ['nullable', 'integer'],
            'cost_category' => ['required', Rule::in(Kostenarten::allowed($current?->cost_category))],
            'vendor_name' => ['nullable', 'string', 'max:190'],
            'amount_gross' => ['nullable', $amountRule],
            'frequency' => ['required', Rule::in(array_keys(RecurrenceSchedule::FREQUENCIES))],
            'due_month' => ['required', 'integer', 'between:1,12'],
            'due_day' => ['required', 'integer', 'between:1,31'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'note' => ['nullable', 'string', 'max:5000'],
        ], Stammdaten::messages() + [
            'between.numeric' => '„:attribute“ muss zwischen :min und :max liegen.',
            'date_format' => '„:attribute“ ist kein gültiges Datum.',
            'after_or_equal' => 'Das Enddatum darf nicht vor dem Beginn liegen.',
            'owner_id.in' => 'Bitte einen Eigentümer aus der Liste wählen.',
            'cost_category.required' => 'Bitte eine Kostenart wählen. Sie wird gebraucht, um Belege zuzuordnen.',
        ], Stammdaten::attributes() + [
            'cost_category' => 'Kostenart',
            'vendor_name' => 'Lieferant',
            'amount_gross' => 'Betrag',
            'frequency' => 'Rhythmus',
            'due_month' => 'Monat',
            'due_day' => 'Tag',
            'start_date' => 'Beginn',
            'end_date' => 'Ende',
            'property_id' => 'Objekt',
            'unit_id' => 'Einheit',
        ]);

        // Eigentümer, Objekt und Einheit müssen zusammenpassen (wie bei Belegen).
        $place = ReceiptRules::normalize($raw);
        $vendor = Vendor::resolve($raw['vendor_name'] ?? null, $raw['cost_category']);
        $note = trim((string) ($raw['note'] ?? ''));

        return [
            'owner_id' => $place['owner_id'],
            'owner_level' => $place['owner_level'],
            'property_id' => $place['property_id'],
            'unit_id' => $place['unit_id'],
            'vendor_id' => $vendor?->id,
            'vendor_name' => $vendor?->name,
            'cost_category' => $raw['cost_category'],
            'amount_gross' => Money::parse($raw['amount_gross'] ?? null),
            'frequency' => $raw['frequency'],
            'due_month' => $raw['frequency'] === 'monthly' ? 1 : (int) $raw['due_month'],
            'due_day' => (int) $raw['due_day'],
            'start_date' => $raw['start_date'],
            'end_date' => ($raw['end_date'] ?? null) ?: null,
            'note' => $note === '' ? null : $note,
        ];
    }

    private function formData(RecurringExpense $expense): array
    {
        $owners = $this->visibleOwners()->get(['owners.id', 'owners.name']);
        $ownerIds = $owners->pluck('id')->map(fn ($id) => (int) $id)->all();

        $properties = Property::query()
            ->whereIn('owner_id', $ownerIds)
            ->with(['units' => fn ($q) => $q->orderBy('label')])
            ->orderBy('name')
            ->get();

        if (! $expense->exists && ! $expense->owner_id && count($ownerIds) === 1) {
            $expense->owner_id = $ownerIds[0];
        }

        $vendors = Vendor::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'default_cost_category']);

        return compact('expense', 'owners', 'properties', 'vendors');
    }
}
