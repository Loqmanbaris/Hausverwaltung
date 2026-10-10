<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Gesendete E-Mail (Paket 21). */
class SentEmail extends Model
{
    public const KINDS = [
        'mahnung' => 'Mahnung',
        'nk' => 'Nebenkostenabrechnung',
        'vertrag' => 'Mietvertrag',
        'uebergabe' => 'Übergabeprotokoll',
        'raten' => 'Ratenvereinbarung',
        'anfrage' => 'Antwort auf Anfrage',
        'rundschreiben' => 'Rundschreiben',
        'auftrag' => 'Handwerkerauftrag',
        'verbrauch' => 'Verbrauchsinformation',
        'hinweis' => 'Hinweis an Verwaltung',
        'test' => 'Test',
    ];

    protected $fillable = ['owner_id', 'tenant_id', 'lease_id', 'kind', 'ref_id', 'recipients', 'subject', 'body', 'attachments', 'status', 'error', 'user_id'];

    protected function casts(): array
    {
        return ['attachments' => 'array'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Mitarbeiter sehen nur E-Mails ihrer Eigentümer; E-Mails ohne Eigentümer nur der Administrator. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin() ? $query : $query->whereIn('sent_emails.owner_id', Owner::visibleTo($user)->select('owners.id'));
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }
}
