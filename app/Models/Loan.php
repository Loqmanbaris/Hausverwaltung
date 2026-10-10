<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Darlehen (Paket 23). */
class Loan extends Model
{
    public const KINDS = [
        'annuitaet' => 'Annuitätendarlehen (feste Rate)',
        'tilgung' => 'Tilgungsdarlehen (feste Tilgung)',
        'endfaellig' => 'Endfällig / KfW (erst nur Zinsen)',
        'privat' => 'Privatdarlehen',
    ];

    public const INTERVALS = [1 => 'monatlich', 3 => 'vierteljährlich', 6 => 'halbjährlich', 12 => 'jährlich'];

    protected $fillable = [
        'owner_id', 'property_id', 'name', 'lender', 'account_no', 'lender_iban', 'lender_iban_hash', 'kind', 'principal', 'paid_out_on',
        'interest_rate', 'installment', 'initial_repayment', 'interval_months', 'first_payment_on', 'fixed_until', 'free_until', 'end_date',
        'special_repayment_pct', 'status', 'note', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'lender_iban' => 'encrypted',
            'principal' => 'decimal:2', 'interest_rate' => 'decimal:3', 'installment' => 'decimal:2', 'initial_repayment' => 'decimal:3',
            'special_repayment_pct' => 'decimal:2', 'interval_months' => 'integer',
            'paid_out_on' => 'date', 'first_payment_on' => 'date', 'fixed_until' => 'date', 'free_until' => 'date', 'end_date' => 'date',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    public function events(): HasMany
    {
        return $this->hasMany(LoanEvent::class)->orderBy('date')->orderBy('id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin() ? $query : $query->whereIn('loans.owner_id', Owner::visibleTo($user)->select('owners.id'));
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }

    public function intervalLabel(): string
    {
        return self::INTERVALS[$this->interval_months] ?? $this->interval_months.'-monatlich';
    }
}
