<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Eigener Termin oder eigenes To-do eines Benutzers (Paket 42, „Mein Tag“). */
class PersonalItem extends Model
{
    protected $fillable = ['user_id', 'kind', 'title', 'notes', 'due_on', 'start_time', 'end_time', 'remind', 'done_at'];

    protected function casts(): array
    {
        return ['due_on' => 'date:Y-m-d', 'done_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
