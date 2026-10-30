<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Besichtigungstermin eines Interessenten (Paket 31). */
class ApplicantViewing extends Model
{
    public const STATUS = [
        'geplant' => 'Geplant',
        'stattgefunden' => 'Hat stattgefunden',
        'abgesagt' => 'Abgesagt',
        'nicht_erschienen' => 'Nicht erschienen',
    ];

    protected $fillable = ['applicant_id', 'unit_id', 'starts_at', 'place', 'status', 'note'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime'];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function statusLabel(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }
}
