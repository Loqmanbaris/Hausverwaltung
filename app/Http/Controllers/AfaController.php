<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\Property;
use App\Models\PropertyAfa;
use App\Models\PropertyAfaYear;
use App\Models\PropertyValue;
use App\Support\Audit;
use App\Support\Money;
use App\Support\Tax\Afa;
use Illuminate\Http\Request;

/** Gebäude-AfA je Objekt (Paket 29). */
class AfaController extends Controller
{
    use ScopesOwners;

    public function edit(Property $property)
    {
        $this->assertOwnerAccess((int) $property->owner_id);
        $a = PropertyAfa::for((int) $property->id);
        $v = PropertyValue::query()->where('property_id', $property->id)->first();
        $start = $a->start_date?->format('Y-m-d') ?? $v?->purchase_date?->format('Y-m-d');
        $basis = Afa::basisCents($a, $v);
        $years = [];
        if ($a->exists && $start !== null && $basis !== null) {
            for ($y = (int) substr($start, 0, 4); $y <= (int) date('Y'); $y++) {
                $years[$y] = Afa::forYear((int) $property->id, $y);
            }
        }

        return view('afa.edit', [
            'property' => $property, 'a' => $a, 'v' => $v, 'start' => $start, 'basis' => $basis, 'years' => $years,
            'overrides' => PropertyAfaYear::query()->where('property_id', $property->id)->orderBy('year')->get(),
        ]);
    }

    public function update(Request $request, Property $property)
    {
        $this->assertOwnerAccess((int) $property->owner_id);
        $data = $request->validate([
            'building_share' => ['nullable', 'string', 'max:10'], 'basis_override' => ['nullable', 'string', 'max:20'], 'extra_basis' => ['nullable', 'string', 'max:20'],
            'rate' => ['required', 'string', 'max:10'], 'start_date' => ['nullable', 'date_format:Y-m-d'], 'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $errors = [];
        $share = trim((string) ($data['building_share'] ?? ''));
        $shareVal = $share === '' ? null : str_replace(',', '.', $share);
        if ($shareVal !== null && (! is_numeric($shareVal) || (float) $shareVal <= 0 || (float) $shareVal > 100)) {
            $errors['building_share'] = 'Der Gebäudeanteil muss zwischen 0 und 100 Prozent liegen.';
        }
        $rateVal = str_replace(',', '.', trim($data['rate']));
        if (! is_numeric($rateVal) || (float) $rateVal <= 0 || (float) $rateVal > 20) {
            $errors['rate'] = 'Bitte einen AfA-Satz zwischen 0 und 20 Prozent eingeben.';
        }
        $money = function (string $k) use ($data, &$errors) {
            $raw = trim((string) ($data[$k] ?? ''));
            if ($raw === '') {
                return null;
            }
            $p = Money::parse($raw);
            if ($p === null || (float) $p < 0) {
                $errors[$k] = 'Bitte einen gültigen Betrag eingeben.';
            }

            return $p;
        };
        $override = $money('basis_override');
        $extra = $money('extra_basis');
        if ($errors !== []) {
            return back()->withErrors($errors)->withInput();
        }
        $a = PropertyAfa::for((int) $property->id);
        $old = $a->exists ? $a->only(['building_share', 'basis_override', 'extra_basis', 'rate', 'start_date']) : null;
        $a->fill([
            'property_id' => $property->id, 'building_share' => $shareVal, 'basis_override' => $override, 'extra_basis' => $extra ?? 0,
            'rate' => $rateVal, 'start_date' => $data['start_date'] ?? null, 'note' => trim((string) ($data['note'] ?? '')) ?: null,
        ])->save();
        Audit::log('afa.updated', $property, 'AfA-Angaben gespeichert: '.$property->name, $old, $a->only(['building_share', 'basis_override', 'extra_basis', 'rate', 'start_date']));

        return redirect()->route('afa.edit', $property)->with('status', 'AfA-Angaben gespeichert.');
    }

    public function yearStore(Request $request, Property $property)
    {
        $this->assertOwnerAccess((int) $property->owner_id);
        $data = $request->validate(['year' => ['required', 'integer', 'between:1990,'.((int) date('Y') + 1)], 'amount' => ['required', 'string', 'max:20'], 'note' => ['nullable', 'string', 'max:190']]);
        $amount = Money::parse($data['amount']);
        if ($amount === null || (float) $amount < 0) {
            return back()->withErrors(['amount' => 'Bitte einen gültigen Betrag eingeben.'])->withInput();
        }
        PropertyAfaYear::query()->updateOrCreate(['property_id' => $property->id, 'year' => (int) $data['year']], ['amount' => $amount, 'note' => trim((string) ($data['note'] ?? '')) ?: null]);
        Audit::log('afa.year', $property, 'AfA-Jahresbetrag '.$data['year'].' gespeichert: '.$property->name, null, ['jahr' => $data['year'], 'betrag' => $amount]);

        return redirect()->route('afa.edit', $property)->with('status', 'Jahresbetrag gespeichert.');
    }

    public function yearDestroy(Property $property, int $year)
    {
        $this->assertOwnerAccess((int) $property->owner_id);
        PropertyAfaYear::query()->where('property_id', $property->id)->where('year', $year)->delete();
        Audit::log('afa.year_removed', $property, 'AfA-Jahresbetrag '.$year.' entfernt: '.$property->name);

        return redirect()->route('afa.edit', $property)->with('status', 'Jahresbetrag entfernt, das Jahr wird wieder gerechnet.');
    }
}
