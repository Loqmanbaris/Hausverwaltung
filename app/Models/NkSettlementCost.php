<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Eine Kostenposition einer Abrechnung: aus einem Beleg oder von Hand (Korrektur, Abgrenzung). */
class NkSettlementCost extends Model
{
    protected $fillable = ['nk_settlement_id', 'receipt_id', 'cost_key', 'amount', 'unit_id', 'label', 'included'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'included' => 'boolean'];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class)->withTrashed();
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class)->withTrashed();
    }
}
