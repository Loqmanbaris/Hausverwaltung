<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Ein Gerät (Handy/iPad/PC), das Mitteilungen empfangen darf. */
class PushSubscription extends Model
{
    protected $fillable = ['user_id', 'endpoint_hash', 'endpoint', 'public_key', 'auth_token', 'content_encoding', 'device', 'last_used_at'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }
}
