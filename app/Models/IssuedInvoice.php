<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ausgestellte E-Rechnung oder Gutschrift (Paket 37). Wird nie gelöscht, nur storniert. */
class IssuedInvoice extends Model
{
    protected $table = 'issued_invoices';

    public const STATUS = ['erstellt' => 'Erstellt', 'versendet' => 'Versendet', 'storniert' => 'Storniert'];

    protected $fillable = [
        'owner_id', 'lease_id', 'rent_charge_id', 'original_id', 'type', 'number', 'issued_on', 'due_on', 'period_from', 'period_to',
        'net', 'vat', 'gross', 'vat_rate', 'status', 'sent_on', 'xml', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date', 'due_on' => 'date', 'period_from' => 'date', 'period_to' => 'date', 'sent_on' => 'date',
            'net' => 'decimal:2', 'vat' => 'decimal:2', 'gross' => 'decimal:2', 'vat_rate' => 'decimal:2',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class)->withTrashed();
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class)->withTrashed();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin() ? $query : $query->whereIn('issued_invoices.owner_id', Owner::visibleTo($user)->select('owners.id'));
    }

    public function isCredit(): bool
    {
        return $this->type === '381';
    }

    public function statusLabel(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    public function kindLabel(): string
    {
        return $this->isCredit() ? 'Gutschrift' : 'Rechnung';
    }
}
