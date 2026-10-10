<?php

namespace App\Http\Controllers;

use App\Support\Dashboard\Layout;
use Illuminate\Http\Request;

/** Übersicht anpassen: Bereiche, Reihenfolge, Schnellzugriff, Merkzettel (Paket 19). */
class DashboardLayoutController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();

        return view('dashboard-layout', [
            'blocks' => Layout::blocks($user),
            'quick' => Layout::quickChoices($user),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'order' => ['required', 'array', 'max:50'],
            'order.*' => ['string', 'max:30'],
            'on' => ['nullable', 'array', 'max:50'],
            'on.*' => ['string', 'max:30'],
            'quick' => ['nullable', 'array', 'max:50'],
            'quick.*' => ['string', 'max:30'],
            'color' => ['nullable', 'array', 'max:50'],
            'color.*' => ['string', 'max:30'],
        ]);
        Layout::save($request->user(), $data['order'], $data['on'] ?? [], $data['quick'] ?? [], $data['color'] ?? []);

        return redirect()->route('dashboard')->with('status', 'Deine Übersicht wurde gespeichert.');
    }

    public function reset(Request $request)
    {
        Layout::reset($request->user());

        return redirect()->route('dashboard.layout')->with('status', 'Die Übersicht ist wieder wie am Anfang.');
    }

    public function note(Request $request)
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:'.Layout::NOTE_MAX]], ['note.max' => 'Der Merkzettel darf höchstens '.Layout::NOTE_MAX.' Zeichen haben.']);
        Layout::saveNote($request->user(), $data['note'] ?? null);

        return redirect()->to(route('dashboard').'#merkzettel')->with('status', 'Merkzettel gespeichert.');
    }
}
