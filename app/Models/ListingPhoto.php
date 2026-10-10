<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingPhoto extends Model
{
    protected $fillable = ['listing_id', 'file', 'caption', 'sort', 'width', 'height'];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function url(string $size = 'l'): string
    {
        return route('public.photo', ['photo' => $this->id, 'size' => $size === 's' ? 's' : 'l']).'?v='.substr(md5((string) $this->file), 0, 8);
    }
}
