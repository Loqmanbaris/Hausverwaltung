<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Konto, von dem für einen Mietvertrag gezahlt wird (vom System gelernt). */
class LeasePayerAccount extends Model
{
    protected $fillable = ['lease_id', 'iban_hash', 'iban', 'name', 'created_by'];

    protected function casts(): array
    {
        return ['iban' => 'encrypted'];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class)->withTrashed();
    }

    public function maskedIban(): string
    {
        $iban = (string) $this->iban;

        return substr($iban, 0, 4).' … '.substr($iban, -4);
    }
}
