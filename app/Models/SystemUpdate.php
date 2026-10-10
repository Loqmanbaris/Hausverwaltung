<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ein über die Update-Seite eingespieltes Paket. */
class SystemUpdate extends Model
{
    public const STATUS = [
        'installiert' => 'eingespielt',
        'test_fehler' => 'eingespielt, Selbsttest mit Fehlern',
        'fehlgeschlagen' => 'fehlgeschlagen, alter Stand wiederhergestellt',
        'zurueckgenommen' => 'zurückgenommen',
    ];

    protected $fillable = [
        'name', 'title', 'package_sha256', 'status', 'backup_dir', 'files_changed', 'files_new', 'migrations', 'selftest', 'output',
        'installed_by', 'installed_at', 'rolled_back_at',
    ];

    protected function casts(): array
    {
        return [
            'files_changed' => 'array',
            'files_new' => 'array',
            'migrations' => 'array',
            'installed_at' => 'datetime',
            'rolled_back_at' => 'datetime',
        ];
    }

    public function installer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'installed_by');
    }

    public function statusLabel(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }
}
