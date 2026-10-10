<?php

namespace App\Http\Controllers;

use App\Models\CostType;
use App\Support\Audit;
use App\Support\Kostenarten;
use App\Support\Stammdaten;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Kostenarten verwalten (nur Administrator). Es wird nichts gelöscht, nur stillgelegt. */
class CostTypeController extends Controller
{
    public function index()
    {
        $types = CostType::query()->orderBy('sort')->orderBy('id')->get();
        $usage = DB::table('receipts')
            ->whereNull('deleted_at')
            ->whereNotNull('cost_category')
            ->selectRaw('cost_category, count(*) as c')
            ->groupBy('cost_category')
            ->pluck('c', 'cost_category')
            ->all();

        return view('costtypes.index', [
            'groups' => $types->groupBy('group_label'),
            'usage' => $usage,
        ]);
    }

    public function create()
    {
        return view('costtypes.form', [
            'type' => new CostType(['active' => true, 'allocatable' => false]),
            'groupNames' => Kostenarten::groupNames(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $type = DB::transaction(function () use ($data) {
            $type = CostType::create($data + [
                'key' => $this->newKey($data['label']),
                'sort' => ((int) CostType::query()->max('sort')) + 10,
                'active' => true,
                'is_system' => false,
            ]);
            Audit::log('cost_type.created', $type, 'Kostenart angelegt: '.$type->label, null, $data);

            return $type;
        });
        Kostenarten::forgetCache();

        return redirect()->route('cost-types.index')->with('status', 'Kostenart „'.$type->label.'“ wurde angelegt.');
    }

    public function edit(CostType $costType)
    {
        return view('costtypes.form', [
            'type' => $costType,
            'groupNames' => Kostenarten::groupNames(),
        ]);
    }

    public function update(Request $request, CostType $costType)
    {
        $data = $this->validated($request) + ['active' => $request->boolean('active')];
        $data['sort'] = $request->filled('sort') ? max(0, min(65000, (int) $request->input('sort'))) : $costType->sort;

        DB::transaction(function () use ($costType, $data) {
            $keys = array_keys($data);
            $before = $costType->only($keys);
            $costType->update($data);
            [$old, $new] = Audit::diff(
                array_map(fn ($v) => is_bool($v) ? (int) $v : $v, $before),
                array_map(fn ($v) => is_bool($v) ? (int) $v : $v, $costType->only($keys))
            );

            if ($new !== null) {
                Audit::log('cost_type.updated', $costType, 'Kostenart geändert: '.$costType->label, $old, $new);
            }
        });
        Kostenarten::forgetCache();

        return redirect()->route('cost-types.index')->with('status', 'Änderungen wurden gespeichert.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'group_label' => ['required', 'string', 'max:80'],
        ], Stammdaten::messages(), ['label' => 'Bezeichnung', 'group_label' => 'Gruppe']);

        $data['label'] = trim((string) preg_replace('/\s+/u', ' ', $data['label']));
        $data['group_label'] = trim((string) preg_replace('/\s+/u', ' ', $data['group_label']));
        $data['allocatable'] = $request->boolean('allocatable');

        return $data;
    }

    /** Fester Schlüssel aus der Bezeichnung, eindeutig und ohne Sonderzeichen. */
    private function newKey(string $label): string
    {
        $base = 'k_'.mb_substr(trim((string) preg_replace('/[^a-z0-9]+/', '_', Str::lower(Str::ascii($label))), '_'), 0, 30);
        $key = $base;
        $n = 1;
        while (CostType::query()->where('key', $key)->exists()) {
            $n++;
            $key = mb_substr($base, 0, 36).'_'.$n;
        }

        return $key;
    }
}
