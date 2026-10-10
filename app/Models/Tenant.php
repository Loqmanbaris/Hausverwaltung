<?php

namespace App\Models;

use App\Support\Stammdaten;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'owner_id', 'type', 'display_name', 'company_name', 'first_name', 'last_name',
        'vat_id', 'street', 'zip', 'city', 'email', 'phone', 'notes',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class)->withTrashed();
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(TenantContact::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereIn('tenants.owner_id', Owner::visibleTo($user)->select('owners.id'));
    }

    public static function composeName(array $data): string
    {
        if (($data['type'] ?? '') === 'firma') {
            return trim((string) ($data['company_name'] ?? ''));
        }

        return trim(($data['last_name'] ?? '').', '.($data['first_name'] ?? ''), ', ');
    }

    public function typeLabel(): string
    {
        return Stammdaten::TENANT_TYPE[$this->type] ?? '–';
    }

    public function addressLine(): string
    {
        $second = trim(($this->zip ?? '').' '.($this->city ?? ''));

        return trim(($this->street ?? '').($this->street && $second ? ', ' : '').$second);
    }
}
