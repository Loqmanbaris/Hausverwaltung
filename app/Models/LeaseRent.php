<?php

namespace App\Models;

use App\Support\Money;
use App\Support\Rent\Mieten;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Mietstand ab einem Datum (Vertragsbeginn, Erhöhung, Staffel, Index, neue Vorauszahlung). Beträge netto. */
class LeaseRent extends Model
{
    protected $fillable = [
        'lease_id', 'valid_from', 'reason', 'rent_cold', 'prepay_operating', 'prepay_heating', 'parking', 'garage',
        'furniture', 'index_value', 'index_month', 'note', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'rent_cold' => 'decimal:2',
            'prepay_operating' => 'decimal:2',
            'prepay_heating' => 'decimal:2',
            'parking' => 'decimal:2',
            'garage' => 'decimal:2',
            'furniture' => 'decimal:2',
            'index_value' => 'decimal:2',
            'index_month' => 'date',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class)->withTrashed();
    }

    public function netTotal(): float
    {
        $sum = 0.0;
        foreach (array_keys(Mieten::COMPONENTS) as $col) {
            $sum += (float) $this->{$col};
        }

        return round($sum, 2);
    }

    public function reasonLabel(): string
    {
        return Mieten::REASONS[$this->reason] ?? $this->reason;
    }

    /** „Kalt 650,00 € · NK 180,00 €“ – nur Bestandteile über 0. */
    public function partsLabel(): string
    {
        $parts = [];
        foreach (Mieten::SHORT as $col => $label) {
            if ((float) $this->{$col} != 0.0) {
                $parts[] = $label.' '.Money::format($this->{$col});
            }
        }

        return $parts === [] ? '–' : implode(' · ', $parts);
    }
}
