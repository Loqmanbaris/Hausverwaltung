<?php

namespace App\Models;

use App\Support\Kostenarten;
use App\Support\Money;
use App\Support\Receipts\RecurrenceSchedule;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Eine Ausgabe, die regelmäßig anfällt (z. B. Grundsteuer, Versicherung, Darlehensrate). */
class RecurringExpense extends Model
{
    protected $fillable = [
        'owner_id', 'property_id', 'unit_id', 'owner_level', 'vendor_id', 'vendor_name', 'cost_category',
        'amount_gross', 'frequency', 'due_month', 'due_day', 'start_date', 'end_date', 'active', 'note', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'owner_level' => 'boolean',
            'active' => 'boolean',
            'amount_gross' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'due_month' => 'integer',
            'due_day' => 'integer',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class)->withTrashed();
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class)->withTrashed();
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereIn('recurring_expenses.owner_id', Owner::visibleTo($user)->select('owners.id'));
    }

    /** @return DateTimeImmutable[] */
    public function dueDates(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return RecurrenceSchedule::dueDates(
            (string) $this->frequency,
            (int) $this->due_month,
            (int) $this->due_day,
            $this->start_date?->format('Y-m-d'),
            $this->end_date?->format('Y-m-d'),
            $from,
            $to
        );
    }

    public function categoryLabel(): string
    {
        return Kostenarten::label($this->cost_category);
    }

    public function amountLabel(): string
    {
        return $this->amount_gross === null ? '–' : Money::format($this->amount_gross);
    }

    public function scheduleLabel(): string
    {
        return RecurrenceSchedule::describe((string) $this->frequency, (int) $this->due_month, (int) $this->due_day);
    }

    /** Kurzbezeichnung: „Stadtwerke, Wasser und Abwasser“. */
    public function title(): string
    {
        return $this->vendor_name ? $this->vendor_name.' – '.$this->categoryLabel() : $this->categoryLabel();
    }

    public function placeLabel(): string
    {
        if ($this->owner_level) {
            return 'Ganzer Eigentümer';
        }
        if ($this->property === null) {
            return '–';
        }

        return $this->property->name.($this->unit ? ', '.$this->unit->label : '');
    }
}
