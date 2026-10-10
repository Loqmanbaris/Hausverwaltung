<?php

namespace App\Models;

use App\Support\Technik\Wartung;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Wiederkehrende Wartung oder Prüfpflicht (Paket 24). */
class MaintenanceTask extends Model
{
    protected $fillable = ['owner_id', 'property_id', 'unit_id', 'type', 'title', 'interval_months', 'next_due', 'last_done', 'vendor_id', 'remind_days', 'reminded_at', 'active', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['next_due' => 'date', 'last_done' => 'date', 'reminded_at' => 'datetime', 'active' => 'boolean', 'interval_months' => 'integer'];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class)->withTrashed();
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MaintenanceLog::class)->orderByDesc('done_on')->orderByDesc('id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin() ? $query : $query->whereIn('maintenance_tasks.owner_id', Owner::visibleTo($user)->select('owners.id'));
    }

    /** ueberfaellig | bald | ok */
    public function state(?string $today = null): string
    {
        $today ??= date('Y-m-d');
        $d = $this->next_due->format('Y-m-d');

        return $d < $today ? 'ueberfaellig' : ($d <= date('Y-m-d', strtotime($today.' +'.max(1, (int) $this->remind_days).' days')) ? 'bald' : 'ok');
    }

    public function intervalLabel(): string
    {
        return Wartung::intervalLabel($this->interval_months);
    }

    public function place(): string
    {
        return trim(($this->property?->name ?? '').($this->unit ? ', '.$this->unit->label : ''));
    }
}
