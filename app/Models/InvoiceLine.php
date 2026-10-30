<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Position einer Rechnung (Paket 45). */
class InvoiceLine extends Model
{
    protected $fillable = ['invoice_id', 'pos', 'name', 'quantity', 'unit', 'unit_net', 'vat_rate', 'exemption'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'unit_net' => 'decimal:2', 'vat_rate' => 'decimal:2'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
