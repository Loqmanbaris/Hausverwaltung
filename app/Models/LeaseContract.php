<?php

namespace App\Models;

use App\Support\Contracts\Vorlagen;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Erstellter Mietvertrag (Text eingefroren) mit Unterschriften oder Scan. */
class LeaseContract extends Model
{
    public const STATUS = [
        'entwurf' => 'Entwurf',
        'unterschrieben' => 'Unterschrieben',
        'storniert' => 'Storniert',
    ];

    protected $fillable = [
        'lease_id', 'owner_id', 'kind', 'status', 'extras', 'header', 'body', 'content_hash', 'sign_method', 'sign_place', 'signed_at',
        'tenant_signer', 'landlord_signer', 'tenant_signature', 'landlord_signature', 'scan_path', 'scan_name', 'scan_mime', 'created_by', 'signed_by',
    ];

    protected function casts(): array
    {
        return [
            'extras' => 'array',
            'header' => 'array',
            'body' => 'array',
            'signed_at' => 'datetime',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class)->withTrashed();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereIn('lease_contracts.owner_id', Owner::visibleTo($user)->select('owners.id'));
    }

    public function isDraft(): bool
    {
        return $this->status === 'entwurf';
    }

    public function isSigned(): bool
    {
        return $this->status === 'unterschrieben';
    }

    public function title(): string
    {
        return Vorlagen::TITLES[$this->kind] ?? 'Mietvertrag';
    }

    public function statusLabel(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    /** Kurzform der Prüfsumme für die Fußzeile. */
    public function shortHash(): string
    {
        return strtoupper(implode(' ', str_split(substr((string) $this->content_hash, 0, 16), 4)));
    }
}
