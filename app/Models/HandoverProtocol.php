<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Übergabeprotokoll bei Einzug oder Auszug (Paket 13). */
class HandoverProtocol extends Model
{
    public const KINDS = ['einzug' => 'Einzug', 'auszug' => 'Auszug'];

    protected $fillable = [
        'lease_id', 'owner_id', 'kind', 'handover_on', 'status', 'attendees', 'rooms', 'keys', 'note', 'new_address', 'compare_id',
        'sign_method', 'signed_at', 'tenant_signer', 'landlord_signer', 'tenant_signature', 'landlord_signature', 'content_hash', 'created_by',
    ];

    protected function casts(): array
    {
        return ['handover_on' => 'date', 'signed_at' => 'datetime', 'rooms' => 'array', 'keys' => 'array'];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class)->withTrashed();
    }

    public function compare(): BelongsTo
    {
        return $this->belongsTo(self::class, 'compare_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(HandoverPhoto::class)->orderBy('id');
    }

    public function readings(): HasMany
    {
        return $this->hasMany(HandoverReading::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin() ? $query : $query->whereIn('handover_protocols.owner_id', Owner::visibleTo($user)->select('owners.id'));
    }

    public function isDraft(): bool
    {
        return $this->status === 'entwurf';
    }

    public function title(): string
    {
        return 'Übergabeprotokoll '.(self::KINDS[$this->kind] ?? '');
    }

    public function shortHash(): string
    {
        return strtoupper(implode(' ', str_split(substr((string) $this->content_hash, 0, 16), 4)));
    }
}
