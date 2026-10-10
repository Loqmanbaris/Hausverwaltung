<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantContact extends Model
{
    use SoftDeletes;

    protected $fillable = ['tenant_id', 'name', 'role', 'email', 'phone', 'receives_invoices'];

    protected function casts(): array
    {
        return ['receives_invoices' => 'boolean'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class)->withTrashed();
    }
}
