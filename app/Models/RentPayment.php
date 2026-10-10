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

    protected $fillable = [
        'lease_id', 'owner_id', 'paid_on', 'amount', 'method', 'source', 'bank_transaction_id', 'nk_settlement_id', 'reference', 'note',
        'allocated_amount', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
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
