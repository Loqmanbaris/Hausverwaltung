<?php

namespace App\Support\Technik;

use App\Models\WorkOrder;
use App\Models\WorkOrderPhoto;
use App\Support\Documents\DocumentStore;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/** Fotos zu Aufträgen: verkleinert, gedreht, ohne Metadaten, verschlüsselt (Paket 24). */
class Fotos
{
    public const MAX_PER_ORDER = 30;

    /** @throws RuntimeException */
    public static function store(WorkOrder $o, UploadedFile $file, string $by = 'team'): WorkOrderPhoto
    {
        if (! function_exists('imagecreatefromstring')) {
            throw new RuntimeException('Auf dem Server fehlt die Bildbearbeitung (GD).');
        }
        if (WorkOrderPhoto::query()->where('work_order_id', $o->id)->count() >= self::MAX_PER_ORDER) {
            throw new RuntimeException('Es sind schon '.self::MAX_PER_ORDER.' Fotos gespeichert.');
        }
        $raw = (string) file_get_contents($file->getRealPath());
        $info = @getimagesizefromstring($raw);
        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) || $info[0] * $info[1] > 40_000_000) {
            throw new RuntimeException('„'.mb_substr($file->getClientOriginalName(), 0, 80).'“ ist kein lesbares Foto (JPG, PNG oder WebP).');
        }
        @ini_set('memory_limit', '512M');
        $img = @imagecreatefromstring($raw);
        if ($img === false) {
            throw new RuntimeException('Das Foto konnte nicht gelesen werden.');
        }
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($raw));
            $angle = [3 => 180, 6 => -90, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
            if ($angle && ($rot = imagerotate($img, $angle, 0)) !== false) {
                imagedestroy($img);
                $img = $rot;
            }
        }
        $jpg = function (int $max, int $q) use ($img): string {
            $w = imagesx($img);
            $h = imagesy($img);
            $s = min(1, $max / max($w, $h));
            $dst = imagecreatetruecolor(max(1, (int) round($w * $s)), max(1, (int) round($h * $s)));
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
            imagecopyresampled($dst, $img, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $h);
            ob_start();
            imagejpeg($dst, null, $q);
            imagedestroy($dst);

            return (string) ob_get_clean();
        };
        $large = DocumentStore::put($jpg(1600, 80));
        $thumb = DocumentStore::put($jpg(420, 72));
        imagedestroy($img);

        return WorkOrderPhoto::create(['work_order_id' => $o->id, 'path' => $large, 'thumb' => $thumb, 'by' => $by]);
    }

    public static function delete(WorkOrderPhoto $ph): void
    {
        DocumentStore::forget($ph->path);
        DocumentStore::forget($ph->thumb);
        $ph->delete();
    }

    /** Vorschaubilder als data:-Adressen (für Druck und PDF). */
    public static function thumbs(WorkOrder $o): array
    {
        $out = [];
        foreach ($o->photos as $ph) {
            try {
                $out[$ph->id] = 'data:image/jpeg;base64,'.base64_encode(DocumentStore::get($ph->thumb));
            } catch (\Throwable) {
            }
        }

        return $out;
    }
}
