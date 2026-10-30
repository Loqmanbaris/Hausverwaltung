<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** AfA-Angaben eines Objekts (Paket 29). */
class PropertyAfa extends Model
{
    protected $table = 'property_afa';

    protected $fillable = ['property_id', 'building_share', 'basis_override', 'extra_basis', 'rate', 'start_date', 'note'];

    protected function casts(): array
    {
        return ['building_share' => 'decimal:2', 'basis_override' => 'decimal:2', 'extra_basis' => 'decimal:2', 'rate' => 'decimal:2', 'start_date' => 'date'];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    public static function for(int $propertyId): self
    {
        return static::query()->firstOrNew(['property_id' => $propertyId]);
    }
}
