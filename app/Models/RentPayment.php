<?php

namespace App\Models;

use App\Support\Rent\Mieten;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Ein Zahlungseingang zu einem Mietvertrag (von Hand erfasst, ab Paket 5b auch aus dem Kontoauszug). */
class RentPayment extends Model
{
    use SoftDeletes;

    /** Einnahmearten beim Aufteilen einer Gutschrift. Miete, Nebenkosten und Gebühren sind Mietzahlungen; Kaution und Sonstiges nicht. */
    public const INCOME_KINDS = [
        'miete' => 'Miete',
        'nk' => 'Nebenkosten-Nachzahlung',
        'gebuehr' => 'Mahngebühr / Zinsen',
        'kaution' => 'Kaution',
        'sonstiges' => 'Sonstiges (keine Miete)',
    ];

    /** Einnahmearten, die als Mietzahlung auf das Mieterkonto gehen. */
    public const RENT_KINDS = ['miete', 'nk', 'gebuehr'];

    protected $fillable = [
        'lease_id', 'owner_id', 'paid_on', 'amount', 'method', 'source', 'income_kind', 'for_period', 'bank_transaction_id', 'nk_settlement_id', 'reference', 'note',
        'allocated_amount', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'for_period' => 'date',
            'amount' => 'decimal:2',
            'allocated_amount' => 'decimal:2',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(RentAllocation::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereIn('rent_payments.owner_id', Owner::visibleTo($user)->select('owners.id'));
    }

    /** z. B. „Miete Oktober 2026“, „Miete“ oder „Nebenkosten-Nachzahlung“. */
    public function incomeLabel(): string
    {
        $kind = (string) ($this->income_kind ?? 'miete');
        $label = self::INCOME_KINDS[$kind] ?? 'Miete';
        if ($kind === 'miete' && $this->for_period) {
            $months = ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
            $label .= ' '.$months[(int) $this->for_period->format('n')].' '.$this->for_period->format('Y');
        }

        return $label;
    }

    public function methodLabel(): string
    {
        if ($this->source === 'nk') {
            return 'Guthaben Nebenkostenabrechnung';
        }
        if ($this->source === 'co2') {
            return 'Erstattung CO2-Kosten (Anteil Vermieter)';
        }
        if ($this->source === 'import') {
            return 'Übernahme beim Import: bis Stichtag ausgeglichen';
        }

        return Mieten::METHODS[$this->method] ?? $this->method;
    }
}
