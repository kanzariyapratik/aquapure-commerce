<?php

namespace App\Http\Middleware;

use App\Services\TwoFactorAuthentication;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureTwoFactorVerified
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user?->hasTwoFactorAuthentication() && ! $request->routeIs('logout')) {
            $twoFactor = app(TwoFactorAuthentication::class);
            if (! hash_equals($twoFactor->fingerprint($user), $request->session()->get('two_factor.verified', ''))) {
                Auth::logoutCurrentDevice();
                $twoFactor->startChallenge($request, $user);

                return redirect()->route('two-factor.challenge');
            }
        }

        return $next($request);
    }
}
