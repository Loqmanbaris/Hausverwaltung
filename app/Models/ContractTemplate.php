<?php

namespace App\Models;

use App\Support\Contracts\Vorlagen;
use Illuminate\Database\Eloquent\Model;

/** Angepasste Vertragsvorlage je Vertragsart. Ohne Eintrag gilt das Muster aus Vorlagen. */
class ContractTemplate extends Model
{
    protected $fillable = ['kind', 'sections', 'updated_by'];

    protected function casts(): array
    {
        return ['sections' => 'array'];
    }

    /** @return array<int, array{key: string, title: string, text: string, enabled: bool}> */
    public static function sectionsFor(string $kind): array
    {
        try {
            $row = self::query()->where('kind', $kind)->first();
        } catch (\Throwable) {
            $row = null;
        }

        return $row && is_array($row->sections) && $row->sections !== [] ? $row->sections : Vorlagen::defaults($kind);
    }

    public static function isCustomized(string $kind): bool
    {
        return self::query()->where('kind', $kind)->exists();
    }
}
