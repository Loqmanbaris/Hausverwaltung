<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Buchung auf einer Kaution (Paket 34). */
class DepositEntry extends Model
{
    protected $fillable = ['deposit_id', 'entry_on', 'kind', 'amount', 'note', 'settlement', 'created_by'];

    protected function casts(): array
    {
        return ['entry_on' => 'date', 'amount' => 'decimal:2', 'settlement' => 'boolean'];
    }

    public function deposit(): BelongsTo
    {
        return $this->belongsTo(Deposit::class);
    }

    public function kindLabel(): string
    {
        return Deposit::KINDS[$this->kind] ?? $this->kind;
    }
}
