<?php

namespace App\Models;

use App\Support\Audit;
use App\Support\Kostenarten;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Lieferant oder Rechnungsteller. Gilt für alle Eigentümer, enthält keine Bankdaten. */
class Vendor extends Model
{
    protected $fillable = [
        'name', 'name_key', 'street', 'zip', 'city', 'email', 'phone', 'vat_id',
        'default_cost_category', 'note', 'active',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }

    /** Name in Kleinbuchstaben und mit einfachen Leerzeichen, zum Wiederfinden. */
    public static function normalizeName(?string $name): string
    {
        $n = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $name)));

        return mb_substr($n, 0, 190);
    }

    /**
     * Findet den Lieferanten zu einem eingetippten Namen oder legt ihn an.
     * Ein neuer Lieferant übernimmt die Kostenart des Belegs als Vorschlag für das nächste Mal.
     */
    public static function resolve(?string $name, ?string $costCategory = null): ?self
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', (string) $name));
        $key = self::normalizeName($name);
        if ($key === '') {
            return null;
        }

        $vendor = static::query()->where('name_key', $key)->first();
        if ($vendor !== null) {
            return $vendor;
        }

        $vendor = static::create([
            'name' => mb_substr($name, 0, 190),
            'name_key' => $key,
            'default_cost_category' => $costCategory,
            'active' => true,
        ]);
        Audit::log('vendor.created', $vendor, 'Lieferant angelegt (aus Beleg): '.$vendor->name);

        return $vendor;
    }

    public function addressLine(): string
    {
        $second = trim(($this->zip ?? '').' '.($this->city ?? ''));

        return trim(($this->street ?? '').($this->street && $second ? ', ' : '').$second);
    }

    public function defaultCategoryLabel(): string
    {
        return Kostenarten::label($this->default_cost_category);
    }
}
