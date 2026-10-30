<?php

namespace App\Http\Controllers;

/**
 * Liefert die Dateien des Beleg-Scanners aus (Paket 47): Scanner-Skript und die Texterkennung Tesseract.js (Apache-2.0).
 * Alles läuft im Browser des Anwenders. Es gehen keine Bilder an Dritte. Nur eine feste Liste von Dateien wird ausgeliefert.
 */
class ScanAssetController extends Controller
{
    private const FILES = [
        'scanner.js' => ['scanner.js', 'application/javascript; charset=utf-8', false],
        'tesseract.min.js' => ['tesseract.min.js', 'application/javascript; charset=utf-8', false],
        'worker.min.js' => ['worker.min.js', 'application/javascript; charset=utf-8', false],
        'tesseract-core-simd-lstm.wasm.js' => ['tesseract-core-simd-lstm.wasm.js', 'application/javascript; charset=utf-8', false],
        'tesseract-core-lstm.wasm.js' => ['tesseract-core-lstm.wasm.js', 'application/javascript; charset=utf-8', false],
        'deu.traineddata.gz' => ['deu.traineddata.b64.js', 'application/gzip', true],
    ];

    public static function names(): array
    {
        return array_keys(self::FILES);
    }

    public static function path(string $name): string
    {
        return base_path('resources/js/ocr/'.(self::FILES[$name][0] ?? ''));
    }

    /** Kurzer Stempel für die Adresse, damit ein neues Skript nicht aus dem Zwischenspeicher kommt. */
    public static function stamp(string $name): string
    {
        $p = self::path($name);

        return is_file($p) ? substr(md5_file($p), 0, 8) : '0';
    }

    public function show(string $file)
    {
        abort_unless(isset(self::FILES[$file]), 404);
        [, $type, $base64] = self::FILES[$file];
        $path = self::path($file);
        abort_unless(is_file($path), 404);
        $body = (string) file_get_contents($path);
        if ($base64) {
            $body = (string) base64_decode(trim($body), true);
        }

        return response($body, 200, [
            'Content-Type' => $type,
            // Das Skript trägt einen Stempel in der Adresse und darf lange gemerkt werden, die Bibliotheken eine Woche
            'Cache-Control' => $file === 'scanner.js' ? 'public, max-age=31536000, immutable' : 'public, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
