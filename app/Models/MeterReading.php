<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeterReading extends Model
{
    protected $fillable = ['meter_id', 'read_on', 'value', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['read_on' => 'date', 'value' => 'decimal:3'];
    }

    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class)->withTrashed();
    }
}
