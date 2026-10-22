<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TwoFactorAuthentication;
use App\Support\PasswordPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AuthController
{
    public function loginPage()
    {
        return Inertia::render('Auth/Login');
    }

    public function registerPage()
    {
        return Inertia::render('Auth/Register');
    }

    public function login(Request $request, TwoFactorAuthentication $twoFactor)
    {
        $data = $request->validate(['email' => 'required|email|max:254', 'password' => 'required|string']);
        $key = 'login:'.hash('sha256', Str::lower(trim($data['email'])));
        $request->session()->forget('two_factor');
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many sign-in attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }
        RateLimiter::hit($key, 60);

        // Validate the password without creating an authenticated session before 2FA.
        if (! Auth::validate($data)) {
            throw ValidationException::withMessages(['email' => 'Invalid email or password.']);
        }
        $user = Auth::getLastAttempted();
        Auth::getProvider()->rehashPasswordIfRequired($user, $data);
        if ($user->hasTwoFactorAuthentication()) {
            $twoFactor->startChallenge($request, $user);

            return redirect()->route('two-factor.challenge');
        }

        RateLimiter::clear($key);
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('password_hash_web', $user->password);

        return $user->role === 'admin'
            ? redirect()->route('admin.security')
            : redirect()->intended('/');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:254|unique:users,email',
            'password' => PasswordPolicy::rules(),
        ]);
        $user = User::create($data + ['role' => 'customer']);
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('password_hash_web', $user->password);

        return redirect()->intended('/account/security')->with('success', 'Account created. You can enable two-factor authentication from Security & 2FA.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
