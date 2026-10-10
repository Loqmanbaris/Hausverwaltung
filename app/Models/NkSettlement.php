<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Eine Nebenkostenabrechnung für ein Objekt und einen Zeitraum. */
class NkSettlement extends Model
{
    protected $fillable = [
        'property_id', 'owner_id', 'period_from', 'period_to', 'status', 'result', 'issued_on', 'note',
        'created_by', 'finalized_by', 'finalized_at', 'co2_kg', 'co2_cost', 'co2_area',
    ];

    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'issued_on' => 'date',
            'finalized_at' => 'datetime',
            'co2_kg' => 'decimal:1',
            'co2_cost' => 'decimal:2',
            'co2_area' => 'decimal:2',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class)->withTrashed();
    }

    public function costs(): HasMany
    {
        return $this->hasMany(NkSettlementCost::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereIn('nk_settlements.owner_id', Owner::visibleTo($user)->select('owners.id'));
    }

    public function isFinal(): bool
    {
        return $this->status === 'abgeschlossen';
    }

    public function periodLabel(): string
    {
        $f = $this->period_from;
        $t = $this->period_to;
        if ($f->format('m-d') === '01-01' && $t->format('m-d') === '12-31' && $f->format('Y') === $t->format('Y')) {
            return 'Jahr '.$f->format('Y');
        }

        return $f->format('d.m.Y').' bis '.$t->format('d.m.Y');
    }

    /** Ergebnis beim Abschluss (sonst null). */
    public function snapshot(): ?array
    {
        $r = $this->result ? json_decode((string) $this->result, true) : null;

        return is_array($r) ? $r : null;
    }
}
