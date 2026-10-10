<?php

namespace App\Models;

use App\Support\Listings\Listings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Inserat für die Webseite und als Vorlage für ImmoScout24 und Kleinanzeigen (Paket 11). */
class Listing extends Model
{
    protected $fillable = [
        'unit_id', 'owner_id', 'kind', 'status', 'slug', 'title', 'description', 'equipment_text', 'location_text', 'other_text', 'features',
        'show_address', 'available_from', 'area_sqm', 'rooms', 'floor', 'year_built', 'rent_cold', 'service_charge', 'heating_charge', 'deposit',
        'plus_vat', 'price_night', 'price_week', 'price_month', 'price_per_person', 'beds', 'min_nights', 'energy_type', 'energy_value',
        'energy_source', 'energy_year', 'energy_class', 'published_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'show_address' => 'boolean',
            'plus_vat' => 'boolean',
            'price_per_person' => 'boolean',
            'available_from' => 'date',
            'published_at' => 'datetime',
            'area_sqm' => 'decimal:2',
            'rooms' => 'decimal:1',
            'rent_cold' => 'decimal:2',
            'service_charge' => 'decimal:2',
            'heating_charge' => 'decimal:2',
            'deposit' => 'decimal:2',
            'price_night' => 'decimal:2',
            'price_week' => 'decimal:2',
            'price_month' => 'decimal:2',
            'energy_value' => 'decimal:1',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class)->withTrashed();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ListingPhoto::class)->orderBy('sort')->orderBy('id');
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereIn('listings.owner_id', Owner::visibleTo($user)->select('owners.id'));
    }

    public function scopeOnline(Builder $query): Builder
    {
        return $query->where('listings.status', 'online');
    }

    public function isOnline(): bool
    {
        return $this->status === 'online';
    }

    public function kindLabel(): string
    {
        return Listings::KINDS[$this->kind] ?? $this->kind;
    }

    public function statusLabel(): string
    {
        return Listings::STATUS[$this->status] ?? $this->status;
    }

    /** Warmmiete (Kaltmiete + Nebenkosten + Heizkosten), null wenn keine Kaltmiete. */
    public function warmRent(): ?float
    {
        if ($this->rent_cold === null) {
            return null;
        }

        return round((float) $this->rent_cold + (float) $this->service_charge + (float) $this->heating_charge, 2);
    }

    /** Ort für die öffentliche Anzeige: mit oder ohne Straße. */
    public function publicPlace(): string
    {
        $p = $this->unit?->property;
        $city = trim(($p?->zip ?? '').' '.($p?->city ?? ''));
        if ($this->show_address && $p?->street) {
            return $p->street.', '.$city;
        }

        return $city;
    }

    /** Merkmale mit Bezeichnung in der festen Reihenfolge. */
    public function featureLabels(): array
    {
        $set = array_flip($this->features ?? []);
        $out = [];
        foreach (Listings::FEATURES as $key => [$label, $kinds]) {
            if (isset($set[$key])) {
                $out[$key] = $label;
            }
        }

        return $out;
    }
}
