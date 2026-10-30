<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ein Mieterhöhungsverfahren (Paket 27). */
class RentIncrease extends Model
{
    public const KINDS = [
        'vergleich' => 'Ortsübliche Vergleichsmiete (§ 558 BGB)',
        'modernisierung' => 'Modernisierung (§ 559 BGB)',
        'index' => 'Indexmiete (§ 557b BGB)',
        'staffel' => 'Staffelmiete (Mitteilung)',
        'vereinbarung' => 'Vereinbarung (§ 557 Abs. 1 BGB)',
    ];

    public const SHORT = [
        'vergleich' => 'Vergleichsmiete',
        'modernisierung' => 'Modernisierung',
        'index' => 'Indexanpassung',
        'staffel' => 'Staffelmitteilung',
        'vereinbarung' => 'Vereinbarung',
    ];

    public const STATUS = [
        'entwurf' => 'Entwurf',
        'erklaert' => 'Erklärt',
        'wirksam' => 'Wirksam',
        'abgelehnt' => 'Abgelehnt',
        'zurueckgezogen' => 'Zurückgezogen',
    ];

    protected $fillable = [
        'lease_id', 'owner_id', 'kind', 'status', 'notice_on', 'effective_from', 'consent_until', 'suit_until',
        'old_cold', 'new_cold', 'area_sqm', 'data', 'note', 'decided_on', 'lease_rent_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'notice_on' => 'date',
            'effective_from' => 'date',
            'consent_until' => 'date',
            'suit_until' => 'date',
            'decided_on' => 'date',
            'old_cold' => 'decimal:2',
            'new_cold' => 'decimal:2',
            'area_sqm' => 'decimal:2',
            'data' => 'array',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class)->withTrashed();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class)->withTrashed();
    }

    public function rent(): BelongsTo
    {
        return $this->belongsTo(LeaseRent::class, 'lease_rent_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin() ? $query : $query->whereIn('rent_increases.owner_id', Owner::visibleTo($user)->select('owners.id'));
    }

    public function kindLabel(): string
    {
        return self::SHORT[$this->kind] ?? $this->kind;
    }

    public function statusLabel(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    /** Erhöhung der Kaltmiete in Euro (kann bei Index auch negativ sein). */
    public function difference(): float
    {
        return round((float) $this->new_cold - (float) $this->old_cold, 2);
    }

    public function percent(): float
    {
        $old = (float) $this->old_cold;

        return $old > 0 ? round($this->difference() / $old * 100, 2) : 0.0;
    }

    /** Braucht die Erhöhung die Zustimmung des Mieters? */
    public function needsConsent(): bool
    {
        return in_array($this->kind, ['vergleich', 'vereinbarung'], true);
    }

    /** Offen = Entwurf oder erklärt, aber noch nicht wirksam oder beendet. */
    public function isOpen(): bool
    {
        return in_array($this->status, ['entwurf', 'erklaert'], true);
    }

    public function isDraft(): bool
    {
        return $this->status === 'entwurf';
    }

    public function title(): string
    {
        return $this->kindLabel().' ab '.$this->effective_from->format('d.m.Y');
    }

    /** Hilfswert aus dem Rechenweg. */
    public function datum(string $key, mixed $default = null): mixed
    {
        return ($this->data ?? [])[$key] ?? $default;
    }
}
