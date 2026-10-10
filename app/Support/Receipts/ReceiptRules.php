<?php

namespace App\Support\Receipts;

use App\Models\Property;
use App\Models\Unit;
use App\Support\Documents\UploadInspector;
use App\Support\Kostenarten;
use App\Support\Money;
use App\Support\Stammdaten;
use Closure;
use DateTimeImmutable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Prüfregeln und Aufbereitung der Eingaben für Belege. */
class ReceiptRules
{
    /**
     * @param  int[]  $ownerIds  Eigentümer, die der Benutzer sehen darf
     * @param  ?string  $currentCategory  schon gespeicherte Kostenart (bleibt auch stillgelegt erlaubt)
     */
    public static function rules(array $ownerIds, bool $withFiles, ?string $currentCategory = null): array
    {
        $rules = [
            'owner_id' => ['required', 'integer', Rule::in($ownerIds)],
            'owner_level' => ['nullable', 'boolean'],
            'property_id' => ['nullable', 'integer'],
            'unit_id' => ['nullable', 'integer'],
            'receipt_date' => ['nullable', 'date_format:Y-m-d', self::dateRange()],
            'amount_gross' => ['nullable', self::amountRule()],
            'cost_category' => ['nullable', Rule::in(Kostenarten::allowed($currentCategory))],
            'vendor_name' => ['nullable', 'string', 'max:190'],
            'note' => ['nullable', 'string', 'max:5000'],
        ];

        if ($withFiles) {
            $rules['files'] = ['required', 'array', 'min:1', 'max:'.UploadInspector::MAX_FILES];
            $rules['files.*'] = ['file'];
        }

        return $rules;
    }

    public static function messages(): array
    {
        return Stammdaten::messages() + [
            'files.required' => 'Bitte mindestens ein Foto oder eine PDF-Datei anhängen.',
            'files.array' => 'Die Dateien konnten nicht gelesen werden.',
            'min.array' => 'Bitte mindestens ein Foto oder eine PDF-Datei anhängen.',
            'max.array' => 'Pro Beleg sind höchstens :max Dateien möglich.',
            'files.*.file' => 'Eine Datei konnte nicht hochgeladen werden. Vermutlich ist sie zu groß (höchstens 10 MB).',
            'files.*.uploaded' => 'Eine Datei konnte nicht hochgeladen werden. Vermutlich ist sie zu groß (höchstens 10 MB).',
            'owner_id.in' => 'Bitte einen Eigentümer aus der Liste wählen.',
            'receipt_date.date_format' => 'Das Datum ist ungültig.',
        ];
    }

    public static function attributes(): array
    {
        return Stammdaten::attributes() + [
            'receipt_date' => 'Datum',
            'amount_gross' => 'Betrag',
            'cost_category' => 'Kostenart',
            'vendor_name' => 'Lieferant',
            'note' => 'Notiz',
            'property_id' => 'Objekt',
            'unit_id' => 'Einheit',
            'files' => 'Dateien',
        ];
    }

    /**
     * Macht aus den geprüften Eingaben die Felder für die Datenbank und stellt sicher,
     * dass Objekt und Einheit zum gewählten Eigentümer gehören.
     *
     * @throws ValidationException
     */
    public static function normalize(array $data): array
    {
        $ownerId = (int) $data['owner_id'];
        $ownerLevel = ! empty($data['owner_level']);
        $propertyId = $ownerLevel ? null : (int) ($data['property_id'] ?? 0);
        $unitId = $ownerLevel ? null : (int) ($data['unit_id'] ?? 0);

        if ($propertyId) {
            $property = Property::query()->find($propertyId);
            if ($property === null || (int) $property->owner_id !== $ownerId) {
                throw ValidationException::withMessages(['property_id' => 'Das Objekt gehört nicht zu diesem Eigentümer.']);
            }
        }

        if ($unitId) {
            if (! $propertyId) {
                throw ValidationException::withMessages(['unit_id' => 'Für eine Einheit bitte zuerst das Objekt wählen.']);
            }
            $unit = Unit::query()->find($unitId);
            if ($unit === null || (int) $unit->property_id !== $propertyId) {
                throw ValidationException::withMessages(['unit_id' => 'Die Einheit gehört nicht zu diesem Objekt.']);
            }
        }

        return [
            'owner_id' => $ownerId,
            'owner_level' => $ownerLevel,
            'property_id' => $propertyId ?: null,
            'unit_id' => $unitId ?: null,
            'receipt_date' => self::blank($data['receipt_date'] ?? null),
            'amount_gross' => Money::parse($data['amount_gross'] ?? null),
            'cost_category' => self::blank($data['cost_category'] ?? null),
            'vendor_name' => self::blank($data['vendor_name'] ?? null),
            'note' => self::blank($data['note'] ?? null),
        ];
    }

    private static function blank(mixed $value): ?string
    {
        $v = trim((string) $value);

        return $v === '' ? null : $v;
    }

    private static function amountRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (Money::parse($value) === null) {
                $fail('Der Betrag ist ungültig (z. B. 123,45).');
            }
        };
    }

    private static function dateRange(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);
            if ($date === false) {
                return;
            }
            if ($date < new DateTimeImmutable('2000-01-01') || $date > new DateTimeImmutable('+1 year')) {
                $fail('Das Datum ist nicht plausibel. Bitte prüfen.');
            }
        };
    }
}
