<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Umlageschlüssel einer Kostenart in einem Objekt, optional mit Vorwegabzug für Gewerbe. */
class NkCostKey extends Model
{
    public const TYPES = [
        'flaeche' => 'Wohnfläche',
        'personen' => 'Personen',
        'einheiten' => 'je Einheit',
        'wasser' => 'Wasserzähler',
        'heizung' => 'Heizung (Verbrauch und Fläche)',
        'warmwasser' => 'Warmwasser (Verbrauch und Fläche)',
        'nicht' => 'nicht umlegen',
    ];

    protected $fillable = ['property_id', 'cost_key', 'key_type', 'commercial_percent'];

    protected function casts(): array
    {
        return ['commercial_percent' => 'decimal:2'];
    }

    /** Vorschlag, solange nichts eingestellt ist. */
    public static function defaultType(string $costKey, string $heatingMode): string
    {
        if (in_array($costKey, ['heizung', 'warmwasser'], true)) {
            return match ($heatingMode) {
                'zaehler' => $costKey,
                'direkt' => 'nicht',
                default => 'flaeche',
            };
        }

        return match ($costKey) {
            'antenne' => 'einheiten',
            default => 'flaeche',
        };
    }
}
