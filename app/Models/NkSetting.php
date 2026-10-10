<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Einstellungen der Nebenkostenabrechnung je Objekt. */
class NkSetting extends Model
{
    public const HEATING = [
        'zaehler' => 'nach Zählern (Heizkostenverordnung)',
        'flaeche' => 'nach Wohnfläche',
        'direkt' => 'Mieter zahlt direkt (nicht in der Abrechnung)',
    ];

    /** Brennstoff bzw. Wärmeversorgung (Paket 26). CO2-Kosten nach dem BEHG fallen nur bei den markierten an. */
    public const FUELS = [
        'erdgas' => 'Erdgas',
        'heizoel' => 'Heizöl',
        'fluessiggas' => 'Flüssiggas',
        'fernwaerme' => 'Fernwärme',
        'kohle' => 'Kohle',
        'waermepumpe' => 'Wärmepumpe',
        'holz' => 'Holz / Pellets',
        'strom' => 'Strom (z. B. Nachtspeicher)',
        'sonstiges' => 'Sonstiges',
    ];

    public const CO2_FUELS = ['erdgas', 'heizoel', 'fluessiggas', 'fernwaerme', 'kohle'];

    public const BUILDING_USE = [
        '' => 'automatisch (nach überwiegender Fläche)',
        'wohn' => 'Wohngebäude (Stufenmodell)',
        'nichtwohn' => 'Nichtwohngebäude (50 : 50)',
    ];

    public const RESTRICTIONS = [
        'keine' => 'keine',
        'halb' => 'Anteil Vermieter halbiert (z. B. Denkmalschutz, Milieuschutz, Anschlusszwang Fernwärme)',
        'entfall' => 'Anteil Vermieter entfällt (Gebäude und Heizung dürfen nicht verbessert werden)',
    ];

    protected $fillable = ['property_id', 'heating_mode', 'consumption_share', 'fuel', 'building_use', 'co2_restriction', 'co2_restriction_note', 'benchmark_kwh'];

    protected function casts(): array
    {
        return ['consumption_share' => 'integer', 'benchmark_kwh' => 'decimal:1'];
    }

    public function hasCo2Costs(): bool
    {
        return in_array($this->fuel, self::CO2_FUELS, true);
    }

    public function fuelLabel(): string
    {
        return self::FUELS[$this->fuel ?? ''] ?? 'nicht angegeben';
    }

    public static function forProperty(int $propertyId): self
    {
        return static::query()->firstOrNew(['property_id' => $propertyId], ['heating_mode' => 'flaeche', 'consumption_share' => 70, 'co2_restriction' => 'keine']);
    }
}
