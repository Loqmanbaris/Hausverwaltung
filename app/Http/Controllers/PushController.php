<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Support\Push\Push;
use Illuminate\Http\Request;

/** Geräte für Mitteilungen an- und abmelden, Test-Mitteilung. */
class PushController extends Controller
{
    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:2000', 'starts_with:https://'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'in:aes128gcm,aesgcm'],
        ]);
        PushSubscription::query()->updateOrCreate(['endpoint_hash' => hash('sha256', $data['endpoint'])], [
            'user_id' => $request->user()->id,
            'endpoint' => $data['endpoint'],
            'public_key' => $data['keys']['p256dh'],
            'auth_token' => $data['keys']['auth'],
            'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
            'device' => mb_substr((string) $request->userAgent(), 0, 190),
        ]);

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request)
    {
        $endpoint = (string) $request->input('endpoint');
        PushSubscription::query()->where('user_id', $request->user()->id)->where('endpoint_hash', hash('sha256', $endpoint))->delete();

        return response()->json(['ok' => true]);
    }

    public function test(Request $request)
    {
        return response()->json(['sent' => Push::toUser($request->user(), [
            'title' => 'Test: Mitteilungen funktionieren ✓',
            'body' => 'Erinnerungen aus „Mein Tag“ kommen jetzt aufs Gerät.',
            'url' => route('tag.index'),
        ])]);
    }
}
