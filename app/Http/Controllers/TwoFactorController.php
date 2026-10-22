<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TwoFactorAuthentication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TwoFactorController
{
    public function __construct(private TwoFactorAuthentication $twoFactor) {}

    private function settingsPath(User $user): string
    {
        return $user->role === 'admin' ? '/admin/security' : '/account/security';
    }

    private function privatePage(Request $request): void
    {
        // Encrypted history requires Web Crypto, available over HTTPS or localhost.
        Inertia::encryptHistory($request->isSecure() || in_array($request->getHost(), ['localhost', '127.0.0.1']));
        Inertia::clearHistory();
    }

    private function pendingSetup(Request $request): ?array
    {
        $setup = $request->session()->get('two_factor.setup');
        if (($setup['id'] ?? null) !== $request->user()->id || ($setup['expires_at'] ?? 0) < now()->timestamp ||
            ! hash_equals($request->user()->password, $setup['password_hash'] ?? '')) {
            $request->session()->forget('two_factor.setup');

            return null;
        }

        return $setup;
    }

    public function settings(Request $request)
    {
        $user = $request->user();
        $setup = ! $user->hasTwoFactorAuthentication() ? $this->pendingSetup($request) : null;
        $secret = $setup ? decrypt($setup['secret']) : null;
        $codes = $request->session()->pull('two_factor.new_recovery_codes');
        $this->privatePage($request);

        return Inertia::render('Auth/Security', [
            'admin' => $user->role === 'admin',
            'enabled' => $user->hasTwoFactorAuthentication(),
            'required' => $user->role === 'admin',
            'setup' => $secret ? ['secret' => $secret, 'qrCode' => $this->twoFactor->qrCode($user, $secret)] : null,
            'recoveryCodes' => $codes ? decrypt($codes) : [],
            'recoveryCodesRemaining' => count($user->two_factor_recovery_codes ?? []),
        ]);
    }

    public function begin(Request $request)
    {
        $request->validate(['current_password' => ['required', 'current_password']]);
        abort_if($request->user()->hasTwoFactorAuthentication(), 409, 'Two-factor authentication is already enabled.');
        $request->session()->put('two_factor.setup', [
            'id' => $request->user()->id,
            'secret' => encrypt($this->twoFactor->secret()),
            'password_hash' => $request->user()->password,
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        return redirect($this->settingsPath($request->user()));
    }

    public function confirm(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string', 'regex:/\A[0-9]{6}\z/']]);
        $setup = $this->pendingSetup($request);
        if (! $setup) {
            throw ValidationException::withMessages(['code' => 'Setup has expired. Enter your password to start again.']);
        }
        $secret = decrypt($setup['secret']);
        $counter = $this->twoFactor->counter($secret, $data['code']);
        if ($counter === false) {
            throw ValidationException::withMessages(['code' => 'The code is invalid. Check your device time and try the latest code.']);
        }

        $codes = $this->twoFactor->recoveryCodes();
        $user = DB::transaction(function () use ($request, $setup, $secret, $counter, $codes) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_if($user->hasTwoFactorAuthentication(), 409, 'Two-factor authentication is already enabled.');
            abort_unless(hash_equals($user->password, $setup['password_hash']), 409, 'Your password changed. Start setup again.');
            $user->forceFill([
                'two_factor_secret' => $secret,
                'two_factor_recovery_codes' => $this->twoFactor->hashRecoveryCodes($codes),
                'two_factor_confirmed_at' => now(),
                'two_factor_last_counter' => $counter,
                'remember_token' => Str::random(60),
            ])->save();

            return $user;
        });
        $request->session()->forget('two_factor.setup');
        Auth::setUser($user);
        $request->session()->regenerate();
        $request->session()->put('two_factor.verified', $this->twoFactor->fingerprint($user));

        return redirect($this->settingsPath($user))
            ->with('two_factor.new_recovery_codes', encrypt($codes))
            ->with('success', 'Two-factor authentication is enabled. Save your recovery codes in a safe place.');
    }

    private function verifyForManagement(Request $request): User
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'code' => ['nullable', 'required_without:recovery_code', 'string', 'regex:/\A[0-9]{6}\z/'],
            'recovery_code' => ['nullable', 'required_without:code', 'string', 'max:100'],
        ]);
        $user = $this->twoFactor->consume($request->user(), $data['code'] ?? null, $data['recovery_code'] ?? null, $request->user()->password);
        if (! $user) {
            throw ValidationException::withMessages(['code' => 'The code is invalid or already used. Wait for a new authenticator code or use an unused recovery code.']);
        }

        return $user;
    }

    public function regenerateRecoveryCodes(Request $request)
    {
        $codes = $this->twoFactor->recoveryCodes();
        $user = DB::transaction(function () use ($request, $codes) {
            $user = $this->verifyForManagement($request);
            $user->two_factor_recovery_codes = $this->twoFactor->hashRecoveryCodes($codes);
            $user->save();

            return $user;
        });
        Auth::setUser($user);

        return redirect($this->settingsPath($user))
            ->with('two_factor.new_recovery_codes', encrypt($codes))
            ->with('success', 'New recovery codes generated. Your previous recovery codes no longer work.');
    }

    public function disable(Request $request)
    {
        abort_if($request->user()->role === 'admin', 403, 'Two-factor authentication is required for admin accounts.');
        $user = DB::transaction(function () use ($request) {
            $user = $this->verifyForManagement($request);
            $user->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'two_factor_last_counter' => null,
                'remember_token' => Str::random(60),
            ])->save();

            return $user;
        });
        $request->session()->forget('two_factor');
        Auth::setUser($user);
        $request->session()->regenerate();

        return redirect($this->settingsPath($user))->with('success', 'Two-factor authentication has been disabled.');
    }

    public function challenge(Request $request)
    {
        if (! $this->twoFactor->pendingUser($request)) {
            return redirect()->route('login')->with('error', 'Your sign-in session expired. Enter your password again.');
        }
        $this->privatePage($request);

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function verifyChallenge(Request $request)
    {
        $user = $this->twoFactor->pendingUser($request);
        if (! $user) {
            return redirect()->route('login')->with('error', 'Your sign-in session expired. Enter your password again.');
        }
        $key = 'two-factor:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => 'Too many verification attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }
        RateLimiter::hit($key, 300);
        $data = $request->validate([
            'code' => ['nullable', 'required_without:recovery_code', 'string', 'regex:/\A[0-9]{6}\z/'],
            'recovery_code' => ['nullable', 'required_without:code', 'string', 'max:100'],
        ]);
        $verified = $this->twoFactor->consume($user, $data['code'] ?? null, $data['recovery_code'] ?? null, $user->password);
        if (! $verified) {
            throw ValidationException::withMessages(['code' => 'The code is invalid or already used. Wait for a new authenticator code or use an unused recovery code.']);
        }
        Auth::login($verified);
        $request->session()->forget('two_factor');
        $request->session()->regenerate();
        $request->session()->put('two_factor.verified', $this->twoFactor->fingerprint($verified));
        $request->session()->put('password_hash_web', $verified->password);
        RateLimiter::clear($key);
        RateLimiter::clear('login:'.hash('sha256', Str::lower(trim($verified->email))));

        return redirect()->intended($verified->role === 'admin' ? '/admin' : '/');
    }

    public function cancelChallenge(Request $request)
    {
        $request->session()->forget('two_factor');
        $request->session()->regenerate();

        return redirect()->route('login');
    }
}
