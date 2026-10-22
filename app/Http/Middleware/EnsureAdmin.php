<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class EnsureAdmin
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->role === 'admin', 403);
        if (! $request->user()->hasTwoFactorAuthentication() && ! $request->routeIs('admin.security')) {
            return redirect()->route('admin.security')->with('error', 'Set up two-factor authentication before accessing the admin panel.');
        }

        return $next($request);
    }
}
