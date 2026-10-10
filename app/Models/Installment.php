<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Installment extends Model
{
    protected $fillable = ['installment_plan_id', 'no', 'due_date', 'amount'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'amount' => 'decimal:2'];
    }
}
