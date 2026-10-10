<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HandoverPhoto extends Model
{
    protected $fillable = ['handover_protocol_id', 'room', 'path', 'thumb', 'caption'];
}
