<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Eine Kostenart für Belege (Start: Liste aus dem Programm, danach vom Administrator pflegbar). */
class CostType extends Model
{
    protected $fillable = ['key', 'label', 'group_label', 'sort', 'active', 'allocatable', 'is_system'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'allocatable' => 'boolean',
            'is_system' => 'boolean',
        ];
    }
}
