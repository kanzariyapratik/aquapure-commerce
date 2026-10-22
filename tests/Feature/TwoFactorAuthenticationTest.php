<?php

use App\Models\User;
use App\Services\TwoFactorAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now()->startOfMinute()->addSeconds(5));
    }

    private function user(string $role = 'customer'): User
    {
        return User::create(['name' => 'Test User', 'email' => 'user@example.com', 'password' => 'OldPassword123!', 'role' => $role]);
    }

    private function service(): TwoFactorAuthentication
    {
        return app(TwoFactorAuthentication::class);
    }

    private function enable(User $user): array
    {
        $codes = $this->service()->recoveryCodes();
        $user->forceFill([
            'two_factor_secret' => $this->service()->secret(),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $this->service()->hashRecoveryCodes($codes),
        ])->save();

        return $codes;
    }

    private function code(string $secret, int $offset = 0): string
    {
        return (new Google2FA)->oathTotp($secret, intdiv(now()->timestamp, 30) + $offset);
    }

    private function verified(User $user): void
    {
        $this->actingAs($user)->withSession(['two_factor.verified' => $this->service()->fingerprint($user)]);
    }

    private function login(string $password = 'OldPassword123!')
    {
        return $this->post('/login', ['email' => 'user@example.com', 'password' => $password]);
    }

    #[TestWith(['customer', '/account/security'])]
    #[TestWith(['admin', '/admin/security'])]
    public function test_enrollment_requires_password_and_valid_code_and_stores_secrets_safely(string $role, string $path): void
    {
        $user = $this->user($role);
        $this->actingAs($user)->post('/account/two-factor', ['current_password' => 'wrong'])->assertSessionHasErrors('current_password');
        $this->assertNull(session('two_factor.setup'));
        $this->post('/account/two-factor', ['current_password' => 'OldPassword123!'])->assertRedirect($path);
        $pending = session('two_factor.setup');
        $secret = decrypt($pending['secret']);
        $this->assertNotSame($secret, $pending['secret']);
        $this->assertFalse($user->fresh()->hasTwoFactorAuthentication());
        $this->get($path)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Security')
                ->where('setup.secret', $secret)->where('setup.qrCode', fn ($qr) => str_starts_with($qr, 'data:image/svg+xml;base64,')));
        $this->post('/account/two-factor/confirm', ['code' => '12345'])->assertSessionHasErrors('code');
        $this->assertNull(session()->getOldInput('code'));
        $this->post('/account/two-factor/confirm', ['code' => $this->code($secret)])->assertRedirect($path)->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertTrue($user->hasTwoFactorAuthentication());
        $this->assertSame($this->service()->fingerprint($user), session('two_factor.verified'));
        $raw = DB::table('users')->where('id', $user->id)->first();
        $this->assertNotSame($secret, $raw->two_factor_secret);
        $codes = decrypt(session('two_factor.new_recovery_codes'));
        $this->assertCount(10, $codes);
        $this->assertStringNotContainsString($codes[0], $raw->two_factor_recovery_codes);
        $this->assertNotContains($codes[0], $user->two_factor_recovery_codes);
        $this->assertArrayNotHasKey('two_factor_secret', $user->toArray());
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $user->toArray());
        $this->get($path)->assertInertia(fn (Assert $page) => $page->where('enabled', true)->where('setup', null)->has('recoveryCodes', 10));
        $this->get($path)->assertInertia(fn (Assert $page) => $page->has('recoveryCodes', 0));
    }

    public function test_admin_cannot_access_admin_pages_until_enrollment(): void
    {
        $this->actingAs($this->user('admin'));
        foreach (['/admin', '/admin/products', '/admin/orders', '/admin/enquiries', '/admin/password'] as $path) {
            $this->get($path)->assertRedirect('/admin/security');
        }
        $this->post('/admin/products', [])->assertRedirect('/admin/security');
        $this->get('/admin/security')->assertOk()->assertInertia(fn (Assert $page) => $page->where('required', true)->where('enabled', false));
    }

    public function test_customer_cannot_access_admin_security_page(): void
    {
        $this->actingAs($this->user())->get('/admin/security')->assertForbidden();
    }

    #[TestWith(['customer'])]
    #[TestWith(['admin'])]
    public function test_password_alone_does_not_create_authenticated_session(string $role): void
    {
        $user = $this->user($role);
        $this->enable($user);
        $this->login()->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
        $this->assertNull(session(Auth::guard()->getName()));
        $this->get('/account/password')->assertRedirect('/login');
        $this->get('/two-factor-challenge')->assertOk();
        $this->post('/two-factor-challenge', ['code' => $this->code($user->two_factor_secret)])
            ->assertSessionHasNoErrors()->assertRedirect('/account/password');
        $this->assertAuthenticatedAs($user);
        $this->assertNull(session('two_factor.pending'));
        $this->get($role === 'admin' ? '/admin/security' : '/account/security')->assertOk();
    }

    public function test_recovery_code_works_once_and_authenticator_code_cannot_be_replayed(): void
    {
        $user = $this->user();
        $codes = $this->enable($user);
        $this->login();
        $this->post('/two-factor-challenge', ['recovery_code' => $codes[0]])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
        $this->assertCount(9, $user->fresh()->two_factor_recovery_codes);
        $this->post('/logout');
        $this->login();
        $this->post('/two-factor-challenge', ['recovery_code' => $codes[0]])->assertSessionHasErrors('code');
        $this->assertGuest();
        $code = $this->code($user->two_factor_secret);
        $this->post('/two-factor-challenge', ['code' => $code])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
        $this->post('/logout');
        $this->login();
        $this->post('/two-factor-challenge', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->travel(30)->seconds();
        $this->post('/two-factor-challenge', ['code' => $this->code($user->two_factor_secret)])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    #[TestWith(['missing'])]
    #[TestWith(['expired'])]
    #[TestWith(['password-changed'])]
    #[TestWith(['secret-changed'])]
    public function test_challenge_is_bound_to_password_secret_and_short_lived_session(string $scenario): void
    {
        $user = $this->user();
        $this->enable($user);
        if ($scenario !== 'missing') {
            $this->login();
        }
        if ($scenario === 'expired') {
            $this->travel(6)->minutes();
        } elseif ($scenario === 'password-changed') {
            $user->update(['password' => 'ChangedPassword789!']);
        } elseif ($scenario === 'secret-changed') {
            $user->forceFill(['two_factor_secret' => $this->service()->secret()])->save();
        }
        $this->post('/two-factor-challenge', ['code' => $this->code($user->two_factor_secret)])->assertRedirect('/login');
        $this->assertGuest();
        $this->assertNull(session('two_factor.pending'));
    }

    public function test_two_factor_attempt_limit_cannot_be_reset_with_new_password_login(): void
    {
        $user = $this->user();
        $this->enable($user);
        $this->login();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/two-factor-challenge', ['code' => '12345'])->assertSessionHasErrors('code');
        }
        $this->delete('/two-factor-challenge')->assertRedirect('/login');
        $this->login();
        $this->post('/two-factor-challenge', ['code' => $this->code($user->two_factor_secret)])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->travel(61)->seconds();
        $this->post('/two-factor-challenge', ['code' => $this->code($user->two_factor_secret)])->assertSessionHasErrors('code');
    }

    public function test_wrong_password_attempts_are_limited_per_account(): void
    {
        $this->user();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->login('wrong-password')->assertSessionHasErrors('email');
        }
        $this->login()->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many sign-in attempts', session('errors')->first('email'));
        $this->assertGuest();
        $this->travel(61)->seconds();
        $this->login()->assertSessionHasNoErrors();
        $this->assertAuthenticated();
    }

    public function test_existing_session_without_second_factor_is_challenged(): void
    {
        $user = $this->user();
        $this->enable($user);
        $this->actingAs($user)->get('/account/password')->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
        $this->assertSame($user->id, session('two_factor.pending.id'));
    }

    public function test_password_reset_does_not_remove_second_factor(): void
    {
        $user = $this->user();
        $this->enable($user);
        $this->login();
        $this->post('/reset-password', [
            'email' => $user->email, 'token' => Password::createToken($user),
            'password' => 'NewPassword456!', 'password_confirmation' => 'NewPassword456!',
        ])->assertRedirect('/login');
        $this->assertTrue($user->fresh()->hasTwoFactorAuthentication());
        $this->post('/two-factor-challenge', ['code' => $this->code($user->two_factor_secret)])->assertRedirect('/login');
        $this->login('NewPassword456!')->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
    }

    public function test_enrollment_expires_and_cannot_overwrite_enabled_authenticator(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post('/account/two-factor', ['current_password' => 'OldPassword123!']);
        $secret = decrypt(session('two_factor.setup.secret'));
        $this->travel(11)->minutes();
        $this->post('/account/two-factor/confirm', ['code' => $this->code($secret)])->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasTwoFactorAuthentication());
        $this->enable($user);
        $this->verified($user);
        $this->post('/account/two-factor', ['current_password' => 'OldPassword123!'])->assertStatus(409);
    }

    public function test_regenerating_recovery_codes_requires_both_factors_and_invalidates_old_codes(): void
    {
        $user = $this->user();
        $oldCodes = $this->enable($user);
        $this->verified($user);
        $this->post('/account/two-factor/recovery-codes', ['current_password' => 'wrong', 'recovery_code' => $oldCodes[0]])
            ->assertSessionHasErrors('current_password');
        $this->assertCount(10, $user->fresh()->two_factor_recovery_codes);
        $this->post('/account/two-factor/recovery-codes', ['current_password' => 'OldPassword123!', 'recovery_code' => $oldCodes[0]])
            ->assertSessionHasNoErrors()->assertRedirect('/account/security');
        $newCodes = decrypt(session('two_factor.new_recovery_codes'));
        $this->assertCount(10, $newCodes);
        $this->assertNotSame($oldCodes, $newCodes);
        $this->assertNull($this->service()->consume($user->fresh(), null, $oldCodes[1]));
        $this->assertNotNull($this->service()->consume($user->fresh(), null, $newCodes[0]));
    }

    public function test_customer_disabling_second_factor_requires_password_and_fresh_factor(): void
    {
        $user = $this->user();
        $codes = $this->enable($user);
        $this->verified($user);
        $this->delete('/account/two-factor', ['current_password' => 'OldPassword123!'])->assertSessionHasErrors('code');
        $this->assertTrue($user->fresh()->hasTwoFactorAuthentication());
        $this->delete('/account/two-factor', ['current_password' => 'OldPassword123!', 'recovery_code' => $codes[0]])
            ->assertRedirect('/account/security')->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->hasTwoFactorAuthentication());
        $this->assertNull($user->fresh()->two_factor_secret);
        $this->assertNull($user->fresh()->two_factor_recovery_codes);
        $this->assertNull(session('two_factor.verified'));
    }

    public function test_admin_cannot_disable_two_factor_authentication(): void
    {
        $user = $this->user('admin');
        $codes = $this->enable($user);
        $this->verified($user);
        $this->delete('/account/two-factor', ['current_password' => 'OldPassword123!', 'recovery_code' => $codes[0]])->assertForbidden();
        $this->assertTrue($user->fresh()->hasTwoFactorAuthentication());
    }

    public function test_replacing_authenticator_invalidates_previous_verified_session(): void
    {
        $user = $this->user();
        $this->enable($user);
        $this->verified($user);
        $this->enable($user);
        $this->get('/account/security')->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
    }

    #[TestWith(['short123'])]
    #[TestWith(['onlyletterspassword'])]
    #[TestWith(['1234567890123456'])]
    #[TestWith(['éééééééééééééééééééééééééééééééééééé123'])]
    public function test_registration_rejects_weak_or_bcrypt_overlength_passwords(string $password): void
    {
        $this->post('/register', [
            'name' => 'User', 'email' => 'new@example.com',
            'password' => $password, 'password_confirmation' => $password,
        ])->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_cannot_assign_admin_role(): void
    {
        $this->post('/register', [
            'name' => 'User', 'email' => 'new@example.com', 'role' => 'admin',
            'password' => 'StrongPassword123!', 'password_confirmation' => 'StrongPassword123!',
        ])->assertRedirect('/account/security');
        $this->assertSame('customer', User::first()->role);
    }

    public function test_guest_cannot_read_or_modify_two_factor_settings(): void
    {
        $this->get('/account/security')->assertRedirect('/login');
        $this->get('/admin/security')->assertRedirect('/login');
        $this->post('/account/two-factor')->assertRedirect('/login');
        $this->post('/account/two-factor/confirm')->assertRedirect('/login');
        $this->post('/account/two-factor/recovery-codes')->assertRedirect('/login');
        $this->delete('/account/two-factor')->assertRedirect('/login');
    }

    public function test_expired_authenticator_codes_are_rejected(): void
    {
        $user = $this->user();
        $this->enable($user);
        $oldCode = $this->code($user->two_factor_secret, -2);
        $this->login();
        $this->post('/two-factor-challenge', ['code' => $oldCode])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_two_factor_settings_require_recent_current_password_confirmation(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post('/account/two-factor', ['current_password' => 'OldPassword123!']);
        $setup = session('two_factor.setup');
        $user->update(['password' => 'DifferentPassword123!']);
        $this->withSession(['password_hash_web' => $user->password]);
        $this->post('/account/two-factor/confirm', ['code' => $this->code(decrypt($setup['secret']))])->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasTwoFactorAuthentication());
    }

    public function test_authentication_pages_are_not_cached(): void
    {
        $this->get('/login')->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/forgot-password')->assertHeader('Cache-Control', 'no-store, private');
    }
}
