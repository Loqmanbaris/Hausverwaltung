<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Welcher Teil einer Zahlung welchen Soll-Posten deckt. Wird vom System berechnet. */
class RentAllocation extends Model
{
    protected $fillable = ['rent_payment_id', 'rent_charge_id', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(RentPayment::class, 'rent_payment_id')->withTrashed();
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(RentCharge::class, 'rent_charge_id');
    }
}
