<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\Owner;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\TenantContact;
use App\Models\Unit;
use Illuminate\Http\Request;

/**
 * Eine Suche über Eigentümer, Objekte, Einheiten, Mieter und Ansprechpartner.
 * Mitarbeiter finden nur Datensätze der für sie freigegebenen Eigentümer.
 */
class SearchController extends Controller
{
    use ScopesOwners;

    private const LIMIT = 15;

    public function index(Request $request)
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $terms = array_values(array_filter(preg_split('/\s+/u', $q) ?: [], fn ($t) => $t !== ''));
        if (preg_match('/^[+0-9][0-9 \-\/()]{2,}$/', $q)) {
            // Telefonnummer mit Leerzeichen („0421 123 456“) als ein Begriff behandeln
            $terms = [$q];
        }
        $tooShort = $q !== '' && mb_strlen($q) < 2;
        $results = null;

        if ($q !== '' && ! $tooShort) {
            $user = $this->me();

            $owners = $this->matching(
                Owner::visibleTo($user),
                $terms,
                ['owners.name', 'owners.street', 'owners.zip', 'owners.city', 'owners.email', 'owners.vat_id', 'owners.creditor_id'],
                ['owners.phone']
            )->orderBy('owners.name');

            $properties = $this->matching(
                Property::visibleTo($user)->with('owner'),
                $terms,
                ['properties.name', 'properties.street', 'properties.zip', 'properties.city']
            )->orderBy('properties.name');

            $units = Unit::visibleTo($user)->with('property.owner');
            foreach ($terms as $term) {
                $like = $this->like($term);
                $units->where(function ($w) use ($like) {
                    $w->where('units.label', 'like', $like)
                        ->orWhere('units.floor', 'like', $like)
                        ->orWhere('units.equipment', 'like', $like)
                        ->orWhereIn(
                            'units.property_id',
                            Property::query()->select('properties.id')->where(function ($p) use ($like) {
                                $p->where('properties.name', 'like', $like)
                                    ->orWhere('properties.street', 'like', $like)
                                    ->orWhere('properties.city', 'like', $like);
                            })
                        );
                });
            }
            $units->orderBy('units.label');

            $tenants = $this->matching(
                Tenant::visibleTo($user)->with('owner'),
                $terms,
                ['tenants.display_name', 'tenants.company_name', 'tenants.first_name', 'tenants.last_name',
                    'tenants.email', 'tenants.vat_id', 'tenants.street', 'tenants.zip', 'tenants.city'],
                ['tenants.phone']
            )->orderBy('tenants.display_name');

            $contacts = $this->matching(
                TenantContact::query()
                    ->whereIn('tenant_contacts.tenant_id', Tenant::visibleTo($user)->select('tenants.id'))
                    ->with('tenant'),
                $terms,
                ['tenant_contacts.name', 'tenant_contacts.role', 'tenant_contacts.email'],
                ['tenant_contacts.phone']
            )->orderBy('tenant_contacts.name');

            $results = [
                'owners' => $this->limited($owners),
                'properties' => $this->limited($properties),
                'units' => $this->limited($units),
                'tenants' => $this->limited($tenants),
                'contacts' => $this->limited($contacts),
            ];
        }

        $total = $results === null ? 0 : collect($results)->sum(fn ($r) => $r['items']->count());

        return view('search.index', compact('q', 'tooShort', 'results', 'total'));
    }

    private function like(string $term): string
    {
        return '%'.addcslashes($term, '%_\\').'%';
    }

    /**
     * Jeder Suchbegriff muss in mindestens einer der Spalten vorkommen.
     * Telefonnummern werden ohne Leerzeichen, Bindestriche und Klammern verglichen.
     * Die Spaltennamen kommen nur aus dem Programmcode, nie aus der Eingabe.
     */
    private function matching($query, array $terms, array $columns, array $phoneColumns = [])
    {
        foreach ($terms as $term) {
            $like = $this->like($term);
            $digits = str_replace([' ', '-', '/', '(', ')'], '', $term);
            $isNumber = (bool) preg_match('/^\+?[0-9]{3,}$/', $digits);

            $query->where(function ($w) use ($columns, $phoneColumns, $like, $digits, $isNumber) {
                foreach ($columns as $column) {
                    $w->orWhere($column, 'like', $like);
                }

                if ($isNumber) {
                    foreach ($phoneColumns as $column) {
                        $w->orWhereRaw(
                            "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$column}, ' ', ''), '-', ''), '/', ''), '(', ''), ')', '') like ?",
                            ['%'.$digits.'%']
                        );
                    }
                }
            });
        }

        return $query;
    }

    /** @return array{items: \Illuminate\Support\Collection, more: bool} */
    private function limited($query): array
    {
        $items = $query->limit(self::LIMIT + 1)->get();

        return [
            'items' => $items->take(self::LIMIT),
            'more' => $items->count() > self::LIMIT,
        ];
    }
}
