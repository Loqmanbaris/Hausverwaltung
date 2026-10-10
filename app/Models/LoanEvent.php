<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ereignis zu einem Darlehen: Zahlung (aus dem Kontoauszug), Sondertilgung, Zinsänderung, Saldo laut Bank, Jahreszinsen laut Bank. */
class LoanEvent extends Model
{
    public const TYPES = [
        'zahlung' => 'Zahlung',
        'sondertilgung' => 'Sondertilgung',
        'zinsaenderung' => 'Neuer Zinssatz / neue Rate',
        'saldo' => 'Restschuld laut Bank',
        'jahreszinsen' => 'Zinsen im Jahr laut Bank',
    ];

    protected $fillable = ['loan_id', 'type', 'date', 'amount', 'new_rate', 'new_installment', 'bank_transaction_id', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['date' => 'date', 'amount' => 'decimal:2', 'new_rate' => 'decimal:3', 'new_installment' => 'decimal:2'];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class, 'bank_transaction_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
