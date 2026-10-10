<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');
        $response->headers->set('Strict-Transport-Security', 'max-age=15552000');
        // Öffentliche Fotos der Inserate dürfen zwischengespeichert werden
        if (! $response->headers->hasCacheControlDirective('public')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
