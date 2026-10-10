<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Ein eingelesener Kontoauszug (Datei). */
class BankImport extends Model
{
    protected $fillable = [
        'owner_id', 'owner_bank_account_id', 'filename', 'format', 'storage_path', 'period_from', 'period_to',
        'rows_total', 'rows_new', 'rows_duplicate', 'rows_skipped', 'created_by',
    ];

    protected function casts(): array
    {
        return ['period_from' => 'date', 'period_to' => 'date'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class)->withTrashed();
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(OwnerBankAccount::class, 'owner_bank_account_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }
}
