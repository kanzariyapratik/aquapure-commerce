<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthenticationSecurity
{
    public function handle(Request $request, Closure $next)
    {
        if (app()->isProduction()) {
            config(['session.secure' => true]);
        }

        $response = $next($request);
        if ($request->user() || $request->routeIs('login', 'register', 'password.*', 'two-factor.*')) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Referrer-Policy', 'same-origin');
        }
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
