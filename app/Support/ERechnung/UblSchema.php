<?php

namespace App\Support\ERechnung;

use RuntimeException;

/** Legt die Schema-Dateien für die E-Rechnungs-Prüfung im Cache-Ordner ab (Paket 37). */
class UblSchema
{
    /** Pfad zur Haupt-Schemadatei. Schreibt die Dateien, wenn sie fehlen oder veraltet sind. */
    public static function mainPath(): string
    {
        $files = UblSchemaFiles::files();
        $dir = storage_path('framework/cache/ubl21-'.substr(sha1(implode('|', array_keys($files)).strlen(implode('', $files))), 0, 10));
        $main = $dir.'/maindoc/UBL-Invoice-2.1.xsd';
        if (! is_file($main)) {
            foreach ($files as $rel => $content) {
                $target = $dir.'/'.$rel;
                if (! is_dir(dirname($target)) && ! @mkdir(dirname($target), 0775, true) && ! is_dir(dirname($target))) {
                    throw new RuntimeException('Der Cache-Ordner ist nicht beschreibbar.');
                }
                if (file_put_contents($target, $content) === false) {
                    throw new RuntimeException('Schema-Datei konnte nicht geschrieben werden.');
                }
            }
        }

        return $main;
    }
}
