<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Zugang eines Mieters zum Mieterportal (Paket 36). */
class TenantPortalUser extends Model
{
    protected $fillable = ['tenant_id', 'email', 'enabled', 'invited_at', 'last_login_at', 'created_by'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'invited_at' => 'datetime', 'last_login_at' => 'datetime'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class)->withTrashed();
    }
}
