<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Belegung einer Montage-Wohnung (Anreise bis Abreise). */
class UnitBooking extends Model
{
    public const STATUS = [
        'reserviert' => 'Reserviert',
        'belegt' => 'Belegt',
        'gesperrt' => 'Gesperrt (z. B. Renovierung)',
    ];

    protected $fillable = ['unit_id', 'date_from', 'date_to', 'status', 'label', 'persons', 'note', 'inquiry_id', 'created_by'];

    protected function casts(): array
    {
        return ['date_from' => 'date', 'date_to' => 'date'];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class)->withTrashed();
    }

    public function nights(): int
    {
        return (int) $this->date_from->diffInDays($this->date_to);
    }
}
