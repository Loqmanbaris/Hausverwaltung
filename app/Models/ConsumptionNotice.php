<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Monatliche Verbrauchsinformation eines Objekts ist versendet oder als erledigt markiert (Paket 26). */
class ConsumptionNotice extends Model
{
    protected $fillable = ['property_id', 'month', 'method', 'count', 'created_by'];

    protected function casts(): array
    {
        return ['month' => 'date', 'count' => 'integer'];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
