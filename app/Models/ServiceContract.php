<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Laufender Vertrag mit einem Anbieter: Strom, Gas, Versicherung, Hausmeister und so weiter (Paket 32). */
class ServiceContract extends Model
{
    public const KINDS = [
        'strom' => 'Strom',
        'gas' => 'Gas',
        'wasser' => 'Wasser und Abwasser',
        'fernwaerme' => 'Fernwärme',
        'muell' => 'Müllabfuhr',
        'versicherung' => 'Versicherung',
        'hausmeister' => 'Hausmeister',
        'reinigung' => 'Reinigung und Gartenpflege',
        'aufzug' => 'Aufzug und Wartung',
        'kabel' => 'Kabel, Internet, Telefon',
        'messdienst' => 'Messdienst und Zähler',
        'verwaltung' => 'Verwaltung und Beratung',
        'sonstiges' => 'Sonstiges',
    ];

    public const INTERVALS = [
        'monatlich' => ['monatlich', 12],
        'vierteljaehrlich' => ['vierteljährlich', 4],
        'halbjaehrlich' => ['halbjährlich', 2],
        'jaehrlich' => ['jährlich', 1],
        'einmalig' => ['einmalig', 0],
    ];

    public const UNITS = ['tage' => 'Tage', 'wochen' => 'Wochen', 'monate' => 'Monate'];

    public const STATUS = ['aktiv' => 'Läuft', 'gekuendigt' => 'Gekündigt', 'beendet' => 'Beendet'];

    protected $fillable = [
        'owner_id', 'property_id', 'unit_id', 'kind', 'title', 'provider', 'contract_no', 'customer_no', 'meter_no', 'start_date', 'term_end',
        'auto_renew', 'renew_months', 'notice_value', 'notice_unit', 'remind_days', 'amount', 'interval', 'cost_type_id', 'status',
        'cancelled_on', 'cancel_effective', 'reminded_for', 'reminded_stage', 'contact', 'note', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date', 'term_end' => 'date', 'cancelled_on' => 'date', 'cancel_effective' => 'date', 'reminded_for' => 'date',
            'auto_renew' => 'boolean', 'amount' => 'decimal:2',
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

    public function costType(): BelongsTo
    {
        return $this->belongsTo(CostType::class, 'cost_type_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin() ? $query : $query->whereIn('service_contracts.owner_id', Owner::visibleTo($user)->select('owners.id'));
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }

    public function statusLabel(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    public function intervalLabel(): string
    {
        return self::INTERVALS[$this->interval][0] ?? $this->interval;
    }

    /** Kosten im Jahr in Euro (0 bei einmalig oder ohne Betrag). */
    public function annualCost(): float
    {
        $f = self::INTERVALS[$this->interval][1] ?? 0;

        return round((float) $this->amount * $f, 2);
    }

    public function noticeLabel(): string
    {
        return $this->notice_value.' '.(self::UNITS[$this->notice_unit] ?? $this->notice_unit);
    }

    public function place(): string
    {
        $p = $this->property?->name;
        $u = $this->unit?->label;

        return $p ? ($u ? $p.', '.$u : $p) : ($this->owner?->name ?? '');
    }
}
