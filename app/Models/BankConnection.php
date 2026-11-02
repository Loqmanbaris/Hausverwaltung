<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Automatischer Kontoabruf (FinTS) für ein Bankkonto eines Eigentümers (Paket 47). */
class BankConnection extends Model
{
    public const STATUS = ['neu' => 'eingerichtet, noch nicht abgerufen', 'bereit' => 'bereit', 'tan' => 'Freigabe nötig', 'fehler' => 'Fehler'];

    protected $fillable = ['owner_bank_account_id', 'url', 'bank_code', 'username', 'pin', 'tan_mode', 'tan_medium', 'state', 'pending', 'status', 'active', 'last_fetch_at', 'last_to', 'last_error', 'last_message'];

    protected $hidden = ['username', 'pin', 'state', 'pending'];

    protected function casts(): array
    {
        return [
            'username' => 'encrypted',
            'pin' => 'encrypted',
            'state' => 'encrypted',
            'pending' => 'encrypted',
            'active' => 'boolean',
            'last_fetch_at' => 'datetime',
            'last_to' => 'date',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(OwnerBankAccount::class, 'owner_bank_account_id');
    }
}
