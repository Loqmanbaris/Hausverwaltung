<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Mietkaution eines Mietvertrags (Paket 34). */
class Deposit extends Model
{
    public const FORMS = ['konto' => 'Kautionskonto', 'sparbuch' => 'Sparbuch', 'buergschaft' => 'Bürgschaft', 'bar' => 'Bar hinterlegt'];

    public const KINDS = ['einzahlung' => 'Einzahlung durch den Mieter', 'zinsen' => 'Zinsen', 'einbehalt' => 'Einbehalt', 'rueckzahlung' => 'Auszahlung an den Mieter'];

    protected $fillable = ['lease_id', 'owner_id', 'form', 'institution', 'account_note', 'interest_rate', 'installments', 'status', 'settled_on', 'claim', 'note'];

    protected function casts(): array
    {
        return ['interest_rate' => 'decimal:3', 'settled_on' => 'date', 'claim' => 'decimal:2'];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class)->withTrashed();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class)->withTrashed();
    }

    public function entries(): HasMany
    {
        return $this->hasMany(DepositEntry::class)->orderBy('entry_on')->orderBy('id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin() ? $query : $query->whereIn('deposits.owner_id', Owner::visibleTo($user)->select('owners.id'));
    }

    public function formLabel(): string
    {
        return self::FORMS[$this->form] ?? $this->form;
    }
}
