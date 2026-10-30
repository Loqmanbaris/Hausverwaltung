<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Rechnung oder Gutschrift der Hausverwaltung (Paket 45). */
class Invoice extends Model
{
    public const STATUS = ['entwurf' => 'Entwurf', 'festgeschrieben' => 'Festgeschrieben', 'versendet' => 'Versendet', 'storniert' => 'Storniert'];

    protected $attributes = ['type' => '380', 'status' => 'entwurf', 'recipient_kind' => 'free', 'tax_mode' => 'regel'];

    protected $fillable = [
        'number', 'type', 'status', 'recipient_kind', 'tenant_id', 'owner_id', 'buyer', 'seller', 'tax_mode', 'issued_on', 'due_on',
        'delivery_from', 'delivery_to', 'buyer_reference', 'note', 'original_id', 'net', 'vat', 'gross', 'xml', 'sent_on', 'paid_on', 'finalized_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'buyer' => 'array', 'seller' => 'array', 'issued_on' => 'date:Y-m-d', 'due_on' => 'date:Y-m-d', 'delivery_from' => 'date:Y-m-d',
            'delivery_to' => 'date:Y-m-d', 'sent_on' => 'date:Y-m-d', 'paid_on' => 'date:Y-m-d', 'finalized_at' => 'datetime',
            'net' => 'decimal:2', 'vat' => 'decimal:2', 'gross' => 'decimal:2',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('pos')->orderBy('id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class)->withTrashed();
    }

    public function isCredit(): bool
    {
        return $this->type === '381';
    }

    public function isDraft(): bool
    {
        return $this->status === 'entwurf';
    }

    public function kindLabel(): string
    {
        return $this->isCredit() ? 'Gutschrift' : 'Rechnung';
    }

    public function statusLabel(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }
}
