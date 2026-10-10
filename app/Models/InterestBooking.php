<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Gebuchte Verzugszinsen (Paket 14). */
class InterestBooking extends Model
{
    protected $fillable = ['lease_id', 'owner_id', 'period_from', 'period_until', 'mode', 'amount', 'details', 'status', 'rent_charge_id', 'created_by'];

    protected function casts(): array
    {
        return ['period_from' => 'date', 'period_until' => 'date', 'amount' => 'decimal:2', 'details' => 'array'];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class)->withTrashed();
    }
}
