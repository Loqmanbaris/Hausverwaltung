<?php

namespace App\Support\EInvoice;

use App\Models\Vendor;
use App\Support\Audit;

/** Ordnet den Rechnungsteller einer E-Rechnung einem Lieferanten zu oder legt ihn an. */
class EInvoiceVendors
{
    /** Für diese Felder übernehmen wir Angaben aus der Rechnung, aber nur wenn beim Lieferanten noch nichts steht. */
    private const FIELDS = ['street' => 190, 'zip' => 10, 'city' => 120, 'email' => 190, 'phone' => 60, 'vat_id' => 20];

    /**
     * Findet zuerst über die USt-ID, dann über den Namen. Gibt null zurück, wenn die Rechnung keinen Namen hat.
     *
     * @param  array{name: ?string, street: ?string, zip: ?string, city: ?string, email: ?string, phone: ?string, vat_id: ?string}  $seller
     */
    public static function match(array $seller): ?Vendor
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', (string) ($seller['name'] ?? '')));
        $key = Vendor::normalizeName($name);
        if ($key === '') {
            return null;
        }

        $values = [];
        foreach (self::FIELDS as $field => $max) {
            $v = trim((string) ($seller[$field] ?? ''));
            if ($field === 'vat_id') {
                $v = self::normalizeVat($v) ?? '';
            }
            $values[$field] = $v === '' ? null : mb_substr($v, 0, $max);
        }

        $vendor = null;
        if ($values['vat_id'] !== null) {
            $vendor = Vendor::query()
                ->whereRaw("UPPER(REPLACE(vat_id, ' ', '')) = ?", [$values['vat_id']])
                ->orderBy('id')
                ->first();
        }
        $vendor ??= Vendor::query()->where('name_key', $key)->first();

        if ($vendor === null) {
            $vendor = Vendor::create(array_filter($values, fn ($v) => $v !== null) + [
                'name' => mb_substr($name, 0, 190),
                'name_key' => $key,
                'active' => true,
            ]);
            Audit::log('vendor.created', $vendor, 'Lieferant angelegt (aus E-Rechnung): '.$vendor->name);

            return $vendor;
        }

        $filled = [];
        foreach ($values as $field => $value) {
            if ($value !== null && trim((string) $vendor->{$field}) === '') {
                $filled[$field] = $value;
            }
        }
        if ($filled !== []) {
            $vendor->update($filled);
            Audit::log('vendor.updated', $vendor, 'Lieferant ergänzt (aus E-Rechnung): '.$vendor->name, null, $filled);
        }

        return $vendor;
    }

    /** „de 123 456 789“ wird zu „DE123456789“. Ungültiges wird zu null. */
    public static function normalizeVat(?string $value): ?string
    {
        $v = mb_strtoupper((string) preg_replace('/[\s.\-\/]+/u', '', (string) $value));

        return preg_match('/^[A-Z]{2}[A-Z0-9]{2,18}$/', $v) === 1 ? $v : null;
    }
}
