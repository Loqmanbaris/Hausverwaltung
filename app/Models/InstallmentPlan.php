<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Ratenvereinbarung über einen Mietrückstand (Paket 14). */
class InstallmentPlan extends Model
{
    public const STATUS = [
        'aktiv' => 'Läuft',
        'erfuellt' => 'Erfüllt',
        'geplatzt' => 'Nicht eingehalten',
        'storniert' => 'Storniert',
    ];

    protected $fillable = ['lease_id', 'owner_id', 'agreed_on', 'debt', 'status', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['agreed_on' => 'date', 'debt' => 'decimal:2'];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class)->withTrashed();
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class)->orderBy('no');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin() ? $query : $query->whereIn('installment_plans.owner_id', Owner::visibleTo($user)->select('owners.id'));
    }

    public function statusLabel(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }
}
