<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkOrderPhoto extends Model
{
    protected $fillable = ['work_order_id', 'path', 'thumb', 'by'];
}
