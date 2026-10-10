<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkOrderNote extends Model
{
    protected $fillable = ['work_order_id', 'user_id', 'author', 'text'];
}
