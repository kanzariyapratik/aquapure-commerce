<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorAuthentication
{
    public function secret(): string
    {
        return (new Google2FA)->generateSecretKey(32);
    }

    public function qrCode(User $user, string $secret): string
    {
        $uri = (new Google2FA)->getQRCodeUrl(config('app.name'), $user->email, $secret);
        $writer = new Writer(new ImageRenderer(new RendererStyle(240, 4), new SvgImageBackEnd));

        return 'data:image/svg+xml;base64,'.base64_encode($writer->writeString($uri));
    }

    public function counter(string $secret, string $code, ?int $lastCounter = null): int|false
    {
        if (! preg_match('/\A[0-9]{6}\z/', $code)) {
            return false;
        }

        return (new Google2FA)->verifyKeyNewer($secret, $code, $lastCounter ?? 0, 1, intdiv(now()->timestamp, 30));
    }

    public function recoveryCodes(): array
    {
        return array_map(fn () => implode('-', str_split(bin2hex(random_bytes(16)), 8)), range(1, 10));
    }

    public function hashRecoveryCodes(array $codes): array
    {
        return array_map(fn (string $code) => hash('sha256', $code), $codes);
    }

    // Row locks prevent concurrent requests from reusing the same OTP or recovery code.
    public function consume(User $user, ?string $code, ?string $recoveryCode, ?string $passwordHash = null): ?User
    {
        return DB::transaction(function () use ($user, $code, $recoveryCode, $passwordHash) {
            $locked = User::whereKey($user->id)->lockForUpdate()->first();
            if (! $locked?->hasTwoFactorAuthentication() ||
                ($passwordHash !== null && ! hash_equals($locked->password, $passwordHash)) ||
                ! hash_equals($this->fingerprint($user), $this->fingerprint($locked))) {
                return null;
            }

            if ($recoveryCode !== null && $recoveryCode !== '') {
                $hash = hash('sha256', strtolower(trim($recoveryCode)));
                $codes = $locked->two_factor_recovery_codes ?? [];
                $index = null;
                foreach ($codes as $candidate => $stored) {
                    if (hash_equals($stored, $hash)) {
                        $index = $candidate;
                        break;
                    }
                }
                if ($index === null) {
                    return null;
                }
                unset($codes[$index]);
                $locked->two_factor_recovery_codes = array_values($codes);
            } else {
                $counter = $this->counter($locked->two_factor_secret, $code ?? '', $locked->two_factor_last_counter);
                if ($counter === false) {
                    return null;
                }
                $locked->two_factor_last_counter = $counter;
            }

            $locked->save();

            return $locked;
        });
    }

    public function fingerprint(User $user): string
    {
        return hash('sha256', $user->id.'|'.$user->two_factor_secret);
    }

    public function startChallenge(Request $request, User $user): void
    {
        $request->session()->forget('two_factor');
        $request->session()->regenerate();
        $request->session()->put('two_factor.pending', [
            'id' => $user->id,
            'password_hash' => $user->password,
            'fingerprint' => $this->fingerprint($user),
            'expires_at' => now()->addMinutes(5)->timestamp,
        ]);
    }

    public function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get('two_factor.pending');
        $user = isset($pending['id']) ? User::find($pending['id']) : null;
        if (! $user?->hasTwoFactorAuthentication() || ($pending['expires_at'] ?? 0) < now()->timestamp ||
            ! hash_equals($user->password, $pending['password_hash'] ?? '') ||
            ! hash_equals($this->fingerprint($user), $pending['fingerprint'] ?? '')) {
            $request->session()->forget('two_factor.pending');

            return null;
        }

        return $user;
    }
}
