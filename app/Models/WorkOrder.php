<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Schadensfall bzw. Handwerkerauftrag (Paket 24). */
class WorkOrder extends Model
{
    public const STATUS = [
        'gemeldet' => 'Gemeldet',
        'beauftragt' => 'Beauftragt',
        'termin' => 'Termin steht',
        'erledigt' => 'Erledigt',
        'abgerechnet' => 'Abgerechnet',
        'storniert' => 'Storniert',
    ];

    public const OPEN = ['gemeldet', 'beauftragt', 'termin'];

    public const PRIORITY = ['normal' => 'Normal', 'dringend' => 'Dringend', 'notfall' => 'Notfall (sofort)'];

    public const SOURCE = ['manuell' => 'erfasst', 'webseite' => 'über die Webseite gemeldet', 'uebergabe' => 'aus Übergabeprotokoll', 'wartung' => 'aus dem Wartungsplan'];

    protected $fillable = [
        'owner_id', 'property_id', 'unit_id', 'lease_id', 'maintenance_task_id', 'source', 'inquiry_id', 'handover_protocol_id', 'title', 'description',
        'priority', 'status', 'vendor_id', 'appointment_at', 'done_on', 'cost_estimate', 'receipt_id', 'token_hash', 'token_expires_at',
        'reporter_name', 'reporter_phone', 'created_by',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['appointment_at' => 'datetime', 'done_on' => 'date', 'token_expires_at' => 'datetime', 'cost_estimate' => 'decimal:2'];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class)->withTrashed();
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class)->withTrashed();
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(MaintenanceTask::class, 'maintenance_task_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(WorkOrderPhoto::class)->orderBy('id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(WorkOrderNote::class)->orderByDesc('id');
    }

    /** Ohne Eigentümer (z. B. Meldung über die Webseite, Objekt noch unklar) sieht sie nur der Administrator. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin() ? $query : $query->whereIn('work_orders.owner_id', Owner::visibleTo($user)->select('owners.id'));
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('work_orders.status', self::OPEN);
    }

    public function statusLabel(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    public function place(): string
    {
        $p = $this->property;
        if (! $p) {
            return 'Objekt noch nicht zugeordnet';
        }

        return trim($p->name.($this->unit ? ', '.$this->unit->label.($this->unit->floor ? ' ('.$this->unit->floor.')' : '') : ''));
    }

    public function number(): string
    {
        return 'A-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }
}
