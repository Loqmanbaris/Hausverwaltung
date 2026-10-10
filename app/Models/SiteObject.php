<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Objekt in der Galerie „Unsere Objekte“ auf der Webseite. */
class SiteObject extends Model
{
    protected $fillable = ['property_id', 'visible', 'title', 'text', 'sort'];

    protected function casts(): array
    {
        return ['visible' => 'boolean'];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(SiteAsset::class, 'ref_id')->where('kind', 'object')->orderBy('sort')->orderBy('id');
    }
}
