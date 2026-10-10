<?php

namespace App\Models;

use App\Support\Kostenarten;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Receipt extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'owner_id', 'property_id', 'unit_id', 'owner_level', 'receipt_date', 'amount_gross',
        'cost_category', 'vendor_id', 'vendor_name', 'note', 'created_by',
        'invoice_number', 'due_date', 'net_amount', 'tax_amount', 'source', 'einvoice_format',
    ];

    protected function casts(): array
    {
        return [
            'owner_level' => 'boolean',
            'receipt_date' => 'date',
            'amount_gross' => 'decimal:2',
            'due_date' => 'date',
            'net_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class)->withTrashed();
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class)->withTrashed();
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ReceiptFile::class)->orderBy('position');
    }

    /** Admin sieht alle Belege, Mitarbeiter nur die der freigegebenen Eigentümer. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereIn('receipts.owner_id', Owner::visibleTo($user)->select('owners.id'));
    }

    /** Belege, bei denen noch Pflichtangaben fehlen. */
    public function scopeIncomplete(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('receipts.receipt_date')
                ->orWhereNull('receipts.amount_gross')
                ->orWhereNull('receipts.cost_category')
                ->orWhere(function (Builder $p) {
                    $p->where('receipts.owner_level', false)->whereNull('receipts.property_id');
                });
        });
    }

    public function scopeComplete(Builder $query): Builder
    {
        return $query->whereNotNull('receipts.receipt_date')
            ->whereNotNull('receipts.amount_gross')
            ->whereNotNull('receipts.cost_category')
            ->where(function (Builder $q) {
                $q->where('receipts.owner_level', true)->orWhereNotNull('receipts.property_id');
            });
    }

    public static function incompleteCountFor(User $user): int
    {
        return (int) static::query()->visibleTo($user)->incomplete()->count();
    }

    /** Namen der Pflichtangaben, die noch fehlen. */
    public function missing(): array
    {
        $missing = [];
        if ($this->receipt_date === null) {
            $missing[] = 'Datum';
        }
        if ($this->amount_gross === null) {
            $missing[] = 'Betrag';
        }
        if ($this->cost_category === null) {
            $missing[] = 'Kostenart';
        }
        if (! $this->owner_level && $this->property_id === null) {
            $missing[] = 'Objekt';
        }

        return $missing;
    }

    public function isComplete(): bool
    {
        return $this->missing() === [];
    }

    /** Stammt der Beleg aus einer E-Rechnung (XRechnung oder ZUGFeRD)? */
    public function isEInvoice(): bool
    {
        return $this->source === 'e_invoice';
    }

    public function categoryLabel(): string
    {
        return Kostenarten::label($this->cost_category);
    }

    public function amountLabel(): string
    {
        return $this->amount_gross === null ? '–' : Money::format($this->amount_gross);
    }

    /** Wo der Beleg hingehört, in Worten. */
    public function placeLabel(): string
    {
        if ($this->owner_level) {
            return 'Ganzer Eigentümer';
        }
        if ($this->property === null) {
            return '–';
        }

        return $this->property->name.($this->unit ? ', '.$this->unit->label : '');
    }

    /** Kurzbezeichnung für Protokoll und Überschriften. */
    public function title(): string
    {
        $parts = ['Beleg Nr. '.$this->id];
        if ($this->vendor_name) {
            $parts[] = $this->vendor_name;
        }
        if ($this->amount_gross !== null) {
            $parts[] = Money::format($this->amount_gross);
        }

        return implode(', ', $parts);
    }
}
