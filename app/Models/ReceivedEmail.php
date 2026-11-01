<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Empfangene E-Mail aus dem Postfach (Posteingang). */
class ReceivedEmail extends Model
{
    protected $fillable = [
        'folder', 'uidvalidity', 'uid', 'message_id', 'in_reply_to', 'references', 'from_address', 'from_name', 'reply_to',
        'to_addresses', 'cc', 'subject', 'body', 'body_html', 'attachments', 'size', 'received_at', 'owner_id', 'tenant_id', 'vendor_id',
        'applicant_id', 'read_at', 'read_by', 'replied_at', 'archived',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'received_at' => 'datetime',
            'read_at' => 'datetime',
            'replied_at' => 'datetime',
            'archived' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    /** Wie bei den gesendeten E-Mails: Mitarbeiter sehen nur E-Mails ihrer Eigentümer, alles andere nur der Administrator. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin() ? $query : $query->whereIn('received_emails.owner_id', Owner::visibleTo($user)->select('owners.id'));
    }

    public static function unreadCountFor(?User $user): int
    {
        if ($user === null) {
            return 0;
        }

        return self::visibleTo($user)->whereNull('read_at')->where('archived', false)->count();
    }

    public function senderLabel(): string
    {
        return $this->from_name ?: $this->from_address;
    }

    /** Adresse für eine Antwort (Antwort-an, sonst Absender). */
    public function replyAddress(): string
    {
        return $this->reply_to ?: $this->from_address;
    }
}
