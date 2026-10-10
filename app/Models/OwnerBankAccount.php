<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OwnerBankAccount extends Model
{
    use SoftDeletes;

    protected $fillable = ['owner_id', 'label', 'account_holder', 'iban', 'bic', 'bank_name', 'is_primary'];

    protected function casts(): array
    {
        return [
            'iban' => 'encrypted',
            'is_primary' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function formattedIban(): string
    {
        return trim(chunk_split((string) $this->iban, 4, ' '));
    }
}
