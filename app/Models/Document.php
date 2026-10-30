<?php

namespace App\Models;

use App\Support\Ablage\Ablage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Abgelegtes Dokument (Paket 22). Die Datei liegt verschlüsselt im DocumentStore. */
class Document extends Model
{
    protected $fillable = [
        'owner_id', 'attach_type', 'attach_id', 'category', 'title', 'path', 'original_name', 'mime', 'size', 'sha256',
        'doc_date', 'expires_on', 'remind_days', 'reminded_at', 'archived', 'note', 'source', 'created_by', 'shared_with_tenant',
    ];

    protected function casts(): array
    {
        return ['doc_date' => 'date', 'expires_on' => 'date', 'reminded_at' => 'datetime', 'archived' => 'boolean', 'shared_with_tenant' => 'boolean'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin() ? $query : $query->whereIn('documents.owner_id', Owner::visibleTo($user)->select('owners.id'));
    }

    public function scopeFor(Builder $query, string $type, int $id): Builder
    {
        return $query->where('attach_type', $type)->where('attach_id', $id);
    }

    public function categoryLabel(): string
    {
        return Ablage::CATEGORIES[$this->category][0] ?? $this->category;
    }

    /** abgelaufen | bald | ok | null */
    public function expiryState(?string $today = null): ?string
    {
        if ($this->expires_on === null) {
            return null;
        }
        $today ??= date('Y-m-d');
        $d = $this->expires_on->format('Y-m-d');
        if ($d < $today) {
            return 'abgelaufen';
        }

        return $d <= date('Y-m-d', strtotime($today.' +'.max(1, (int) $this->remind_days).' days')) ? 'bald' : 'ok';
    }

    public function sizeLabel(): string
    {
        return $this->size >= 1048576 ? number_format($this->size / 1048576, 1, ',', '.').' MB' : max(1, (int) round($this->size / 1024)).' KB';
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }
}
