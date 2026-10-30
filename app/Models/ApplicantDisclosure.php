<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/** Selbstauskunft eines Interessenten. Die Angaben liegen verschlüsselt (Paket 31). */
class ApplicantDisclosure extends Model
{
    public const STATUS = ['angefordert' => 'Angefordert', 'ausgefuellt' => 'Online ausgefüllt', 'manuell' => 'Von Hand eingetragen'];

    protected $fillable = ['applicant_id', 'status', 'token_hash', 'token_enc', 'token_expires_at', 'requested_at', 'submitted_at', 'consent_at', 'data_enc'];

    protected function casts(): array
    {
        return ['token_expires_at' => 'datetime', 'requested_at' => 'datetime', 'submitted_at' => 'datetime', 'consent_at' => 'datetime'];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        if ((string) $this->data_enc === '') {
            return [];
        }
        try {
            $d = json_decode(Crypt::decryptString($this->data_enc), true);
        } catch (\Throwable) {
            return [];
        }

        return is_array($d) ? $d : [];
    }

    public function setData(array $data): void
    {
        $this->data_enc = Crypt::encryptString(json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    public function isFilled(): bool
    {
        return in_array($this->status, ['ausgefuellt', 'manuell'], true);
    }

    public function statusLabel(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }
}
