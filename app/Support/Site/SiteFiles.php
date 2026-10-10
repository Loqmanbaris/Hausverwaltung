<?php

namespace App\Support\Site;

use App\Models\SiteAsset;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Dateien der Webseite (öffentlich, daher unverschlüsselt) unter storage/app/site.
 * Fotos werden verkleinert (Kameradaten fallen weg), Logos behalten die Transparenz, Downloads nur als PDF.
 */
class SiteFiles
{
    private static ?string $root = null;

    public static function useRoot(?string $path): void
    {
        self::$root = $path;
    }

    public static function root(): string
    {
        return self::$root ?? storage_path('app/site');
    }

    /** @throws RuntimeException */
    public static function store(UploadedFile $file, string $kind, ?int $refId = null, ?string $title = null): SiteAsset
    {
        $raw = (string) file_get_contents($file->getRealPath());
        $dir = self::root();
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException('Der Ordner für die Webseiten-Dateien konnte nicht angelegt werden.');
        }
        $name = bin2hex(random_bytes(12));

        if ($kind === 'download') {
            if (! str_starts_with($raw, '%PDF')) {
                throw new RuntimeException('Bitte nur PDF-Dateien hochladen.');
            }
            file_put_contents($dir.'/'.$name.'.pdf', $raw);

            return self::record($kind, $refId, $title ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), $name.'.pdf', 'application/pdf', null, null);
        }

        if (! function_exists('imagecreatefromstring')) {
            throw new RuntimeException('Auf dem Server fehlt die Bildbearbeitung (GD).');
        }
        $info = @getimagesizefromstring($raw);
        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) || $info[0] * $info[1] > 40_000_000) {
            throw new RuntimeException('„'.$file->getClientOriginalName().'“ ist kein JPG-, PNG- oder WebP-Bild.');
        }
        @ini_set('memory_limit', '512M');
        $img = @imagecreatefromstring($raw);
        if ($img === false) {
            throw new RuntimeException('Das Bild konnte nicht gelesen werden.');
        }
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($raw));
            $angle = [3 => 180, 6 => -90, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
            if ($angle && ($rot = imagerotate($img, $angle, 0)) !== false) {
                imagedestroy($img);
                $img = $rot;
            }
        }
        $w = imagesx($img);
        $h = imagesy($img);

        if ($kind === 'logo') {
            $s = min(1, 600 / max($w, $h));
            $dst = imagecreatetruecolor(max(1, (int) round($w * $s)), max(1, (int) round($h * $s)));
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
            imagecopyresampled($dst, $img, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $h);
            imagepng($dst, $dir.'/'.$name.'.png', 9);
            $asset = self::record($kind, $refId, $title, $name.'.png', 'image/png', imagesx($dst), imagesy($dst));
            imagedestroy($dst);
            imagedestroy($img);

            return $asset;
        }

        foreach (['l' => 2000, 's' => 720] as $size => $max) {
            $s = min(1, $max / max($w, $h));
            $dst = imagecreatetruecolor(max(1, (int) round($w * $s)), max(1, (int) round($h * $s)));
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
            imagecopyresampled($dst, $img, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $h);
            imagejpeg($dst, $dir.'/'.$name.'-'.$size.'.jpg', $size === 'l' ? 82 : 78);
            if ($size === 'l') {
                [$fw, $fh] = [imagesx($dst), imagesy($dst)];
            }
            imagedestroy($dst);
        }
        imagedestroy($img);

        return self::record($kind, $refId, $title, $name, 'image/jpeg', $fw, $fh);
    }

    private static function record(string $kind, ?int $refId, ?string $title, string $file, string $mime, ?int $w, ?int $h): SiteAsset
    {
        // Logo und Startfoto gibt es nur einmal: altes ersetzen
        if (in_array($kind, ['logo', 'hero'], true)) {
            foreach (SiteAsset::query()->where('kind', $kind)->get() as $old) {
                self::delete($old);
            }
        }

        return SiteAsset::create([
            'kind' => $kind, 'ref_id' => $refId, 'title' => $title ? mb_substr($title, 0, 190) : null, 'file' => $file, 'mime' => $mime,
            'width' => $w, 'height' => $h, 'sort' => (int) SiteAsset::query()->where('kind', $kind)->where('ref_id', $refId)->max('sort') + 1,
        ]);
    }

    public static function path(SiteAsset $a, string $size): ?string
    {
        if (! preg_match('/^[a-f0-9]{24}(\.(png|pdf))?$/', $a->file)) {
            return null;
        }
        $p = $a->mime === 'image/jpeg' ? self::root().'/'.$a->file.'-'.($size === 's' ? 's' : 'l').'.jpg' : self::root().'/'.$a->file;

        return is_file($p) ? $p : null;
    }

    public static function delete(SiteAsset $a): void
    {
        foreach (['l', 's', 'o'] as $size) {
            $p = self::path($a, $size);
            if ($p) {
                @unlink($p);
            }
        }
        $a->delete();
    }

    public static function first(string $kind): ?SiteAsset
    {
        try {
            return SiteAsset::query()->where('kind', $kind)->orderByDesc('id')->first();
        } catch (\Throwable) {
            return null;
        }
    }
}
