<?php

namespace App\Support\Listings;

use App\Models\Listing;
use App\Models\ListingPhoto;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Fotos der Inserate. Sie sind öffentlich, deshalb nicht verschlüsselt, liegen aber außerhalb von public/
 * und werden über eine Route ausgeliefert. Beim Hochladen werden sie verkleinert und neu gespeichert,
 * dadurch fallen auch Kameradaten (z. B. GPS-Position) weg.
 */
class PhotoStore
{
    public const SIZES = ['l' => 1600, 's' => 640];

    private static ?string $root = null;

    public static function useRoot(?string $path): void
    {
        self::$root = $path === null ? null : rtrim($path, '/');
    }

    public static function root(): string
    {
        return self::$root ?? storage_path('app/listing-photos');
    }

    public static function available(): bool
    {
        return function_exists('imagecreatefromstring') && function_exists('imagejpeg');
    }

    /** @throws RuntimeException */
    public static function store(UploadedFile $upload, Listing $listing): ListingPhoto
    {
        if (! self::available()) {
            throw new RuntimeException('Auf dem Server fehlt die Bildbearbeitung (PHP-Erweiterung GD).');
        }
        $raw = (string) file_get_contents($upload->getRealPath());
        $info = @getimagesizefromstring($raw);
        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new RuntimeException('„'.$upload->getClientOriginalName().'“ ist kein JPG-, PNG- oder WebP-Bild.');
        }
        if ($info[0] * $info[1] > 40_000_000) {
            throw new RuntimeException('„'.$upload->getClientOriginalName().'“ ist zu groß (mehr als 40 Megapixel).');
        }
        @ini_set('memory_limit', '512M');
        $img = @imagecreatefromstring($raw);
        if ($img === false) {
            throw new RuntimeException('„'.$upload->getClientOriginalName().'“ konnte nicht gelesen werden.');
        }
        $img = self::orient($img, $raw, $info[2]);

        $name = $listing->id.'/'.bin2hex(random_bytes(12));
        $dir = self::root().'/'.$listing->id;
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException('Der Ordner für Fotos konnte nicht angelegt werden.');
        }
        $w = imagesx($img);
        $h = imagesy($img);
        foreach (self::SIZES as $size => $max) {
            $scale = min(1, $max / max($w, $h));
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));
            $dst = imagecreatetruecolor($nw, $nh);
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // Transparenz bei PNG weiß
            imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            $ok = imagejpeg($dst, self::root().'/'.$name.'-'.$size.'.jpg', $size === 'l' ? 82 : 78);
            imagedestroy($dst);
            if (! $ok) {
                throw new RuntimeException('Das Foto konnte nicht gespeichert werden.');
            }
            if ($size === 'l') {
                [$fw, $fh] = [$nw, $nh];
            }
        }
        imagedestroy($img);

        return ListingPhoto::create([
            'listing_id' => $listing->id,
            'file' => $name,
            'sort' => (int) ListingPhoto::query()->where('listing_id', $listing->id)->max('sort') + 1,
            'width' => $fw,
            'height' => $fh,
        ]);
    }

    public static function path(ListingPhoto $photo, string $size): ?string
    {
        if (! preg_match('#^\d+/[a-f0-9]{24}$#', (string) $photo->file)) {
            return null;
        }
        $p = self::root().'/'.$photo->file.'-'.($size === 's' ? 's' : 'l').'.jpg';

        return is_file($p) ? $p : null;
    }

    public static function delete(ListingPhoto $photo): void
    {
        foreach (array_keys(self::SIZES) as $size) {
            $p = self::path($photo, $size);
            if ($p) {
                @unlink($p);
            }
        }
        $photo->delete();
    }

    /** Hochkant aufgenommene Handyfotos richtig drehen. */
    private static function orient(\GdImage $img, string $raw, int $type): \GdImage
    {
        if ($type !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return $img;
        }
        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($raw));
        $o = (int) ($exif['Orientation'] ?? 1);
        $angle = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($angle !== 0) {
            $rot = imagerotate($img, $angle, 0);
            if ($rot !== false) {
                imagedestroy($img);

                return $rot;
            }
        }

        return $img;
    }
}
