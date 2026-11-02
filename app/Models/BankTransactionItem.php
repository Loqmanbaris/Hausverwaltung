<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Teil einer Gutschrift, der keine Mietzahlung ist: Kaution (wird bei der Kaution gebucht) oder Sonstiges. */
class BankTransactionItem extends Model
{
    protected $fillable = ['bank_transaction_id', 'owner_id', 'lease_id', 'kind', 'amount', 'note', 'deposit_entry_id', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class, 'bank_transaction_id');
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class)->withTrashed();
    }

    public function kindLabel(): string
    {
        return RentPayment::INCOME_KINDS[$this->kind] ?? $this->kind;
    }
}
