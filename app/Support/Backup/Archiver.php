<?php

namespace App\Support\Backup;

use FilesystemIterator;
use Phar;
use PharData;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

/**
 * Packt Datenbanksicherung, Programmdateien (inklusive .env) und eine Anleitung in ein Archiv.
 * Nimmt eine ZIP-Datei, wenn PHP das kann, sonst eine tar.gz-Datei.
 */
class Archiver
{
    /** Ordner, die nicht gesichert werden (neu installierbar oder flüchtig). */
    private const SKIP_PREFIXES = [
        'vendor/',
        'node_modules/',
        '.git/',
        'bootstrap/cache/',
        'storage/logs/',
        'storage/framework/cache/',
        'storage/framework/sessions/',
        'storage/framework/views/',
        'storage/app/backups/',
    ];

    public static function extension(): string
    {
        return class_exists(ZipArchive::class) ? 'zip' : 'tar.gz';
    }

    /**
     * @param  string  $targetBase  Pfad ohne Endung, z. B. /tmp/paket
     * @return string Pfad der erzeugten Datei (mit Endung)
     */
    public static function create(string $targetBase, string $sqlFile, string $appPath, string $readme): string
    {
        $files = self::collect($appPath);

        return self::extension() === 'zip'
            ? self::createZip($targetBase.'.zip', $sqlFile, $appPath, $files, $readme)
            : self::createTar($targetBase, $sqlFile, $appPath, $files, $readme);
    }

    /** @return string[] relative Pfade (mit /) aller zu sichernden Dateien */
    public static function collect(string $appPath): array
    {
        $appPath = rtrim($appPath, '/');
        $filter = new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($appPath, FilesystemIterator::SKIP_DOTS),
            function ($current) use ($appPath) {
                if ($current->isLink()) {
                    return false;
                }
                $relative = substr($current->getPathname(), strlen($appPath) + 1);
                $relative .= $current->isDir() ? '/' : '';

                return ! self::skipped($relative);
            }
        );

        $files = [];
        foreach (new RecursiveIteratorIterator($filter) as $file) {
            if ($file->isFile() && $file->isReadable()) {
                $files[] = substr($file->getPathname(), strlen($appPath) + 1);
            }
        }
        sort($files);

        return $files;
    }

    public static function skipped(string $relative): bool
    {
        foreach (self::SKIP_PREFIXES as $prefix) {
            if (str_starts_with($relative, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private static function createZip(string $path, string $sqlFile, string $appPath, array $files, string $readme): string
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Das Archiv konnte nicht angelegt werden.');
        }

        $zip->addFile($sqlFile, 'datenbank.sql');
        foreach ($files as $relative) {
            $zip->addFile(rtrim($appPath, '/').'/'.$relative, 'dateien/'.$relative);
        }
        $zip->addFromString('LIESMICH.txt', $readme);

        if (! $zip->close()) {
            throw new RuntimeException('Das Archiv konnte nicht fertiggestellt werden.');
        }

        return $path;
    }

    private static function createTar(string $base, string $sqlFile, string $appPath, array $files, string $readme): string
    {
        @unlink($base.'.tar');
        @unlink($base.'.tar.gz');

        $tar = new PharData($base.'.tar');
        $tar->addFile($sqlFile, 'datenbank.sql');
        foreach ($files as $relative) {
            $tar->addFile(rtrim($appPath, '/').'/'.$relative, 'dateien/'.$relative);
        }
        $tar->addFromString('LIESMICH.txt', $readme);
        $tar->compress(Phar::GZ);
        unset($tar);
        @unlink($base.'.tar');

        if (! is_file($base.'.tar.gz')) {
            throw new RuntimeException('Das Archiv konnte nicht fertiggestellt werden.');
        }

        return $base.'.tar.gz';
    }
}
