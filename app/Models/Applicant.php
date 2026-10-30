<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Mietinteressent (Paket 31). */
class Applicant extends Model
{
    public const STATUS = [
        'neu' => 'Neu',
        'besichtigung' => 'Besichtigung',
        'selbstauskunft' => 'Selbstauskunft',
        'zusage' => 'Zusage',
        'absage' => 'Absage',
        'vertrag' => 'Vertrag',
        'zurueckgezogen' => 'Zurückgezogen',
    ];

    public const SOURCES = [
        'webseite' => 'Webseite',
        'portal' => 'Immobilienportal',
        'empfehlung' => 'Empfehlung',
        'manuell' => 'Sonstiges',
    ];

    /** Verfahren läuft noch. */
    public const OPEN = ['neu', 'besichtigung', 'selbstauskunft', 'zusage'];

    protected $fillable = [
        'owner_id', 'unit_id', 'listing_id', 'inquiry_id', 'first_name', 'last_name', 'email', 'phone', 'persons', 'move_in', 'source',
        'message', 'status', 'note', 'reason', 'decided_on', 'tenant_id', 'delete_after', 'created_by',
    ];

    protected function casts(): array
    {
        return ['move_in' => 'date', 'decided_on' => 'date', 'delete_after' => 'date'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class)->withTrashed();
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class)->withTrashed();
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class)->withTrashed();
    }

    public function viewings(): HasMany
    {
        return $this->hasMany(ApplicantViewing::class)->orderBy('starts_at');
    }

    public function disclosure(): HasOne
    {
        return $this->hasOne(ApplicantDisclosure::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin() ? $query : $query->whereIn('applicants.owner_id', Owner::visibleTo($user)->select('owners.id'));
    }

    public function name(): string
    {
        return trim(($this->first_name ?? '').' '.$this->last_name);
    }

    public function statusLabel(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    /** „Wohnung 2 OG links, Haus Musterstraße“ oder „ohne bestimmte Wohnung“. */
    public function unitLabel(): string
    {
        $u = $this->unit;

        return $u ? $u->label.($u->property ? ', '.$u->property->name : '') : 'ohne bestimmte Wohnung';
    }
}
