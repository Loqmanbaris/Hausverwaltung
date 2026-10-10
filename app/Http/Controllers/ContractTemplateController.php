<?php

namespace App\Http\Controllers;

use App\Models\ContractTemplate;
use App\Support\Audit;
use App\Support\Contracts\ContractData;
use App\Support\Contracts\ContractRenderer;
use App\Support\Contracts\Vorlagen;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Vertragsvorlagen anpassen (nur Administratoren). */
class ContractTemplateController extends Controller
{
    public function index()
    {
        $kinds = [];
        foreach (Vorlagen::KINDS as $k => $label) {
            $kinds[$k] = ['label' => $label, 'custom' => ContractTemplate::isCustomized($k), 'count' => count(ContractTemplate::sectionsFor($k))];
        }

        return view('contract-templates.index', compact('kinds'));
    }

    public function edit(string $kind)
    {
        abort_unless(isset(Vorlagen::KINDS[$kind]), 404);
        $sections = ContractTemplate::sectionsFor($kind);
        // Vorschau mit Platzhalter-Namen
        $sample = [];
        foreach (array_keys(ContractData::PLACEHOLDERS) as $k) {
            $sample[$k] = '«'.$k.'»';
        }

        return view('contract-templates.edit', [
            'kind' => $kind,
            'label' => Vorlagen::KINDS[$kind],
            'sections' => $sections,
            'custom' => ContractTemplate::isCustomized($kind),
            'placeholders' => ContractData::PLACEHOLDERS,
            'preview' => ContractRenderer::render($sections, $sample),
        ]);
    }

    public function update(Request $request, string $kind)
    {
        abort_unless(isset(Vorlagen::KINDS[$kind]), 404);
        $data = $request->validate([
            's' => ['required', 'array', 'min:1', 'max:60'],
            's.*.key' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/'],
            's.*.title' => ['required', 'string', 'max:120'],
            's.*.text' => ['nullable', 'string', 'max:8000'],
            's.*.enabled' => ['nullable', 'boolean'],
            'add' => ['nullable', Rule::in(['1'])],
        ], ['s.*.title.required' => 'Jeder Paragraf braucht eine Überschrift.']);

        $sections = [];
        $keys = [];
        foreach ($data['s'] as $s) {
            $key = $s['key'];
            if (isset($keys[$key])) {
                $key .= '_'.substr(bin2hex(random_bytes(3)), 0, 6);
            }
            $keys[$key] = true;
            $sections[] = ['key' => $key, 'title' => trim($s['title']), 'text' => str_replace("\r", '', (string) ($s['text'] ?? '')), 'enabled' => (bool) ($s['enabled'] ?? false)];
        }
        if (! empty($data['add'])) {
            $sections[] = ['key' => 'eigen_'.bin2hex(random_bytes(3)), 'title' => 'Neuer Paragraf', 'text' => '(#) ', 'enabled' => true];
        }

        ContractTemplate::query()->updateOrCreate(['kind' => $kind], ['sections' => $sections, 'updated_by' => auth()->id()]);
        Audit::log('contract.template', 'Vertragsvorlage geändert: '.Vorlagen::KINDS[$kind]);

        return redirect()->to(route('contract-templates.edit', $kind).(! empty($data['add']) ? '#p'.(count($sections) - 1) : ''))
            ->with('status', ! empty($data['add']) ? 'Neuer Paragraf am Ende angelegt.' : 'Vorlage gespeichert. Sie gilt für alle neu erstellten Verträge.');
    }

    public function reset(string $kind)
    {
        abort_unless(isset(Vorlagen::KINDS[$kind]), 404);
        ContractTemplate::query()->where('kind', $kind)->delete();
        Audit::log('contract.template', 'Vertragsvorlage auf Muster zurückgesetzt: '.Vorlagen::KINDS[$kind]);

        return redirect()->route('contract-templates.edit', $kind)->with('status', 'Vorlage auf das Muster zurückgesetzt.');
    }
}
