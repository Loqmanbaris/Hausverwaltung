<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Eine erzeugte DATEV-Datei. Die Datei selbst liegt verschlüsselt im geschützten Speicher. */
class DatevExport extends Model
{
    protected $fillable = [
        'kind', 'created_by', 'owner_id', 'period_from', 'period_to', 'entry_count', 'total_amount',
        'filename', 'storage_path', 'size_bytes', 'sha256', 'config',
    ];

    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'total_amount' => 'decimal:2',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class)->withTrashed();
    }
}
