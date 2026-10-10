<?php

namespace App\Support\Contracts;

use App\Models\ContractTemplate;
use App\Models\Lease;
use App\Models\LeaseContract;
use App\Models\User;
use App\Support\Audit;
use App\Support\Documents\DocumentStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Mietvertrag erzeugen, unterschreiben (Tablet oder Scan), stornieren. */
class ContractService
{
    /**
     * Text und Kopfdaten aus Vorlage und Daten erzeugen.
     *
     * @return array{header: array, body: array, hash: string, values: array}
     */
    public static function build(Lease $lease, array $extras): array
    {
        $kind = ContractData::kindFor($lease);
        $values = ContractData::values($lease, $extras);
        $body = ContractRenderer::render(ContractTemplate::sectionsFor($kind), $values);
        $header = [
            'kind' => $kind,
            'title' => Vorlagen::TITLES[$kind],
            'vermieter' => ['name' => $values['vermieter_name'], 'anschrift' => $values['vermieter_anschrift']],
            'mieter' => ['name' => $values['mieter_name'], 'vertreter' => $values['mieter_vertreter'] ?? '', 'anschrift' => $values['mieter_anschrift']],
            'weitere_mieter' => $values['weitere_mieter'] ?? '',
            'objekt' => trim($values['einheit'].', '.$values['objekt_anschrift'], ', '),
            'ort' => $values['ort'] ?? '',
            'erstellt' => date('d.m.Y'),
        ];

        return ['header' => $header, 'body' => $body, 'hash' => ContractRenderer::hash($body, $header), 'values' => $values];
    }

    public static function create(Lease $lease, array $extras, User $user): LeaseContract
    {
        $b = self::build($lease, $extras);
        $c = LeaseContract::create([
            'lease_id' => $lease->id,
            'owner_id' => $lease->owner_id,
            'kind' => $b['header']['kind'],
            'status' => 'entwurf',
            'extras' => $extras,
            'header' => $b['header'],
            'body' => $b['body'],
            'content_hash' => $b['hash'],
            'created_by' => $user->id,
        ]);
        Audit::log('contract.created', $c, 'Mietvertrag erstellt (Entwurf): '.$lease->title());

        return $c;
    }

    public static function regenerate(LeaseContract $c, array $extras): void
    {
        if (! $c->isDraft()) {
            throw new RuntimeException('Ein unterschriebener Vertrag kann nicht mehr geändert werden.');
        }
        $b = self::build($c->lease, $extras);
        $c->update(['extras' => $extras, 'header' => $b['header'], 'body' => $b['body'], 'content_hash' => $b['hash']]);
    }

    /**
     * Unterschriften vom Tablet speichern.
     *
     * @throws RuntimeException
     */
    public static function signOnTablet(LeaseContract $c, string $tenantPng, string $landlordPng, string $tenantName, string $landlordName, string $place, User $user): void
    {
        if (! $c->isDraft()) {
            throw new RuntimeException('Dieser Vertrag ist nicht mehr im Entwurf.');
        }
        $t = self::decodeSignature($tenantPng, 'Mieter');
        $l = self::decodeSignature($landlordPng, 'Vermieter');

        $paths = [];
        try {
            $paths[] = $tp = DocumentStore::put($t);
            $paths[] = $lp = DocumentStore::put($l);
            DB::transaction(function () use ($c, $tp, $lp, $tenantName, $landlordName, $place, $user) {
                $c->update([
                    'status' => 'unterschrieben',
                    'sign_method' => 'tablet',
                    'sign_place' => mb_substr($place, 0, 120),
                    'signed_at' => now(),
                    'tenant_signer' => mb_substr($tenantName, 0, 190),
                    'landlord_signer' => mb_substr($landlordName, 0, 190),
                    'tenant_signature' => $tp,
                    'landlord_signature' => $lp,
                    'signed_by' => $user->id,
                ]);
                Audit::log('contract.signed', $c, 'Mietvertrag am Tablet unterschrieben: '.$c->lease?->title().' (Prüfsumme '.$c->shortHash().')');
            });
        } catch (\Throwable $e) {
            foreach ($paths as $p) {
                DocumentStore::forget($p);
            }

            throw $e;
        }
    }

    /** Unterschriebenen Vertrag als Scan (PDF oder Foto) ablegen. */
    public static function attachScan(LeaseContract $c, UploadedFile $file, ?string $place, User $user): void
    {
        if ($c->status === 'storniert') {
            throw new RuntimeException('Der Vertrag ist storniert.');
        }
        $raw = (string) file_get_contents($file->getRealPath());
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($raw) ?: '';
        if (! in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
            throw new RuntimeException('Bitte eine PDF-Datei oder ein Foto (JPG, PNG) hochladen.');
        }
        $path = DocumentStore::put($raw);
        $old = $c->scan_path;
        $c->update([
            'status' => 'unterschrieben',
            'sign_method' => $c->sign_method ?? 'papier',
            'sign_place' => $c->sign_place ?? ($place ? mb_substr($place, 0, 120) : null),
            'signed_at' => $c->signed_at ?? now(),
            'scan_path' => $path,
            'scan_name' => mb_substr($file->getClientOriginalName(), 0, 190),
            'scan_mime' => $mime,
            'signed_by' => $c->signed_by ?? $user->id,
        ]);
        if ($old) {
            DocumentStore::forget($old);
        }
        Audit::log('contract.scan', $c, 'Unterschriebener Mietvertrag als Scan abgelegt: '.$c->lease?->title());
    }

    public static function cancel(LeaseContract $c): void
    {
        $c->update(['status' => 'storniert']);
        Audit::log('contract.cancelled', $c, 'Mietvertrag storniert: '.$c->lease?->title());
    }

    public static function delete(LeaseContract $c): void
    {
        foreach (['tenant_signature', 'landlord_signature', 'scan_path'] as $f) {
            if ($c->{$f}) {
                DocumentStore::forget($c->{$f});
            }
        }
        Audit::log('contract.deleted', $c, 'Mietvertrag (Entwurf) gelöscht: '.$c->lease?->title());
        $c->delete();
    }

    /** Unterschrift als PNG-Daten-Adresse für die Anzeige. */
    public static function signatureDataUri(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        try {
            return 'data:image/png;base64,'.base64_encode(DocumentStore::get($path));
        } catch (\Throwable) {
            return null;
        }
    }

    /** @throws RuntimeException */
    public static function decodeSignature(string $dataUri, string $who): string
    {
        if (! preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $dataUri, $m)) {
            throw new RuntimeException('Die Unterschrift von '.$who.' fehlt.');
        }
        $png = base64_decode($m[1], true);
        if ($png === false || strlen($png) > 600_000) {
            throw new RuntimeException('Die Unterschrift von '.$who.' konnte nicht gelesen werden.');
        }
        $info = @getimagesizefromstring($png);
        if ($info === false || $info[2] !== IMAGETYPE_PNG || $info[0] > 3000 || $info[1] > 1500) {
            throw new RuntimeException('Die Unterschrift von '.$who.' konnte nicht gelesen werden.');
        }
        if (function_exists('imagecreatefromstring') && ($img = @imagecreatefromstring($png)) !== false) {
            // Leere Fläche erkennen: genug gezeichnete (nicht transparente, dunkle) Punkte?
            $w = imagesx($img);
            $h = imagesy($img);
            $ink = 0;
            for ($x = 0; $x < $w; $x += 3) {
                for ($y = 0; $y < $h; $y += 3) {
                    $rgba = imagecolorat($img, $x, $y);
                    $alpha = ($rgba >> 24) & 0x7F;
                    $lum = (($rgba >> 16) & 0xFF) + (($rgba >> 8) & 0xFF) + ($rgba & 0xFF);
                    if ($alpha < 100 && $lum < 450) {
                        $ink++;
                    }
                }
            }
            imagedestroy($img);
            if ($ink < 15) {
                throw new RuntimeException('Die Unterschrift von '.$who.' ist leer oder zu klein.');
            }
        }

        return $png;
    }
}
