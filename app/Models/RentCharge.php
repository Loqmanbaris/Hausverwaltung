<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Soll-Miete eines Monats. Wird vom System erzeugt und bei Änderungen am Vertrag neu berechnet. */
class RentCharge extends Model
{
    protected $fillable = [
        'lease_id', 'owner_id', 'kind', 'nk_settlement_id', 'label', 'period', 'period_from', 'period_to', 'due_date', 'rent_cold', 'prepay_operating',
        'prepay_heating', 'parking', 'garage', 'furniture', 'net_total', 'vat_rate', 'vat_amount', 'total', 'paid_amount',
    ];

    protected function casts(): array
    {
        return [
            'period' => 'date',
            'period_from' => 'date',
            'period_to' => 'date',
            'due_date' => 'date',
            'rent_cold' => 'decimal:2',
            'prepay_operating' => 'decimal:2',
            'prepay_heating' => 'decimal:2',
            'parking' => 'decimal:2',
            'garage' => 'decimal:2',
            'furniture' => 'decimal:2',
            'net_total' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class)->withTrashed();
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

        return $query->whereIn('rent_charges.owner_id', Owner::visibleTo($user)->select('owners.id'));
    }

    /** Noch nicht (ganz) bezahlt. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereColumn('rent_charges.paid_amount', '<', 'rent_charges.total');
    }

    public function openAmount(): float
    {
        return round((float) $this->total - (float) $this->paid_amount, 2);
    }

    /** bezahlt | teilweise | offen | ueberfaellig | kommt */
    public function state(?string $today = null): string
    {
        $today ??= date('Y-m-d');
        if ($this->openAmount() <= 0.004) {
            return 'bezahlt';
        }
        if ($this->due_date->format('Y-m-d') < $today) {
            return 'ueberfaellig';
        }
        if ((float) $this->paid_amount > 0) {
            return 'teilweise';
        }

        return $this->due_date->format('Y-m-d') > $today ? 'kommt' : 'offen';
    }

    public function isPartial(): bool
    {
        return $this->period_from->format('Y-m-d') !== $this->period->format('Y-m-d')
            || $this->period_to->format('Y-m-d') !== $this->period->copy()->endOfMonth()->format('Y-m-d');
    }

    /** „Oktober 2026“ oder „Oktober 2026 (15.–31.)“, bei Nebenkosten-Nachzahlungen deren Bezeichnung */
    public function periodLabel(): string
    {
        if (($this->kind ?? 'miete') !== 'miete') {
            return (string) ($this->label ?: 'Nebenkosten-Nachzahlung');
        }

        $months = ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
        $label = $months[(int) $this->period->format('n')].' '.$this->period->format('Y');

        return $this->isPartial() ? $label.' ('.$this->period_from->format('j.').'–'.$this->period_to->format('j.').')' : $label;
    }
}
