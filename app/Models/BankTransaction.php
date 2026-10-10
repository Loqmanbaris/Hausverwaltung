<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Ein Umsatz aus einem Kontoauszug. Gutschriften werden Mietverträgen zugeordnet. */
class BankTransaction extends Model
{
    public const STATUS = [
        'offen' => 'zuzuordnen',
        'teilweise' => 'teilweise zugeordnet',
        'zugeordnet' => 'zugeordnet',
        'ignoriert' => 'keine Miete',
        'ausgabe' => 'Ausgabe',
    ];

    protected $fillable = [
        'bank_import_id', 'owner_id', 'owner_bank_account_id', 'dedupe_key', 'booking_date', 'value_date', 'amount',
        'currency', 'booking_text', 'purpose', 'counterparty_name', 'counterparty_iban', 'counterparty_iban_hash',
        'counterparty_bic', 'end_to_end', 'mandate_ref', 'creditor_id', 'status', 'status_note',
    ];

    protected function casts(): array
    {
        return [
            'booking_date' => 'date',
            'value_date' => 'date',
            'amount' => 'decimal:2',
            'counterparty_iban' => 'encrypted',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class)->withTrashed();
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(OwnerBankAccount::class, 'owner_bank_account_id')->withTrashed();
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(BankImport::class, 'bank_import_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(RentPayment::class, 'bank_transaction_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereIn('bank_transactions.owner_id', Owner::visibleTo($user)->select('owners.id'));
    }

    public function statusLabel(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    /** Noch nicht zugeordneter Rest (nur Gutschriften). */
    public function remaining(): float
    {
        $assigned = $this->relationLoaded('payments')
            ? (float) $this->payments->sum('amount')
            : (float) $this->payments()->sum('amount');

        return round((float) $this->amount - $assigned, 2);
    }

    public function maskedIban(): string
    {
        $iban = (string) $this->counterparty_iban;

        return $iban === '' ? '–' : substr($iban, 0, 4).' … '.substr($iban, -4);
    }
}
