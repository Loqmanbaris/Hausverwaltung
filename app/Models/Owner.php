<?php

namespace App\Models;

use App\Support\Stammdaten;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Owner extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'street', 'zip', 'city', 'tax_number', 'vat_id', 'tax_status',
        'creditor_id', 'email', 'phone', 'notes',
    ];

    protected function casts(): array
    {
        return ['tax_number' => 'encrypted'];
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(OwnerBankAccount::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'owner_user')->withTimestamps();
    }

    /** Admin sieht alle Eigentümer, Mitarbeiter nur die freigegebenen. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereIn(
            'owners.id',
            DB::table('owner_user')->where('user_id', $user->id)->select('owner_id')
        );
    }

    public function taxStatusLabel(): string
    {
        return Stammdaten::TAX_STATUS[$this->tax_status] ?? '–';
    }

    public function addressLine(): string
    {
        return trim(($this->zip ?? '').' '.($this->city ?? ''));
    }
}
