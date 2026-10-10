<?php

namespace App\Support\Datev;

use DateTimeImmutable;
use RuntimeException;

/**
 * Schreibt eine ZIP-Datei ohne die PHP-Erweiterung zip (Dateien werden unkomprimiert abgelegt,
 * das reicht für PDF und Fotos, die schon komprimiert sind). Dateinamen sind UTF-8.
 * Die Datei wird Stück für Stück auf die Platte geschrieben, nicht im Speicher aufgebaut.
 */
class ZipWriter
{
    /** @var resource */
    private $handle;

    /** @var array<int, array{name: string, crc: int, size: int, offset: int}> */
    private array $entries = [];

    private int $offset = 0;

    private int $dosTime;

    private int $dosDate;

    private bool $closed = false;

    public function __construct(private string $path, ?DateTimeImmutable $time = null)
    {
        $handle = @fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Die ZIP-Datei konnte nicht angelegt werden.');
        }
        $this->handle = $handle;

        $time ??= new DateTimeImmutable('now');
        $year = max(1980, (int) $time->format('Y'));
        $this->dosTime = ((int) $time->format('G') << 11) | ((int) $time->format('i') << 5) | intdiv((int) $time->format('s'), 2);
        $this->dosDate = (($year - 1980) << 9) | ((int) $time->format('n') << 5) | (int) $time->format('j');
    }

    public function addFile(string $name, string $content): void
    {
        if ($this->closed) {
            throw new RuntimeException('Die ZIP-Datei ist schon abgeschlossen.');
        }
        if (count($this->entries) >= 65000 || strlen($content) > 0xFFFFFFF0 || $this->offset > 0xFFFFFFF0) {
            throw new RuntimeException('Die ZIP-Datei wird zu groß.');
        }

        $size = strlen($content);
        $crc = (int) hexdec(hash('crc32b', $content));
        $header = pack('VvvvvvVVVvv', 0x04034B50, 20, 0x0800, 0, $this->dosTime, $this->dosDate, $crc, $size, $size, strlen($name), 0).$name;

        $this->write($header);
        $this->write($content);

        $this->entries[] = ['name' => $name, 'crc' => $crc, 'size' => $size, 'offset' => $this->offset];
        $this->offset += strlen($header) + $size;
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $start = $this->offset;
        $central = '';
        foreach ($this->entries as $e) {
            $central .= pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014B50, 20, 20, 0x0800, 0, $this->dosTime, $this->dosDate,
                $e['crc'], $e['size'], $e['size'], strlen($e['name']), 0, 0, 0, 0, 0, $e['offset']
            ).$e['name'];
        }
        $this->write($central);

        $count = count($this->entries);
        $this->write(pack('VvvvvVVv', 0x06054B50, 0, 0, $count, $count, strlen($central), $start, 0));

        fclose($this->handle);
        $this->closed = true;
    }

    private function write(string $data): void
    {
        if ($data !== '' && fwrite($this->handle, $data) !== strlen($data)) {
            throw new RuntimeException('Die ZIP-Datei konnte nicht geschrieben werden. Ist genug Platz frei?');
        }
    }
}
