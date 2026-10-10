<?php

namespace App\Support\Receipts;

use App\Models\Receipt;
use App\Models\ReceiptFile;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Audit;
use App\Support\Documents\DocumentStore;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Anlegen, Ändern und Archivieren von Belegen. Dateien kommen bereits geprüft hier an. */
class ReceiptService
{
    /**
     * @param  array  $data  aufbereitete Felder (siehe ReceiptRules::normalize)
     * @param  array<int, array>  $files  Ergebnisse von UploadInspector::inspect()
     * @return array{0: Receipt, 1: ?Receipt} der neue Beleg und ein früherer Beleg mit gleicher Datei (oder null)
     */
    public static function create(array $data, array $files, User $user): array
    {
        $stored = [];

        try {
            $receipt = DB::transaction(function () use ($data, $files, $user, &$stored) {
                $receipt = Receipt::create(self::withVendor($data) + ['created_by' => $user->id]);

                foreach (array_values($files) as $i => $file) {
                    $path = DocumentStore::put($file['content']);
                    $stored[] = $path;

                    $receipt->files()->create([
                        'position' => $i + 1,
                        'storage_path' => $path,
                        'original_name' => $file['name'],
                        'mime' => $file['mime'],
                        'size_bytes' => $file['size'],
                        'sha256' => $file['sha256'],
                    ]);
                }

                Audit::log(
                    'receipt.created',
                    $receipt,
                    'Beleg erfasst: '.$receipt->title().' ('.count($files).' Datei'.(count($files) === 1 ? '' : 'en').')',
                    null,
                    self::auditData($data)
                );

                return $receipt;
            });
        } catch (Throwable $e) {
            foreach ($stored as $path) {
                DocumentStore::forget($path);
            }

            throw $e;
        }

        return [$receipt, self::duplicateOf($receipt, $files, $user)];
    }

    public static function update(Receipt $receipt, array $data): void
    {
        DB::transaction(function () use ($receipt, $data) {
            $data = self::withVendor($data);
            $keys = array_keys($data);
            $before = self::auditData($receipt->only($keys));
            $receipt->update($data);
            $receipt->refresh();
            [$old, $new] = Audit::diff($before, self::auditData($receipt->only($keys)));

            if ($new !== null) {
                Audit::log('receipt.updated', $receipt, 'Beleg geändert: '.$receipt->title(), $old, $new);
            }
        });
    }

    /** Beleg archivieren: Er verschwindet aus den Listen, die Dateien bleiben aufbewahrt. */
    public static function archive(Receipt $receipt): void
    {
        DB::transaction(function () use ($receipt) {
            Audit::log('receipt.deleted', $receipt, 'Beleg archiviert: '.$receipt->title());
            $receipt->delete();
        });
    }

    /** Ein anderer, für den Benutzer sichtbarer Beleg mit identischer Datei. */
    private static function duplicateOf(Receipt $receipt, array $files, User $user): ?Receipt
    {
        $hashes = array_values(array_unique(array_column($files, 'sha256')));
        if ($hashes === []) {
            return null;
        }

        $other = ReceiptFile::query()
            ->whereIn('sha256', $hashes)
            ->where('receipt_id', '!=', $receipt->id)
            ->whereHas('receipt', fn ($q) => $q->visibleTo($user))
            ->orderBy('id')
            ->first();

        return $other?->receipt;
    }

    /** Ordnet den eingetippten Lieferanten einem Eintrag in der Lieferantenliste zu (oder legt ihn an). */
    private static function withVendor(array $data): array
    {
        $vendor = Vendor::resolve($data['vendor_name'] ?? null, $data['cost_category'] ?? null);

        $data['vendor_id'] = $vendor?->id;
        $data['vendor_name'] = $vendor?->name;

        return $data;
    }

    private static function auditData(array $values): array
    {
        $out = [];
        foreach ($values as $key => $value) {
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d');
            } elseif (is_bool($value)) {
                $value = $value ? '1' : '0';
            }
            $out[$key] = $value === null ? null : (string) $value;
        }

        return $out;
    }
}
