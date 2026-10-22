<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;

class PasswordManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function user(string $role = 'customer'): User
    {
        return User::create([
            'name' => 'Test User',
            'email' => 'user@example.com',
            'password' => 'OldPassword123!',
            'role' => $role,
        ]);
    }

    private function resetData(User $user, string $token): array
    {
        return [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewPassword456!',
            'password_confirmation' => 'NewPassword456!',
        ];
    }

    private function changeData(): array
    {
        return [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword456!',
            'password_confirmation' => 'NewPassword456!',
        ];
    }

    private function prepareAdmin(User $user): void
    {
        if ($user->role === 'admin') {
            $user->forceFill([
                'two_factor_secret' => 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
                'two_factor_confirmed_at' => now(),
            ])->save();
            $this->withSession(['two_factor.verified' => app(\App\Services\TwoFactorAuthentication::class)->fingerprint($user)]);
        }
    }

    public function test_guest_can_open_recovery_pages(): void
    {
        $this->get('/forgot-password')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/ForgotPassword'));

        $this->get('/reset-password/example-token?email=user%40example.com')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/ResetPassword')
                ->where('token', 'example-token')->where('email', 'user@example.com'));
    }

    #[TestWith(['customer'])]
    #[TestWith(['admin'])]
    public function test_accounts_receive_a_usable_reset_notification(string $role): void
    {
        Notification::fake();
        $user = $this->user($role);

        $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect('/forgot-password')->assertSessionHas('success');

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $this->assertTrue(Password::tokenExists($user, $notification->token));
            $this->assertSame(
                route('password.reset', ['token' => $notification->token, 'email' => $user->email]),
                $notification->toMail($user)->actionUrl,
            );

            return true;
        });
    }

    public function test_recovery_does_not_disclose_unknown_accounts_or_resend_immediately(): void
    {
        Notification::fake();
        $user = $this->user();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('success');
        $message = session('success');

        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('success', $message);
        $this->post('/forgot-password', ['email' => 'unknown@example.com'])->assertSessionHas('success', $message);
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    public function test_recovery_validates_email_and_limits_requests(): void
    {
        Notification::fake();
        $this->post('/forgot-password', ['email' => 'invalid'])->assertSessionHasErrors('email');
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->post('/forgot-password', ['email' => 'unknown@example.com'])->assertRedirect();
        }
        $this->post('/forgot-password', ['email' => 'unknown@example.com'])->assertStatus(429);
        Notification::assertNothingSent();
    }

    #[TestWith(['customer'])]
    #[TestWith(['admin'])]
    public function test_password_can_be_reset_once_and_used_to_sign_in(string $role): void
    {
        $user = $this->user($role);
        $user->setRememberToken('previous-remember-token');
        $user->save();
        $data = $this->resetData($user, Password::createToken($user));

        $this->post('/reset-password', $data)->assertRedirect('/login')->assertSessionHas('success');
        $this->assertTrue(Hash::check($data['password'], $user->fresh()->password));
        $this->assertSame($role, $user->fresh()->role);
        $this->assertNotSame('previous-remember-token', $user->fresh()->getRememberToken());
        $this->assertGuest();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);

        $this->post('/reset-password', $data)->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => 'OldPassword123!'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => $data['password']])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    #[TestWith(['invalid'])]
    #[TestWith(['expired'])]
    #[TestWith(['wrong-email'])]
    public function test_invalid_expired_and_mismatched_reset_links_are_rejected(string $scenario): void
    {
        $user = $this->user();
        $token = Password::createToken($user);
        $data = $this->resetData($user, $token);
        if ($scenario === 'invalid') {
            $data['token'] = 'invalid-token';
        } elseif ($scenario === 'expired') {
            $this->travel(61)->minutes();
        } else {
            $other = User::create(['name' => 'Other', 'email' => 'other@example.com', 'password' => 'OtherPassword123!']);
            $data['email'] = $other->email;
        }

        $this->post('/reset-password', $data)->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('OldPassword123!', $user->fresh()->password));
        if (isset($other)) {
            $this->assertTrue(Hash::check('OtherPassword123!', $other->fresh()->password));
        }
    }

    #[TestWith(['short', 'short'])]
    #[TestWith(['Eight123', 'Eight123'])]
    #[TestWith(['NewPassword456!', 'does-not-match'])]
    public function test_reset_requires_a_confirmed_password_of_at_least_twelve_characters(string $password, string $confirmation): void
    {
        $user = $this->user();
        $data = $this->resetData($user, Password::createToken($user));
        $data['password'] = $password;
        $data['password_confirmation'] = $confirmation;

        $this->post('/reset-password', $data)->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('OldPassword123!', $user->fresh()->password));
    }

    #[TestWith(['customer', '/account/password', false])]
    #[TestWith(['admin', '/admin/password', true])]
    public function test_signed_in_accounts_can_change_their_password(string $role, string $path, bool $admin): void
    {
        $user = $this->user($role);
        $this->prepareAdmin($user);
        $token = Password::createToken($user);
        $user->setRememberToken('previous-remember-token');
        $user->save();
        $this->actingAs($user)->get($path)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/ChangePassword')->where('admin', $admin));

        $this->from($path)->put($path, $this->changeData())->assertRedirect($path)
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertTrue(Hash::check('NewPassword456!', $user->fresh()->password));
        $this->assertSame($role, $user->fresh()->role);
        $this->assertNotSame('previous-remember-token', $user->fresh()->getRememberToken());
        $this->assertFalse(Password::tokenExists($user->fresh(), $token));
        $this->get($path)->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    #[TestWith(['/account/password', 'customer', 'current_password', 'wrong-password'])]
    #[TestWith(['/admin/password', 'admin', 'current_password', 'wrong-password'])]
    #[TestWith(['/account/password', 'customer', 'password', 'short'])]
    #[TestWith(['/admin/password', 'admin', 'password', 'short'])]
    #[TestWith(['/account/password', 'customer', 'password_confirmation', 'mismatch'])]
    #[TestWith(['/admin/password', 'admin', 'password_confirmation', 'mismatch'])]
    #[TestWith(['/account/password', 'customer', 'password', 'OldPassword123!'])]
    public function test_change_password_validation_preserves_existing_password(string $path, string $role, string $field, string $value): void
    {
        $user = $this->user($role);
        $this->prepareAdmin($user);
        $data = $this->changeData();
        $data[$field] = $value;
        if ($field === 'password') {
            $data['password_confirmation'] = $value;
        }

        $this->actingAs($user)->put($path, $data)
            ->assertSessionHasErrors($field === 'current_password' ? 'current_password' : 'password');
        $this->assertTrue(Hash::check('OldPassword123!', $user->fresh()->password));
        $this->assertNull(session()->getOldInput('current_password'));
        $this->assertNull(session()->getOldInput('password'));
    }

    public function test_customer_cannot_access_admin_password_endpoints(): void
    {
        $user = $this->user();
        $this->actingAs($user)->get('/admin/password')->assertForbidden();
        $this->put('/admin/password', $this->changeData())->assertForbidden();
        $this->assertTrue(Hash::check('OldPassword123!', $user->fresh()->password));
    }

    #[TestWith(['/account/password'])]
    #[TestWith(['/admin/password'])]
    public function test_guest_cannot_access_change_password_endpoints(string $path): void
    {
        $this->get($path)->assertRedirect('/login');
        $this->put($path, $this->changeData())->assertRedirect('/login');
    }

    public function test_password_change_requests_are_rate_limited(): void
    {
        $this->actingAs($this->user());
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->put('/account/password', ['current_password' => 'wrong'])
                ->assertSessionHasErrors('current_password');
        }
        $this->put('/account/password', $this->changeData())->assertStatus(429);
    }

    public function test_existing_session_with_an_old_password_hash_is_logged_out(): void
    {
        $user = $this->user();
        $oldHash = $user->password;
        $user->update(['password' => 'NewPassword456!']);

        $this->actingAs($user->fresh())->withSession(['password_hash_web' => $oldHash])
            ->get('/account/password')->assertRedirect('/login');
        $this->assertGuest();
    }
}
