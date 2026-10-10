<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Personen im Haushalt ab einem Datum (für den Umlageschlüssel „Personen“). */
class LeasePerson extends Model
{
    protected $table = 'lease_persons';

    protected $fillable = ['lease_id', 'valid_from', 'persons'];

    protected function casts(): array
    {
        return ['valid_from' => 'date', 'persons' => 'integer'];
    }
}
