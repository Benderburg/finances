<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class PrivateResponses
{
    public function handle(Request $r, Closure $next)
    {
        $response = $next($r);
        if ($r->is('api/*', 'auth/*', 'sanctum/*')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' https: http: data:; connect-src 'self'; font-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'");

        return $response;
    }
}
