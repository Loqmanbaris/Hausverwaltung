<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Zahlungserinnerung oder Mahnung zu einem Mietvertrag (Paket 10). */
class DunningNotice extends Model
{
    public const LEVELS = [
        1 => 'Zahlungserinnerung',
        2 => 'Mahnung',
        3 => 'Letzte Mahnung',
    ];

    protected $fillable = [
        'lease_id', 'owner_id', 'level', 'issued_on', 'pay_by', 'open_amount', 'fee', 'items', 'note', 'status', 'fee_charge_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'pay_by' => 'date',
            'open_amount' => 'decimal:2',
            'fee' => 'decimal:2',
            'items' => 'array',
            'level' => 'integer',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class)->withTrashed();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function feeCharge(): BelongsTo
    {
        return $this->belongsTo(RentCharge::class, 'fee_charge_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereIn('dunning_notices.owner_id', Owner::visibleTo($user)->select('owners.id'));
    }

    public function levelLabel(): string
    {
        return self::LEVELS[$this->level] ?? 'Mahnung';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'storniert';
    }

    /** Gesamtforderung im Schreiben (offen + Gebühr). */
    public function totalAmount(): float
    {
        return round((float) $this->open_amount + (float) $this->fee, 2);
    }
}
