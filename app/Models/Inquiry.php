<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Anfrage über das Kontaktformular der Webseite. */
class Inquiry extends Model
{
    public const STATUS = [
        'neu' => 'Neu',
        'in_bearbeitung' => 'In Bearbeitung',
        'erledigt' => 'Erledigt',
    ];

    protected $fillable = ['listing_id', 'owner_id', 'topic', 'name', 'company', 'email', 'phone', 'persons', 'date_from', 'date_to', 'message', 'status', 'note'];

    protected function casts(): array
    {
        return ['date_from' => 'date', 'date_to' => 'date'];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** Admin sieht alles; Mitarbeiter Anfragen zu freigegebenen Eigentümern und allgemeine Anfragen nicht. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereIn('inquiries.owner_id', Owner::visibleTo($user)->select('owners.id'));
    }

    public static function newCountFor(User $user): int
    {
        try {
            return (int) self::visibleTo($user)->where('status', 'neu')->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
