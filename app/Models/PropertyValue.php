<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Kaufpreis, Eigenkapital und geschätzter Wert eines Objekts (Paket 23). */
class PropertyValue extends Model
{
    protected $fillable = ['property_id', 'purchase_price', 'purchase_costs', 'purchase_date', 'equity', 'market_value', 'market_value_date', 'note'];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2', 'purchase_costs' => 'decimal:2', 'equity' => 'decimal:2', 'market_value' => 'decimal:2',
            'purchase_date' => 'date', 'market_value_date' => 'date',
        ];
    }

    public static function for(int $propertyId): self
    {
        return self::query()->firstOrNew(['property_id' => $propertyId]);
    }
}
