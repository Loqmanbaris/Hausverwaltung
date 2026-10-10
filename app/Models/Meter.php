<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Ein Zähler (Kaltwasser, Warmwasser, Wärmemengenzähler, Heizkostenverteiler). */
class Meter extends Model
{
    use SoftDeletes;

    public const KINDS = [
        'wasser' => 'Kaltwasser',
        'warmwasser' => 'Warmwasser',
        'waerme' => 'Wärmemengenzähler',
        'hkv' => 'Heizkostenverteiler',
    ];

    public const UNITS = ['wasser' => 'm³', 'warmwasser' => 'm³', 'waerme' => 'kWh', 'hkv' => 'Striche'];

    /** Zähler, die unter die Heizkostenverordnung fallen (Fernablesbarkeit bis 31.12.2026). */
    public const HEAT_KINDS = ['warmwasser', 'waerme', 'hkv'];

    /** Eichfrist in Jahren (Heizkostenverteiler sind nicht eichpflichtig). */
    public const CALIBRATION_YEARS = ['wasser' => 6, 'warmwasser' => 5, 'waerme' => 5];

    protected $fillable = ['property_id', 'unit_id', 'kind', 'label', 'number', 'factor', 'active', 'remote', 'calibrated_until'];

    protected function casts(): array
    {
        return ['factor' => 'decimal:4', 'active' => 'boolean', 'remote' => 'boolean', 'calibrated_until' => 'integer'];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    public function isHeat(): bool
    {
        return in_array($this->kind, self::HEAT_KINDS, true);
    }

    /** Eichfrist abgelaufen (endet am 31.12. des eingetragenen Jahres). */
    public function calibrationExpired(?string $today = null): bool
    {
        return $this->calibrated_until !== null && (int) $this->calibrated_until < (int) substr($today ?? date('Y-m-d'), 0, 4);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class)->withTrashed();
    }

    public function readings(): HasMany
    {
        return $this->hasMany(MeterReading::class)->orderBy('read_on');
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }

    public function title(): string
    {
        return $this->kindLabel().($this->label ? ' '.$this->label : '').($this->number ? ' (Nr. '.$this->number.')' : '');
    }
}
