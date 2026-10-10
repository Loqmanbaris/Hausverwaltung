<?php

namespace App\Http\Controllers;

use App\Models\Inquiry;
use App\Models\Listing;
use App\Models\ListingPhoto;
use App\Support\Listings\Listings;
use App\Support\Listings\Occupancy;
use App\Support\Listings\PhotoStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Throwable;

/** Öffentliche Angebote, Fotos und Kontaktformular (Paket 11). */
class PublicListingController extends Controller
{
    public function wohnen()
    {
        return $this->index('wohnen');
    }

    public function montage()
    {
        return $this->index('montage');
    }

    public function gewerbe()
    {
        return $this->index('gewerbe');
    }

    private function index(string $kind)
    {
        abort_unless(isset(Listings::PAGES[$kind]), 404);
        $listings = Listing::online()->where('kind', $kind)->with(['unit.property', 'photos'])
            ->orderBy('available_from')->orderByDesc('published_at')->get();
        $free = [];
        if ($kind === 'montage') {
            foreach ($listings as $l) {
                $free[$l->id] = Occupancy::nextFree($l->unit_id);
            }
        }

        return view('public.listings', ['kind' => $kind, 'heading' => Listings::PAGES[$kind][1], 'listings' => $listings, 'free' => $free]);
    }

    public function show(string $slug)
    {
        $listing = Listing::online()->where('slug', $slug)->with(['unit.property', 'photos'])->first();
        if ($listing === null) {
            // Nicht mehr online: freundliche Seite statt Fehler
            return response()->view('public.listing-gone', [], 404);
        }

        return view('public.listing', [
            'listing' => $listing,
            'calendar' => $listing->kind === 'montage' ? Occupancy::calendar($listing->unit_id, date('Y-m'), 3) : [],
            'formToken' => Crypt::encryptString((string) time()),
        ]);
    }

    public function contact(Request $request)
    {
        return view('public.contact', ['formToken' => Crypt::encryptString((string) time()), 'listing' => null, 'topic' => $request->query('thema') === 'schaden' ? 'schaden' : null]);
    }

    public function send(Request $request)
    {
        // Spamschutz: verstecktes Feld, Mindestzeit, Begrenzung je IP
        if (trim((string) $request->input('website')) !== '') {
            return redirect()->route('public.thanks');
        }
        $key = 'inquiry:'.sha1((string) $request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withInput()->withErrors(['message' => 'Es wurden gerade sehr viele Anfragen gesendet. Bitte versuchen Sie es in einer Stunde noch einmal oder schreiben Sie uns eine E-Mail.']);
        }
        try {
            $sent = (int) Crypt::decryptString((string) $request->input('t'));
        } catch (Throwable) {
            $sent = 0;
        }
        if ($sent === 0 || time() - $sent < 3 || time() - $sent > 86400 * 2) {
            return back()->withInput()->withErrors(['message' => 'Bitte das Formular noch einmal absenden.']);
        }

        $data = $request->validate([
            'listing' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:60'],
            'persons' => ['nullable', 'integer', 'between:1,50'],
            'date_from' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after:date_from'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'consent' => ['accepted'],
            'topic' => ['nullable', 'in:schaden'],
            'wohnung' => ['nullable', 'required_if:topic,schaden', 'string', 'max:190'],
        ], [
            'wohnung.required_if' => 'Bitte Adresse und Wohnung angeben, in der der Schaden ist.',
            'name.required' => 'Bitte Ihren Namen angeben.',
            'email.required' => 'Bitte Ihre E-Mail-Adresse angeben.',
            'email.email' => 'Die E-Mail-Adresse ist ungültig.',
            'message.required' => 'Bitte eine Nachricht schreiben.',
            'message.min' => 'Bitte etwas ausführlicher schreiben.',
            'consent.accepted' => 'Bitte der Verarbeitung Ihrer Angaben zur Bearbeitung der Anfrage zustimmen.',
            'date_from.after_or_equal' => 'Der Anreisetag darf nicht in der Vergangenheit liegen.',
            'date_to.after' => 'Der Abreisetag muss nach dem Anreisetag liegen.',
            'persons.between' => 'Bitte eine Personenzahl von 1 bis 50 angeben.',
        ]);
        RateLimiter::hit($key, 3600);

        $listing = ! empty($data['listing']) ? Listing::online()->find((int) $data['listing']) : null;
        $topic = $data['topic'] ?? null;
        $message = trim($data['message']);
        if ($topic === 'schaden') {
            $message = 'Schadensmeldung – Wohnung: '.trim((string) $data['wohnung'])."\n\n".$message;
        }
        $inquiry = Inquiry::create([
            'listing_id' => $listing?->id,
            'owner_id' => $listing?->owner_id ?? ($topic === 'schaden' ? $this->ownerForAddress((string) $data['wohnung']) : null),
            'topic' => $topic,
            'name' => trim($data['name']),
            'company' => trim((string) ($data['company'] ?? '')) ?: null,
            'email' => trim($data['email']),
            'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
            'persons' => $data['persons'] ?? null,
            'date_from' => $data['date_from'] ?? null,
            'date_to' => $data['date_to'] ?? null,
            'message' => $message,
            'status' => 'neu',
        ]);
        \App\Support\Mail\Notify::newInquiry($inquiry);
        if ($topic === 'schaden' && class_exists(\App\Support\Technik\Auftraege::class)) {
            try {
                \App\Support\Technik\Auftraege::fromInquiry($inquiry);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('public.thanks', $topic ? ['thema' => $topic] : []);
    }

    public function thanks(Request $request)
    {
        return view('public.thanks', ['topic' => $request->query('thema') === 'schaden' ? 'schaden' : null]);
    }

    /** Eigentümer zu einer Adresse raten (Straße des Objekts im Text), damit Mitarbeiter die Meldung sehen. */
    private function ownerForAddress(string $text): ?int
    {
        $t = mb_strtolower($text);
        foreach (\App\Models\Property::query()->whereNotNull('street')->get(['owner_id', 'street']) as $p) {
            $street = mb_strtolower(trim((string) $p->street));
            if ($street !== '' && str_contains($t, $street)) {
                return (int) $p->owner_id;
            }
        }

        return null;
    }

    /** Foto eines Inserats, das online ist. */
    public function photo(ListingPhoto $photo, string $size)
    {
        $listing = $photo->listing;
        abort_unless($listing && $listing->isOnline(), 404);
        $path = PhotoStore::path($photo, $size);
        abort_if($path === null, 404);

        return response()->file($path, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}
