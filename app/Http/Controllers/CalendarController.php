<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\CalendarFeed;
use App\Support\Audit;
use App\Support\Calendar\Ics;
use App\Support\Calendar\Kalender;
use App\Support\Mail\MailSettings;
use DateTimeImmutable;
use Illuminate\Http\Request;

/** Kalender-Abo: Link verwalten und Feed ausliefern (Paket 33). */
class CalendarController extends Controller
{
    use ScopesOwners;

    public function index()
    {
        $feed = CalendarFeed::query()->where('user_id', $this->me()->id)->first();
        $url = $feed ? Kalender::url($feed) : null;

        return view('calendar.index', [
            'feed' => $feed, 'url' => $url, 'webcal' => $url ? preg_replace('#^https?://#', 'webcal://', $url) : null,
            'categories' => Kalender::CATEGORIES, 'selected' => $feed?->categories ?: array_keys(Kalender::CATEGORIES),
        ]);
    }

    public function create()
    {
        $existing = CalendarFeed::query()->where('user_id', $this->me()->id)->exists();
        Kalender::createFeed($this->me());
        Audit::log('calendar.created', $existing ? 'Kalender-Link erneuert' : 'Kalender-Abo eingerichtet');

        return redirect()->route('calendar.index')->with('status', $existing ? 'Ein neuer Link ist erzeugt. Der alte funktioniert nicht mehr. Trage den neuen in deinen Kalender ein.' : 'Das Kalender-Abo ist eingerichtet. Kopiere den Link in deinen Kalender.');
    }

    public function update(Request $request)
    {
        $data = $request->validate(['categories' => ['nullable', 'array'], 'categories.*' => ['string']]);
        $sel = array_values(array_intersect(array_keys(Kalender::CATEGORIES), $data['categories'] ?? []));
        $feed = CalendarFeed::query()->where('user_id', $this->me()->id)->firstOrFail();
        $feed->update(['categories' => count($sel) === count(Kalender::CATEGORIES) ? null : $sel]);

        return back()->with('status', 'Gespeichert. Dein Kalender übernimmt die Änderung beim nächsten Abgleich (oft nach ein bis mehreren Stunden).');
    }

    public function destroy()
    {
        CalendarFeed::query()->where('user_id', $this->me()->id)->delete();
        Audit::log('calendar.deleted', 'Kalender-Abo beendet');

        return redirect()->route('calendar.index')->with('status', 'Das Kalender-Abo ist beendet. Der Link funktioniert nicht mehr.');
    }

    /** Öffentlicher Feed. Zugang nur mit dem geheimen Link. */
    public function feed(string $token)
    {
        $feed = Kalender::byToken($token);
        abort_if($feed === null || $feed->user === null || ! $feed->user->is_active, 404);
        $feed->forceFill(['last_fetched_at' => now()])->save();
        $events = Kalender::events($feed->user, $feed->categories ?: null);
        $body = Ics::build($events, MailSettings::fromName().' – Termine', new DateTimeImmutable('now'));

        return response($body, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="termine.ics"',
            'Cache-Control' => 'private, max-age=900',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
