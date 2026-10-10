<?php

namespace App\Models;

use App\Support\Stammdaten;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'property_id', 'label', 'floor', 'usage_type', 'area_sqm', 'rooms', 'equipment', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'area_sqm' => 'decimal:2',
            'rooms' => 'decimal:1',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereIn('units.property_id', Property::visibleTo($user)->select('properties.id'));
    }

    public function usageLabel(): string
    {
        return Stammdaten::USAGE[$this->usage_type] ?? '–';
    }
}
