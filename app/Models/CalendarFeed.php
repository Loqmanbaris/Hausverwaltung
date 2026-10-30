<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/** Kalender-Abo eines Benutzers (Paket 33). */
class CalendarFeed extends Model
{
    protected $fillable = ['user_id', 'token_hash', 'token_enc', 'categories', 'last_fetched_at'];

    protected function casts(): array
    {
        return ['last_fetched_at' => 'datetime', 'categories' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function token(): ?string
    {
        try {
            $t = Crypt::decryptString($this->token_enc);
        } catch (\Throwable) {
            return null;
        }

        return hash('sha256', $t) === $this->token_hash ? $t : null;
    }
}
