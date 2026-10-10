<a href="{{ route('owners.index') }}" @class(['active' => request()->routeIs('owners.*')])>Eigentümer</a>
<a href="{{ route('properties.index') }}" @class(['active' => request()->routeIs('properties.*', 'units.*')])>Objekte</a>
<a href="{{ route('tenants.index') }}" @class(['active' => request()->routeIs('tenants.*')])>Mieter</a>
<a href="{{ route('rent.month') }}" @class(['active' => request()->routeIs('rent.*', 'leases.*', 'bank.*', 'dunning.*')])>Mieten</a>
<a href="{{ route('listings.index') }}" @class(['active' => request()->routeIs('listings.*', 'bookings.*', 'inquiries.*')])>Inserate</a>
<a href="{{ route('nk.index') }}" @class(['active' => request()->routeIs('nk.*')])>Nebenkosten</a>
<a href="{{ route('receipts.index') }}" @class(['active' => request()->routeIs('receipts.*')])>Belege</a>
