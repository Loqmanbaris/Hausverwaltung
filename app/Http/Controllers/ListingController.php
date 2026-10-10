<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\Inquiry;
use App\Models\Lease;
use App\Models\Listing;
use App\Models\ListingPhoto;
use App\Models\Unit;
use App\Support\Audit;
use App\Support\Datev\ZipWriter;
use App\Support\Listings\Listings;
use App\Support\Listings\PhotoStore;
use App\Support\Listings\PortalText;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

/** Inserate verwalten: anlegen, Fotos, online stellen, Texte für die Portale (Paket 11). */
class ListingController extends Controller
{
    use ScopesOwners;

    public function index()
    {
        $listings = Listing::visibleTo($this->me())->with(['unit.property', 'owner', 'photos'])
            ->orderByRaw("field(status, 'online', 'entwurf', 'offline')")->orderByDesc('updated_at')->get();

        $today = date('Y-m-d');
        $units = Unit::visibleTo($this->me())->with('property')->orderBy('property_id')->orderBy('label')->get()
            ->map(function ($u) use ($today) {
                $lease = Lease::query()->where('unit_id', $u->id)->where('start_date', '<=', $today)
                    ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $today))->orderByDesc('start_date')->first();

                return ['unit' => $u, 'rented' => $lease !== null, 'ends' => $lease?->end_date?->format('d.m.Y')];
            });

        return view('listings.index', [
            'listings' => $listings,
            'units' => $units,
            'newInquiries' => Inquiry::newCountFor($this->me()),
        ]);
    }

    public function create(Request $request)
    {
        $unit = Unit::visibleTo($this->me())->with('property')->findOrFail((int) $request->query('einheit'));
        $kind = Listings::kindForUsage($unit->usage_type);
        $listing = new Listing([
            'unit_id' => $unit->id,
            'kind' => $kind,
            'area_sqm' => $unit->area_sqm,
            'rooms' => $unit->rooms,
            'floor' => $unit->floor,
            'equipment_text' => $unit->equipment,
            'title' => self::suggestTitle($unit, $kind),
            'plus_vat' => $unit->usage_type === 'gewerbe_option',
            'features' => [],
        ]);
        // Letzte Miete der Einheit als Vorschlag
        $last = Lease::query()->where('unit_id', $unit->id)->orderByDesc('start_date')->first();
        if ($last && $kind !== 'montage') {
            $r = $last->currentRent();
            if ($r) {
                $listing->rent_cold = $r->rent_cold;
                $listing->service_charge = $r->prepay_operating;
                $listing->heating_charge = $r->prepay_heating;
            }
        }

        return view('listings.form', ['listing' => $listing, 'unit' => $unit]);
    }

    public function store(Request $request)
    {
        $unit = Unit::visibleTo($this->me())->with('property')->findOrFail((int) $request->input('unit_id'));
        $data = $this->validated($request);
        $listing = DB::transaction(function () use ($data, $unit) {
            $l = Listing::create($data + [
                'unit_id' => $unit->id,
                'owner_id' => $unit->property->owner_id,
                'status' => 'entwurf',
                'slug' => 'neu-'.bin2hex(random_bytes(6)),
                'created_by' => $this->me()->id,
            ]);
            $l->update(['slug' => self::slug($l)]);
            Audit::log('listing.created', $l, 'Inserat angelegt: '.$l->title);

            return $l;
        });

        return redirect()->to(route('listings.edit', $listing).'#fotos')->with('status', 'Inserat als Entwurf gespeichert. Jetzt Fotos hinzufügen und dann online stellen.');
    }

    public function edit(Listing $listing)
    {
        $this->assertOwnerAccess((int) $listing->owner_id);
        $listing->load(['unit.property', 'photos']);

        return view('listings.form', [
            'listing' => $listing,
            'unit' => $listing->unit,
            'missing' => Listings::missing($listing),
            'gd' => PhotoStore::available(),
        ]);
    }

    public function update(Request $request, Listing $listing)
    {
        $this->assertOwnerAccess((int) $listing->owner_id);
        $data = $this->validated($request);
        $titleChanged = $data['title'] !== $listing->title;
        $listing->update($data);
        if ($titleChanged && ! $listing->isOnline()) {
            $listing->update(['slug' => self::slug($listing)]);
        }
        Audit::log('listing.updated', $listing, 'Inserat geändert: '.$listing->title);

        return redirect()->route('listings.edit', $listing)->with('status', 'Gespeichert.');
    }

    public function status(Request $request, Listing $listing)
    {
        $this->assertOwnerAccess((int) $listing->owner_id);
        $status = $request->validate(['status' => ['required', Rule::in(['online', 'offline'])]])['status'];
        $listing->update(['status' => $status, 'published_at' => $status === 'online' ? ($listing->published_at ?? now()) : $listing->published_at]);
        Audit::log('listing.'.$status, $listing, 'Inserat '.($status === 'online' ? 'online gestellt' : 'offline genommen').': '.$listing->title);

        return redirect()->route('listings.edit', $listing)->with('status', $status === 'online' ? 'Das Inserat ist jetzt auf der Webseite zu sehen.' : 'Das Inserat ist nicht mehr auf der Webseite zu sehen.');
    }

    public function destroy(Listing $listing)
    {
        $this->assertOwnerAccess((int) $listing->owner_id);
        DB::transaction(function () use ($listing) {
            Audit::log('listing.deleted', $listing, 'Inserat gelöscht: '.$listing->title);
            foreach ($listing->photos as $p) {
                PhotoStore::delete($p);
            }
            $listing->delete();
        });

        return redirect()->route('listings.index')->with('status', 'Inserat gelöscht. Anfragen dazu bleiben erhalten.');
    }

    /* ---------------- Fotos ---------------- */

    public function photoStore(Request $request, Listing $listing)
    {
        $this->assertOwnerAccess((int) $listing->owner_id);
        $request->validate([
            'fotos' => ['required', 'array', 'max:20'],
            'fotos.*' => ['file', 'max:20480'],
        ], ['fotos.required' => 'Bitte Fotos auswählen.', 'fotos.max' => 'Höchstens 20 Fotos auf einmal.', 'fotos.*.max' => 'Ein Foto darf höchstens 20 MB groß sein.', 'fotos.*.file' => 'Ein Foto konnte nicht hochgeladen werden.']);

        if ($listing->photos()->count() + count($request->file('fotos')) > 40) {
            return back()->withErrors(['fotos' => 'Ein Inserat kann höchstens 40 Fotos haben.']);
        }
        $ok = 0;
        $errors = [];
        foreach ($request->file('fotos') as $file) {
            try {
                PhotoStore::store($file, $listing);
                $ok++;
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }
        $r = redirect()->to(route('listings.edit', $listing).'#fotos')->with('status', $ok === 1 ? 'Ein Foto hinzugefügt.' : $ok.' Fotos hinzugefügt.');

        return $errors === [] ? $r : $r->withErrors(['fotos' => implode(' ', $errors)]);
    }

    public function photoUpdate(Request $request, Listing $listing, ListingPhoto $photo)
    {
        $this->photoAccess($listing, $photo);
        $data = $request->validate(['caption' => ['nullable', 'string', 'max:190'], 'move' => ['nullable', Rule::in(['first', 'up', 'down'])]]);
        if (array_key_exists('caption', $data) && ! isset($data['move'])) {
            $photo->update(['caption' => trim((string) $data['caption']) ?: null]);
        }
        if (isset($data['move'])) {
            $ids = $listing->photos()->pluck('id')->all();
            $i = array_search($photo->id, $ids, true);
            if ($i !== false) {
                array_splice($ids, $i, 1);
                $to = match ($data['move']) { 'first' => 0, 'up' => max(0, $i - 1), default => min(count($ids), $i + 1) };
                array_splice($ids, $to, 0, [$photo->id]);
                foreach ($ids as $n => $id) {
                    ListingPhoto::query()->whereKey($id)->update(['sort' => $n + 1]);
                }
            }
        }

        return redirect()->to(route('listings.edit', $listing).'#fotos');
    }

    public function photoDestroy(Listing $listing, ListingPhoto $photo)
    {
        $this->photoAccess($listing, $photo);
        PhotoStore::delete($photo);

        return redirect()->to(route('listings.edit', $listing).'#fotos')->with('status', 'Foto gelöscht.');
    }

    /** Vorschaubild im Verwaltungsbereich (auch für Entwürfe). */
    public function photoShow(Listing $listing, ListingPhoto $photo, string $size)
    {
        $this->photoAccess($listing, $photo);
        $path = PhotoStore::path($photo, $size);
        abort_if($path === null, 404);

        return response()->file($path, ['Content-Type' => 'image/jpeg']);
    }

    /* ---------------- Portale ---------------- */

    public function portal(Listing $listing)
    {
        $this->assertOwnerAccess((int) $listing->owner_id);
        $listing->load(['unit.property', 'photos']);

        return view('listings.portal', [
            'listing' => $listing,
            'facts' => PortalText::facts($listing),
            'is24' => PortalText::immoscout($listing),
            'ka' => PortalText::kleinanzeigen($listing),
            'missing' => Listings::missing($listing),
        ]);
    }

    public function photosZip(Listing $listing)
    {
        $this->assertOwnerAccess((int) $listing->owner_id);
        $photos = $listing->photos()->get();
        abort_if($photos->isEmpty(), 404);
        $tmp = tempnam(sys_get_temp_dir(), 'fotos');
        $zip = new ZipWriter($tmp);
        foreach ($photos as $i => $p) {
            $path = PhotoStore::path($p, 'l');
            if ($path) {
                $zip->addFile(str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT).'.jpg', (string) file_get_contents($path));
            }
        }
        $zip->close();

        return response()->download($tmp, 'fotos-'.Str::slug($listing->title).'.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }

    /* ---------------- Hilfen ---------------- */

    private function photoAccess(Listing $listing, ListingPhoto $photo): void
    {
        $this->assertOwnerAccess((int) $listing->owner_id);
        abort_unless((int) $photo->listing_id === (int) $listing->id, 404);
    }

    private function validated(Request $request): array
    {
        $kind = (string) $request->input('kind');
        $money = ['rent_cold', 'service_charge', 'heating_charge', 'deposit', 'price_night', 'price_week', 'price_month'];
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(Listings::KINDS))],
            'title' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:3999'],
            'equipment_text' => ['nullable', 'string', 'max:3999'],
            'location_text' => ['nullable', 'string', 'max:3999'],
            'other_text' => ['nullable', 'string', 'max:3999'],
            'features' => ['nullable', 'array'],
            'features.*' => [Rule::in(array_keys(Listings::FEATURES))],
            'show_address' => ['nullable', 'boolean'],
            'available_from' => ['nullable', 'date_format:Y-m-d'],
            'area_sqm' => ['nullable', 'string', 'max:12'],
            'rooms' => ['nullable', 'string', 'max:6'],
            'floor' => ['nullable', 'string', 'max:30'],
            'year_built' => ['nullable', 'integer', 'between:1500,2100'],
            'plus_vat' => ['nullable', 'boolean'],
            'price_per_person' => ['nullable', 'boolean'],
            'beds' => ['nullable', 'integer', 'between:1,50'],
            'min_nights' => ['nullable', 'integer', 'between:1,365'],
            'energy_type' => ['nullable', Rule::in(array_keys(Listings::ENERGY_TYPES))],
            'energy_value' => ['nullable', 'string', 'max:10'],
            'energy_source' => ['nullable', 'string', 'max:60'],
            'energy_year' => ['nullable', 'integer', 'between:1500,2100'],
            'energy_class' => ['nullable', Rule::in(Listings::ENERGY_CLASSES)],
        ] + array_fill_keys($money, ['nullable', 'string', 'max:20']), [
            'title.required' => 'Bitte einen Titel angeben, z. B. „Helle 3-Zimmer-Wohnung mit Balkon“.',
            'title.max' => 'Der Titel darf höchstens 100 Zeichen lang sein (Grenze bei ImmoScout24).',
            '*.between' => 'Bitte eine gültige Zahl eingeben.',
            '*.integer' => 'Bitte eine ganze Zahl eingeben.',
        ]);

        $errors = [];
        foreach (array_merge($money, ['area_sqm', 'rooms', 'energy_value']) as $f) {
            $v = trim((string) ($data[$f] ?? ''));
            if ($v === '') {
                $data[$f] = null;

                continue;
            }
            $parsed = Money::parse($v);
            if ($parsed === null || (float) $parsed < 0) {
                $errors[$f] = 'Bitte eine gültige Zahl eingeben.';
            }
            $data[$f] = $parsed;
        }
        if ($errors !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }
        if ($data['rooms'] !== null) {
            $data['rooms'] = number_format(round((float) $data['rooms'] * 2) / 2, 1, '.', '');
        }

        $data['features'] = array_values(array_intersect(array_keys(Listings::featuresFor($kind)), $data['features'] ?? []));
        foreach (['show_address', 'plus_vat', 'price_per_person'] as $b) {
            $data[$b] = (bool) ($data[$b] ?? false);
        }
        foreach (['description', 'equipment_text', 'location_text', 'other_text', 'floor', 'energy_source'] as $t) {
            $data[$t] = isset($data[$t]) && trim((string) $data[$t]) !== '' ? trim((string) $data[$t]) : null;
        }
        if ($kind === 'montage') {
            $data['rent_cold'] = $data['service_charge'] = $data['heating_charge'] = $data['deposit'] = null;
            $data['plus_vat'] = false;
        } else {
            $data['price_night'] = $data['price_week'] = $data['price_month'] = null;
            $data['price_per_person'] = false;
            $data['beds'] = $data['min_nights'] = null;
        }
        if (($data['energy_type'] ?? null) === 'keiner' || ($data['energy_type'] ?? null) === null) {
            $data['energy_value'] = null;
            $data['energy_source'] = null;
            $data['energy_year'] = null;
            $data['energy_class'] = null;
        }

        return $data;
    }

    private static function slug(Listing $l): string
    {
        $base = Str::slug($l->kindLabel().' '.$l->title.' '.($l->unit?->property?->city ?? ''));
        $base = trim(mb_substr($base, 0, 100), '-') ?: 'angebot';

        return $base.'-'.$l->id;
    }

    private static function suggestTitle(Unit $u, string $kind): string
    {
        $city = $u->property?->city ? ' in '.$u->property->city : '';
        $rooms = $u->rooms ? Listings::num($u->rooms, 1).'-Zimmer-' : '';

        return match ($kind) {
            'montage' => 'Möblierte Monteurwohnung'.$city.($u->rooms ? ', '.Listings::num($u->rooms, 1).' Zimmer' : ''),
            'gewerbe' => 'Gewerbefläche'.($u->area_sqm ? ' '.Listings::num($u->area_sqm, 0).' m²' : '').$city,
            default => $rooms.'Wohnung'.$city.($u->area_sqm ? ', '.Listings::num($u->area_sqm, 0).' m²' : ''),
        };
    }
}
