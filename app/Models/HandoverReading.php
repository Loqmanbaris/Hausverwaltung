<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HandoverReading extends Model
{
    protected $fillable = ['handover_protocol_id', 'meter_id', 'value', 'meter_reading_id'];

    protected function casts(): array
    {
        return ['value' => 'decimal:3'];
    }

    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class)->withTrashed();
    }
}
