<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Erstattung des Vermieteranteils an den CO2-Kosten an einen Mieter mit eigenem Versorgungsvertrag (Etagenheizung, Paket 26). */
class Co2Claim extends Model
{
    public const STATUS = ['offen' => 'offen', 'gutgeschrieben' => 'im Mieterkonto gutgeschrieben', 'ausgezahlt' => 'ausgezahlt'];

    protected $fillable = [
        'owner_id', 'lease_id', 'period_from', 'period_to', 'co2_kg', 'co2_cost', 'area', 'gas_stove', 'building_use', 'restriction',
        'kg_per_sqm', 'step', 'landlord_pct', 'amount', 'claimed_on', 'status', 'settled_on', 'rent_payment_id', 'note', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period_from' => 'date', 'period_to' => 'date', 'claimed_on' => 'date', 'settled_on' => 'date',
            'co2_kg' => 'decimal:1', 'co2_cost' => 'decimal:2', 'area' => 'decimal:2', 'kg_per_sqm' => 'decimal:1', 'amount' => 'decimal:2',
            'gas_stove' => 'boolean', 'step' => 'integer', 'landlord_pct' => 'integer',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class)->withTrashed();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class)->withTrashed();
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(RentPayment::class, 'rent_payment_id')->withTrashed();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereIn('co2_claims.owner_id', Owner::visibleTo($user)->select('owners.id'));
    }

    public function statusLabel(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    /** Frist für den Vermieter: 12 Monate ab Geltendmachung (wenn nicht mit einer Abrechnung verrechnet). */
    public function dueBy(): ?\Carbon\CarbonInterface
    {
        return $this->claimed_on?->copy()->addYear();
    }
}
