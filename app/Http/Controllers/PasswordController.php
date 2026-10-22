<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PasswordController
{
    public function forgot()
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function sendResetLink(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($data);

        // Keep the response identical for unknown accounts and throttled requests.
        return back()->with('success', 'If an account exists for this email, a password reset link has been sent. Please check your inbox and spam folder.');
    }

    public function resetPage(Request $request, string $token)
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => PasswordPolicy::rules(),
        ]);

        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors([
                'email' => 'This password reset link is invalid or has expired. Please request a new link.',
            ]);
        }

        return redirect()->route('login')->with('success', 'Your password has been reset. Sign in with your new password.');
    }

    public function edit()
    {
        return Inertia::render('Auth/ChangePassword', ['admin' => false]);
    }

    public function adminEdit()
    {
        return Inertia::render('Auth/ChangePassword', ['admin' => true]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => [...PasswordPolicy::rules(), 'different:current_password'],
        ]);

        $user = $request->user();
        $user->forceFill([
            'password' => $data['password'],
            'remember_token' => Str::random(60),
        ])->save();

        Password::deleteToken($user);
        $request->session()->regenerate();

        return back()->with('success', 'Your password has been changed.');
    }
}
