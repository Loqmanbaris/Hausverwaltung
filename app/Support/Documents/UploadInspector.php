<?php

namespace App\Support\Documents;

/**
 * Prüft eine hochgeladene Datei anhand ihres echten Inhalts (nicht anhand von Name oder
 * Angabe des Browsers). Erlaubt sind Fotos (JPG, PNG, WebP) und PDF.
 * Fotos werden, wenn PHP das kann, neu als JPEG gespeichert: Das entfernt versteckte Angaben
 * wie den Aufnahmeort und dreht das Bild richtig.
 */
class UploadInspector
{
    public const MAX_BYTES = 10 * 1024 * 1024;

    public const MAX_FILES = 10;

    /** Größer als das lassen wir serverseitig unangetastet (Speicherbedarf), der Browser verkleinert vorher. */
    private const MAX_REENCODE_PIXELS = 25_000_000;

    private const MAX_PIXELS = 60_000_000;

    private const MAX_SIDE = 2400;

    public const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    /**
     * @return array{content: string, mime: string, ext: string, size: int, sha256: string, name: string}
     *
     * @throws UploadRejected
     */
    public static function inspect(string $path, string $clientName, bool $reencode = true): array
    {
        $name = self::cleanName($clientName);

        if (! is_file($path) || ! is_readable($path)) {
            throw new UploadRejected("„{$name}“ konnte nicht gelesen werden.");
        }

        $size = (int) filesize($path);
        if ($size === 0) {
            throw new UploadRejected("„{$name}“ ist leer.");
        }
        if ($size > self::MAX_BYTES) {
            throw new UploadRejected("„{$name}“ ist größer als 10 MB.");
        }

        $content = (string) file_get_contents($path);
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->buffer($content);

        if (! isset(self::ALLOWED[$mime])) {
            throw new UploadRejected("„{$name}“ hat ein nicht erlaubtes Format. Erlaubt sind Fotos (JPG, PNG, WebP) und PDF.");
        }

        if ($mime === 'application/pdf') {
            if (! str_starts_with($content, '%PDF-')) {
                throw new UploadRejected("„{$name}“ ist keine gültige PDF-Datei.");
            }
        } else {
            $info = @getimagesizefromstring($content);
            if ($info === false || ($info[0] ?? 0) < 1 || ($info[1] ?? 0) < 1) {
                throw new UploadRejected("Das Foto „{$name}“ ist beschädigt oder kein gültiges Bild.");
            }
            if ($info[0] * $info[1] > self::MAX_PIXELS) {
                throw new UploadRejected("Das Foto „{$name}“ hat zu viele Bildpunkte. Bitte verkleinert aufnehmen.");
            }

            if ($reencode) {
                [$content, $mime] = self::normalizeImage($content, $mime, $info[0], $info[1]);
            }
        }

        return [
            'content' => $content,
            'mime' => $mime,
            'ext' => self::ALLOWED[$mime],
            'size' => strlen($content),
            'sha256' => hash('sha256', $content),
            'name' => $name,
        ];
    }

    public static function canReencode(): bool
    {
        return function_exists('imagecreatefromstring') && function_exists('imagejpeg');
    }

    /** @return array{0: string, 1: string} neuer Inhalt und Dateityp */
    private static function normalizeImage(string $content, string $mime, int $width, int $height): array
    {
        if (! self::canReencode() || $width * $height > self::MAX_REENCODE_PIXELS) {
            return [$content, $mime];
        }

        $image = @imagecreatefromstring($content);
        if ($image === false) {
            return [$content, $mime];
        }

        $orientation = 1;
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($content));
            $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
        }

        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
        if ($angle !== 0 && function_exists('imagerotate')) {
            $rotated = imagerotate($image, $angle, 0xFFFFFF);
            if ($rotated !== false) {
                $image = $rotated;
            }
        }

        $w = imagesx($image);
        $h = imagesy($image);
        $scale = min(1.0, self::MAX_SIDE / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $canvas = imagecreatetruecolor($nw, $nh);
        if ($canvas === false) {
            return [$content, $mime];
        }
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $nw, $nh, $w, $h);

        ob_start();
        $ok = imagejpeg($canvas, null, 85);
        $out = (string) ob_get_clean();

        if (! $ok || $out === '') {
            return [$content, $mime];
        }

        return [$out, 'image/jpeg'];
    }

    private static function cleanName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = (string) preg_replace('/[\x00-\x1F\x7F]+/u', '', $name);
        $name = trim($name);
        if ($name === '') {
            return 'Beleg';
        }

        return mb_substr($name, 0, 150);
    }
}
