<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Erledigt oder ausgelassen: ein einzelner Tag einer wiederkehrenden Serie (Paket 44). */
class PersonalItemMark extends Model
{
    protected $fillable = ['personal_item_id', 'on_date', 'kind'];

    protected function casts(): array
    {
        return ['on_date' => 'date:Y-m-d'];
    }
}
