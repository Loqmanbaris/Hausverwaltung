<?php

namespace App\Support\Backup;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Schreibt die Datenbank als SQL-Datei (Tabellenaufbau und Inhalt). Braucht kein mysqldump.
 * Alle Tabellen werden aus demselben Zeitpunkt gelesen (konsistenter Schnappschuss).
 */
class DatabaseDumper
{
    private const BATCH = 500;

    /**
     * @param  string[]  $skip  Tabellen, die nicht gesichert werden (flüchtige Daten)
     * @return array{tables: int, rows: int}
     */
    public static function dump(PDO $pdo, string $file, array $skip = []): array
    {
        $out = @fopen($file, 'wb');
        if ($out === false) {
            throw new RuntimeException('Die Datei für die Datenbanksicherung konnte nicht angelegt werden.');
        }

        $tables = 0;
        $rows = 0;

        try {
            $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');

            fwrite($out, "-- Datenbanksicherung vom ".date('d.m.Y H:i:s')."\n");
            fwrite($out, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET UNIQUE_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

            $names = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);

            foreach ($names as $row) {
                $name = (string) $row[0];
                if (in_array($name, $skip, true)) {
                    continue;
                }

                $rows += self::dumpTable($pdo, $out, $name);
                $tables++;
            }

            fwrite($out, "SET FOREIGN_KEY_CHECKS=1;\nSET UNIQUE_CHECKS=1;\n");
            $pdo->exec('COMMIT');
        } catch (Throwable $e) {
            try {
                $pdo->exec('ROLLBACK');
            } catch (Throwable) {
                // ignorieren
            }
            throw $e;
        } finally {
            fclose($out);
        }

        return ['tables' => $tables, 'rows' => $rows];
    }

    private static function dumpTable(PDO $pdo, $out, string $name): int
    {
        $quoted = self::identifier($name);

        $create = $pdo->query("SHOW CREATE TABLE {$quoted}")->fetch(PDO::FETCH_NUM);
        fwrite($out, "DROP TABLE IF EXISTS {$quoted};\n{$create[1]};\n\n");

        $order = self::orderBy($pdo, $quoted);
        $offset = 0;
        $total = 0;

        do {
            $batch = $pdo->query("SELECT * FROM {$quoted}{$order} LIMIT ".self::BATCH." OFFSET {$offset}")
                ->fetchAll(PDO::FETCH_ASSOC);

            if ($batch === []) {
                break;
            }

            $columns = implode(',', array_map([self::class, 'identifier'], array_keys($batch[0])));
            $lines = [];
            foreach ($batch as $record) {
                $values = array_map(
                    fn ($value) => $value === null ? 'NULL' : $pdo->quote((string) $value),
                    array_values($record)
                );
                $lines[] = '('.implode(',', $values).')';
            }

            fwrite($out, "INSERT INTO {$quoted} ({$columns}) VALUES\n".implode(",\n", $lines).";\n");

            $total += count($batch);
            $offset += self::BATCH;
        } while (count($batch) === self::BATCH);

        fwrite($out, "\n");

        return $total;
    }

    private static function orderBy(PDO $pdo, string $quotedTable): string
    {
        $keys = $pdo->query("SHOW KEYS FROM {$quotedTable} WHERE Key_name = 'PRIMARY'")->fetchAll(PDO::FETCH_ASSOC);
        if ($keys === []) {
            return '';
        }

        usort($keys, fn ($a, $b) => (int) $a['Seq_in_index'] <=> (int) $b['Seq_in_index']);

        return ' ORDER BY '.implode(',', array_map(fn ($k) => self::identifier((string) $k['Column_name']), $keys));
    }

    public static function identifier(string $name): string
    {
        return '`'.str_replace('`', '``', $name).'`';
    }
}
