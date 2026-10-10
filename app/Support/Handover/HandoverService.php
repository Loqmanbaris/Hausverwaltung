<?php

namespace App\Support\Handover;

use App\Models\HandoverPhoto;
use App\Models\HandoverProtocol;
use App\Models\HandoverReading;
use App\Models\Lease;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\User;
use App\Support\Audit;
use App\Support\Contracts\ContractService;
use App\Support\Documents\DocumentStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Übergabeprotokoll anlegen, speichern, Fotos, abschließen mit Zählerständen (Paket 13). */
class HandoverService
{
    public const STATES = [
        'gut' => 'in Ordnung',
        'gebrauch' => 'Gebrauchsspuren',
        'mangel' => 'Mangel / Schaden',
        'na' => 'entfällt',
    ];

    public const BASE_ITEMS = ['Wände und Decke', 'Boden', 'Fenster', 'Türen', 'Heizkörper', 'Steckdosen, Schalter, Licht'];

    /** Raumname enthält … => Prüfpunkte */
    public const ROOM_ITEMS = [
        'küche' => ['Wände und Decke', 'Boden', 'Fenster', 'Türen', 'Heizkörper', 'Steckdosen, Schalter, Licht', 'Einbauküche und Geräte', 'Spüle und Armaturen'],
        'bad' => ['Wände und Decke', 'Fliesen', 'Boden', 'Fenster oder Lüftung', 'Türen', 'Heizkörper', 'Waschbecken und Armaturen', 'WC', 'Dusche oder Badewanne', 'Spiegel, Zubehör'],
        'wc' => ['Wände und Decke', 'Boden', 'Türen', 'Waschbecken und Armaturen', 'WC', 'Lüftung'],
        'balkon' => ['Boden', 'Geländer', 'Außenwand', 'Beleuchtung'],
        'terrasse' => ['Boden', 'Geländer', 'Außenwand', 'Beleuchtung'],
        'keller' => ['Wände', 'Boden', 'Tür und Schloss', 'Licht'],
        'garage' => ['Tor', 'Boden', 'Wände', 'Licht'],
    ];

    public static function itemsFor(string $room): array
    {
        $l = mb_strtolower($room);
        foreach (self::ROOM_ITEMS as $k => $items) {
            if (str_contains($l, $k)) {
                return $items;
            }
        }

        return self::BASE_ITEMS;
    }

    public static function defaultRooms(Lease $lease): array
    {
        $rooms = (int) round((float) ($lease->unit?->rooms ?? 2));
        $names = match ($lease->lease_type) {
            'gewerbe' => ['Hauptraum', 'Nebenraum', 'WC', 'Lager oder Keller'],
            default => array_merge(['Flur', 'Wohnzimmer'], $rooms >= 2 ? ['Schlafzimmer'] : [], $rooms >= 3 ? ['Zimmer 3'] : [], $rooms >= 4 ? ['Zimmer 4'] : [], ['Küche', 'Bad', 'Balkon', 'Keller']),
        };

        return array_map(fn ($n) => ['name' => $n, 'items' => array_map(fn ($i) => ['name' => $i, 'state' => '', 'note' => ''], self::itemsFor($n)), 'note' => ''], $names);
    }

    public static function defaultKeys(): array
    {
        return array_map(fn ($k) => ['label' => $k, 'count' => ''], ['Haustür', 'Wohnungstür', 'Briefkasten', 'Keller']);
    }

    public static function create(Lease $lease, string $kind, User $user): HandoverProtocol
    {
        $lease->loadMissing('unit');
        $rooms = self::defaultRooms($lease);
        $keys = self::defaultKeys();
        $compare = null;
        if ($kind === 'auszug') {
            $compare = HandoverProtocol::query()->where('lease_id', $lease->id)->where('kind', 'einzug')->where('status', 'abgeschlossen')->orderByDesc('handover_on')->first();
            if ($compare) {
                // Gleiche Räume und Punkte wie beim Einzug, Zustand neu erfassen
                $rooms = array_map(fn ($r) => ['name' => $r['name'], 'items' => array_map(fn ($i) => ['name' => $i['name'], 'state' => '', 'note' => ''], $r['items'] ?? []), 'note' => ''], $compare->rooms ?? []);
                $keys = array_map(fn ($k) => ['label' => $k['label'], 'count' => ''], $compare->keys ?? []);
            }
        }

        return DB::transaction(function () use ($lease, $kind, $user, $rooms, $keys, $compare) {
            $p = HandoverProtocol::create([
                'lease_id' => $lease->id,
                'owner_id' => $lease->owner_id,
                'kind' => $kind,
                'handover_on' => $kind === 'einzug' ? $lease->start_date->format('Y-m-d') : ($lease->end_date?->format('Y-m-d') ?? date('Y-m-d')),
                'status' => 'entwurf',
                'rooms' => $rooms,
                'keys' => $keys,
                'compare_id' => $compare?->id,
                'created_by' => $user->id,
            ]);
            foreach (Meter::query()->where('unit_id', $lease->unit_id)->where('active', true)->orderBy('kind')->get() as $m) {
                HandoverReading::create(['handover_protocol_id' => $p->id, 'meter_id' => $m->id]);
            }
            Audit::log('handover.created', $p, 'Übergabeprotokoll '.HandoverProtocol::KINDS[$kind].' angelegt: '.$lease->title());

            return $p;
        });
    }

    /** Formular übernehmen. */
    public static function save(HandoverProtocol $p, array $data): void
    {
        if (! $p->isDraft()) {
            throw new RuntimeException('Das Protokoll ist abgeschlossen.');
        }
        $rooms = [];
        $map = [];   // alter Raum-Index => neuer Index (für die Fotos)
        foreach ($data['rooms'] ?? [] as $oldIndex => $r) {
            $name = trim((string) ($r['name'] ?? ''));
            if ($name === '' && empty($r['items'])) {
                continue;
            }
            $map[(int) $oldIndex] = count($rooms);
            $items = [];
            foreach ($r['items'] ?? [] as $i) {
                $iname = trim((string) ($i['name'] ?? ''));
                if ($iname === '') {
                    continue;
                }
                $state = (string) ($i['state'] ?? '');
                $items[] = ['name' => mb_substr($iname, 0, 100), 'state' => isset(self::STATES[$state]) ? $state : '', 'note' => mb_substr(trim((string) ($i['note'] ?? '')), 0, 500)];
            }
            $rooms[] = ['name' => mb_substr($name ?: 'Raum', 0, 100), 'items' => $items, 'note' => mb_substr(trim((string) ($r['note'] ?? '')), 0, 2000)];
        }
        $keys = [];
        foreach ($data['keys'] ?? [] as $k) {
            $label = trim((string) ($k['label'] ?? ''));
            if ($label !== '') {
                $keys[] = ['label' => mb_substr($label, 0, 100), 'count' => preg_replace('/[^0-9]/', '', (string) ($k['count'] ?? ''))];
            }
        }
        DB::transaction(function () use ($p, $data, $rooms, $keys, $map) {
            foreach ($p->photos()->get() as $ph) {
                if (! array_key_exists((int) $ph->room, $map)) {
                    DocumentStore::forget($ph->path);
                    DocumentStore::forget($ph->thumb);
                    $ph->delete();
                } elseif ($map[(int) $ph->room] !== (int) $ph->room) {
                    $ph->update(['room' => $map[(int) $ph->room]]);
                }
            }
            $p->update([
                'handover_on' => $data['handover_on'] ?? $p->handover_on->format('Y-m-d'),
                'attendees' => trim((string) ($data['attendees'] ?? '')) ?: null,
                'note' => trim((string) ($data['note'] ?? '')) ?: null,
                'new_address' => trim((string) ($data['new_address'] ?? '')) ?: null,
                'rooms' => $rooms,
                'keys' => $keys,
            ]);
            foreach ($data['readings'] ?? [] as $id => $value) {
                $v = trim((string) $value);
                // Deutsche Schreibweise: Komma = Dezimaltrenner, Punkt = Tausender (1.234 = 1234)
                if (str_contains($v, ',')) {
                    $v = str_replace(['.', ','], ['', '.'], $v);
                } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $v)) {
                    $v = str_replace('.', '', $v);
                }
                HandoverReading::query()->where('handover_protocol_id', $p->id)->whereKey((int) $id)
                    ->update(['value' => $v === '' || ! is_numeric($v) ? null : $v]);
            }
        });
    }

    /**
     * Abschließen: Unterschriften (Tablet) oder Papier, Zählerstände übernehmen, Inhalt sperren.
     *
     * @throws RuntimeException
     */
    public static function finalize(HandoverProtocol $p, string $method, ?string $tenantPng, ?string $landlordPng, string $tenantName, string $landlordName, User $user): void
    {
        if (! $p->isDraft()) {
            throw new RuntimeException('Das Protokoll ist schon abgeschlossen.');
        }
        $paths = [];
        if ($method === 'tablet') {
            $t = ContractService::decodeSignature((string) $tenantPng, 'Mieter');
            $l = ContractService::decodeSignature((string) $landlordPng, 'Vermieter');
            $paths['tenant_signature'] = DocumentStore::put($t);
            $paths['landlord_signature'] = DocumentStore::put($l);
        }
        try {
            DB::transaction(function () use ($p, $method, $paths, $tenantName, $landlordName, $user) {
                $label = 'Übergabe '.HandoverProtocol::KINDS[$p->kind];
                foreach ($p->readings()->with('meter')->get() as $r) {
                    if ($r->value === null || ! $r->meter) {
                        continue;
                    }
                    $mr = MeterReading::query()->updateOrCreate(
                        ['meter_id' => $r->meter_id, 'read_on' => $p->handover_on->format('Y-m-d')],
                        ['value' => $r->value, 'note' => $label, 'created_by' => $user->id]
                    );
                    $r->update(['meter_reading_id' => $mr->id]);
                }
                $p->update($paths + [
                    'status' => 'abgeschlossen',
                    'sign_method' => $method,
                    'signed_at' => now(),
                    'tenant_signer' => mb_substr($tenantName, 0, 190) ?: null,
                    'landlord_signer' => mb_substr($landlordName, 0, 190) ?: null,
                    'content_hash' => self::hash($p),
                ]);
                Audit::log('handover.finalized', $p, $p->title().' abgeschlossen ('.($method === 'tablet' ? 'am Tablet unterschrieben' : 'auf Papier').'): '.$p->lease?->title());
            });
        } catch (\Throwable $e) {
            foreach ($paths as $path) {
                DocumentStore::forget($path);
            }

            throw $e;
        }
    }

    public static function hash(HandoverProtocol $p): string
    {
        $readings = $p->readings()->orderBy('meter_id')->get(['meter_id', 'value'])->map(fn ($r) => [$r->meter_id, (string) $r->value])->all();

        return hash('sha256', json_encode([$p->kind, $p->handover_on->format('Y-m-d'), $p->attendees, $p->rooms, $p->keys, $p->note, $p->new_address, $readings], JSON_UNESCAPED_UNICODE));
    }

    public static function delete(HandoverProtocol $p): void
    {
        foreach ($p->photos as $ph) {
            DocumentStore::forget($ph->path);
            DocumentStore::forget($ph->thumb);
        }
        Audit::log('handover.deleted', $p, $p->title().' (Entwurf) gelöscht: '.$p->lease?->title());
        $p->delete();
    }

    /** Foto verkleinern und verschlüsselt ablegen. @throws RuntimeException */
    public static function storePhoto(HandoverProtocol $p, int $room, UploadedFile $file): HandoverPhoto
    {
        if (! function_exists('imagecreatefromstring')) {
            throw new RuntimeException('Auf dem Server fehlt die Bildbearbeitung (GD).');
        }
        $raw = (string) file_get_contents($file->getRealPath());
        $info = @getimagesizefromstring($raw);
        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) || $info[0] * $info[1] > 40_000_000) {
            throw new RuntimeException('„'.$file->getClientOriginalName().'“ ist kein lesbares Foto.');
        }
        @ini_set('memory_limit', '512M');
        $img = @imagecreatefromstring($raw);
        if ($img === false) {
            throw new RuntimeException('„'.$file->getClientOriginalName().'“ konnte nicht gelesen werden.');
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

        return HandoverPhoto::create(['handover_protocol_id' => $p->id, 'room' => $room, 'path' => $large, 'thumb' => $thumb]);
    }

    /** Vergleich mit dem Einzug: [raum][punkt] => Zustand */
    public static function previousStates(?HandoverProtocol $compare): array
    {
        $out = [];
        foreach ($compare?->rooms ?? [] as $r) {
            foreach ($r['items'] ?? [] as $i) {
                $out[mb_strtolower($r['name'])][mb_strtolower($i['name'])] = ['state' => $i['state'] ?? '', 'note' => $i['note'] ?? ''];
            }
        }

        return $out;
    }

    /** Neuer Mangel gegenüber dem Einzug? */
    public static function isNewDefect(array $prev, string $room, string $item, string $state): bool
    {
        if ($state !== 'mangel') {
            return false;
        }
        $was = $prev[mb_strtolower($room)][mb_strtolower($item)]['state'] ?? null;

        return $was !== null && $was !== 'mangel';
    }
}
