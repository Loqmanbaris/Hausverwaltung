<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Von Hand eingetragener AfA-Jahresbetrag, zum Beispiel degressive oder Sonder-AfA laut Steuerberater (Paket 29). */
class PropertyAfaYear extends Model
{
    protected $table = 'property_afa_years';

    protected $fillable = ['property_id', 'year', 'amount', 'note'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'year' => 'integer'];
    }
}
