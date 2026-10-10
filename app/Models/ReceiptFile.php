<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Eine Datei (Foto oder PDF) eines Belegs. Dateien werden nie geändert. */
class ReceiptFile extends Model
{
    protected $fillable = [
        'receipt_id', 'position', 'storage_path', 'original_name', 'mime', 'size_bytes', 'sha256',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mime === 'application/pdf';
    }

    public function isXml(): bool
    {
        return $this->mime === 'application/xml';
    }

    public function extension(): string
    {
        return match ($this->mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            'application/xml' => 'xml',
            default => 'dat',
        };
    }

    /** Dateiname für den Download, ohne Angaben aus dem Upload. */
    public function downloadName(Receipt $receipt): string
    {
        $date = $receipt->receipt_date?->format('Y-m-d') ?? 'ohne-datum';

        return sprintf('beleg-%d-%s-seite-%d.%s', $receipt->id, $date, $this->position, $this->extension());
    }
}
