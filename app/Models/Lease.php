<?php

namespace App\Models;

use App\Support\Money;
use App\Support\Rent\Mieten;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Ein Mietvertrag über eine Einheit. Der Mietstand (mit Verlauf) steht in lease_rents. */
class Lease extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'owner_id', 'unit_id', 'tenant_id', 'lease_type', 'start_date', 'end_date', 'due_day', 'payment_term_days',
        'vat_rate', 'adjustment_type', 'index_base_value', 'index_base_month', 'deposit_amount', 'deposit_received_on',
        'note', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'due_day' => 'integer',
            'payment_term_days' => 'integer',
            'vat_rate' => 'decimal:2',
            'index_base_value' => 'decimal:2',
            'index_base_month' => 'date',
            'deposit_amount' => 'decimal:2',
            'deposit_received_on' => 'date',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class)->withTrashed();
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class)->withTrashed();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class)->withTrashed();
    }

    public function rents(): HasMany
    {
        return $this->hasMany(LeaseRent::class)->orderBy('valid_from');
    }

    public function charges(): HasMany
    {
        return $this->hasMany(RentCharge::class)->orderBy('period');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(RentPayment::class)->orderBy('paid_on')->orderBy('id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereIn('leases.owner_id', Owner::visibleTo($user)->select('owners.id'));
    }

    /** Verträge, die an diesem Tag laufen. */
    public function scopeActiveOn(Builder $query, string $date): Builder
    {
        return $query->where('leases.start_date', '<=', $date)
            ->where(fn ($q) => $q->whereNull('leases.end_date')->orWhere('leases.end_date', '>=', $date));
    }

    public function typeLabel(): string
    {
        return Mieten::LEASE_TYPES[$this->lease_type] ?? '–';
    }

    public function adjustmentLabel(): string
    {
        return Mieten::ADJUSTMENT[$this->adjustment_type] ?? '–';
    }

    /** laufend | kuenftig | beendet */
    public function state(?string $today = null): string
    {
        $today ??= date('Y-m-d');
        if ($this->start_date->format('Y-m-d') > $today) {
            return 'kuenftig';
        }
        if ($this->end_date !== null && $this->end_date->format('Y-m-d') < $today) {
            return 'beendet';
        }

        return 'laufend';
    }

    public function stateLabel(): string
    {
        return ['laufend' => 'läuft', 'kuenftig' => 'beginnt später', 'beendet' => 'beendet'][$this->state()];
    }

    public function dueLabel(): string
    {
        if ($this->payment_term_days !== null) {
            return $this->payment_term_days.' Tage nach Beginn des Zeitraums (im Voraus)';
        }

        return 'zum '.$this->due_day.'. des Monats';
    }

    /** Der Mietstand, der heute gilt (oder der erste, wenn der Vertrag noch nicht begonnen hat). */
    public function currentRent(?string $date = null): ?LeaseRent
    {
        $date ??= date('Y-m-d');
        $rents = $this->relationLoaded('rents') ? $this->rents : $this->rents()->get();

        return $rents->filter(fn ($r) => $r->valid_from->format('Y-m-d') <= $date)->last() ?? $rents->first();
    }

    /** „Wohnung 1 OG links, Haus Musterstraße – Müller, Hans“ */
    public function title(): string
    {
        $unit = $this->unit;
        $place = $unit ? $unit->label.($unit->property ? ', '.$unit->property->name : '') : 'Einheit';

        return $place.' – '.($this->tenant?->display_name ?? 'Mieter');
    }

    public function deposit(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Deposit::class);
    }

    public function depositLabel(): string
    {
        if ($this->deposit_amount === null) {
            return '–';
        }

        return Money::format($this->deposit_amount).($this->deposit_received_on ? ', erhalten am '.$this->deposit_received_on->format('d.m.Y') : ', noch nicht als erhalten vermerkt');
    }
}
