<?php

namespace App\Models;

use App\Support\Stammdaten;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'owner_id', 'name', 'street', 'zip', 'city', 'heating_type', 'hot_water_type', 'notes',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class)->withTrashed();
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereIn('properties.owner_id', Owner::visibleTo($user)->select('owners.id'));
    }

    public function heatingLabel(): string
    {
        return Stammdaten::HEATING[$this->heating_type] ?? '–';
    }

    public function hotWaterLabel(): string
    {
        return Stammdaten::HOT_WATER[$this->hot_water_type] ?? '–';
    }

    public function addressLine(): string
    {
        $second = trim(($this->zip ?? '').' '.($this->city ?? ''));

        return trim(($this->street ?? '').($this->street && $second ? ', ' : '').$second);
    }
}
