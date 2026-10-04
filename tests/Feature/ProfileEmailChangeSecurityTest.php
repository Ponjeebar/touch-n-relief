<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureCurrentStaffSession;
use App\Models\AuthVerificationCode;
use App\Models\Customer;
use App\Models\Receptionist;
use App\Models\User;
use App\Notifications\AuthVerificationCodeNotification;
use App\Notifications\ProfileEmailChangeRequestedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProfileEmailChangeSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_email_changes_only_after_verification_and_updates_the_linked_record(): void
    {
        Notification::fake();
        $user = $this->createUser(User::ROLE_USER, 'customer-old@example.test');
        $customer = Customer::query()->create([
            'customer_id' => 'CUS-001',
            'full_name' => $user->name,
            'email' => $user->email,
            'password' => 'CurrentPassword9',
        ]);
        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('name="email_current_password"', false)
            ->assertSee('emailPassword.disabled = !isEditing;', false);

        [$verification, $code] = $this->requestEmailChange($user, 'customer-new@example.test');

        $this->assertSame('customer-old@example.test', $user->fresh()->email);
        $this->assertSame('customer-old@example.test', $customer->fresh()->email);
        $this->assertOldAddressWasNotified('customer-old@example.test', 'customer-new@example.test');
        $this->get(route('profile.email-change.show', $verification))
            ->assertOk()
            ->assertSee('customer-new@example.test');

        $this->post(route('profile.email-change.verify', $verification), ['code' => $code])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasNoErrors();

        $this->assertSame('customer-new@example.test', $user->fresh()->email);
        $this->assertSame('customer-new@example.test', $customer->fresh()->email);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseMissing('auth_verification_codes', ['id' => $verification->getKey()]);
    }

    public function test_receptionist_email_verification_updates_the_linked_record_and_preserves_the_current_staff_session(): void
    {
        Notification::fake();
        $user = $this->createUser(User::ROLE_RECEPTIONIST, 'staff-old@example.test');
        $receptionist = Receptionist::query()->create([
            'receptionist_id' => 'REC-001',
            'full_name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'shift' => 'Morning',
        ]);

        [$verification, $code] = $this->requestEmailChange($user, 'staff-new@example.test');
        $this->post(route('profile.email-change.verify', $verification), ['code' => $code])
            ->assertRedirect(route('receptionist.dashboard'))
            ->assertSessionHasNoErrors();

        $newToken = (string) $user->fresh()->staff_session_token;
        $this->assertNotSame('', $newToken);
        $this->assertSame($newToken, session(EnsureCurrentStaffSession::SESSION_KEY));
        $this->assertSame('staff-new@example.test', $receptionist->fresh()->email);
        $this->get(route('receptionist.dashboard'))->assertOk();
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_administrator_email_change_requires_verification_and_keeps_the_current_session_valid(): void
    {
        Notification::fake();
        $admin = $this->createUser(User::ROLE_ADMIN, 'admin-old@example.test');

        [$verification, $code] = $this->requestEmailChange($admin, 'admin-new@example.test');
        $this->assertSame('admin-old@example.test', $admin->fresh()->email);

        $this->post(route('profile.email-change.verify', $verification), ['code' => $code])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasNoErrors();

        $token = (string) $admin->fresh()->staff_session_token;
        $this->assertSame('admin-new@example.test', $admin->fresh()->email);
        $this->assertSame($token, session(EnsureCurrentStaffSession::SESSION_KEY));
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_email_change_requires_the_current_password(): void
    {
        Notification::fake();
        $user = $this->createUser(User::ROLE_USER, 'old@example.test');

        $this->actingAs($user)->from(route('profile.edit'))->put(route('profile.update'), [
            'name' => $user->name,
            'email' => 'new@example.test',
            'username' => $user->username,
            'current_password' => 'WrongPassword9',
            'email_current_password' => 'WrongPassword9',
        ])->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors(['email_current_password'], null, 'profile');

        $this->assertSame('old@example.test', $user->fresh()->email);
        $this->assertDatabaseCount('auth_verification_codes', 0);
        Notification::assertNothingSent();
    }

    public function test_email_verification_rejects_wrong_expired_reused_and_other_users_requests(): void
    {
        Notification::fake();
        $owner = $this->createUser(User::ROLE_USER, 'owner@example.test');
        $other = $this->createUser(User::ROLE_USER, 'other@example.test');
        [$verification, $code] = $this->requestEmailChange($owner, 'owner-new@example.test');

        $this->actingAs($other)
            ->get(route('profile.email-change.show', $verification))
            ->assertNotFound();
        $this->post(route('profile.email-change.verify', $verification), ['code' => $code])
            ->assertNotFound();

        $this->actingAs($owner)
            ->post(route('profile.email-change.verify', $verification), ['code' => '000000'])
            ->assertSessionHasErrors(['code']);
        $this->assertSame('owner@example.test', $owner->fresh()->email);

        $verification->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->post(route('profile.email-change.verify', $verification), ['code' => $code])
            ->assertSessionHasErrors(['code']);
        $this->assertDatabaseMissing('auth_verification_codes', ['id' => $verification->getKey()]);

        [$replacement, $replacementCode] = $this->requestEmailChange($owner->fresh(), 'owner-new@example.test');
        $this->post(route('profile.email-change.verify', $replacement), ['code' => $replacementCode])
            ->assertRedirect(route('profile.edit'));
        $this->post(route('profile.email-change.verify', $replacement), ['code' => $replacementCode])
            ->assertNotFound();
    }

    public function test_email_uniqueness_is_rechecked_when_the_code_is_used(): void
    {
        Notification::fake();
        $owner = $this->createUser(User::ROLE_USER, 'owner@example.test');
        [$verification, $code] = $this->requestEmailChange($owner, 'claimed@example.test');
        $this->createUser(User::ROLE_USER, 'claimed@example.test');

        $this->post(route('profile.email-change.verify', $verification), ['code' => $code])
            ->assertSessionHasErrors(['code']);

        $this->assertSame('owner@example.test', $owner->fresh()->email);
        $this->assertDatabaseHas('auth_verification_codes', ['id' => $verification->getKey()]);
    }

    public function test_sensitive_profile_values_are_never_flashed_by_manual_or_validation_redirects(): void
    {
        $user = $this->createUser(User::ROLE_USER, 'safe@example.test');
        $sensitiveValues = [
            '_token' => 'csrf-secret',
            'api_key' => 'api-secret',
            'client_secret' => 'client-secret',
            'code' => '123456',
            'current_password' => 'WrongPassword9',
            'email_current_password' => 'WrongPassword9',
            'password' => 'ReplacementPassword9',
            'password_confirmation' => 'ReplacementPassword9',
            'secret' => 'generic-secret',
            'token' => 'private-token',
        ];

        $this->actingAs($user)->from(route('profile.edit'))->put(route('profile.update'), [
            ...$sensitiveValues,
            'name' => 'Safe Name',
            'email' => $user->email,
            'username' => $user->username,
        ])->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors(['current_password'], null, 'profile');

        $oldInput = session()->getOldInput();
        $this->assertSame('Safe Name', $oldInput['name'] ?? null);
        foreach (array_keys($sensitiveValues) as $key) {
            $this->assertArrayNotHasKey($key, $oldInput);
        }

        $this->from(route('profile.edit'))->put(route('profile.update'), [
            ...$sensitiveValues,
            'name' => 'Safe Name',
            'email' => $user->email,
            'username' => $user->username,
            'current_password' => 'CurrentPassword9',
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ])->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors(['password'], null, 'profile');

        $oldInput = session()->getOldInput();
        foreach (array_keys($sensitiveValues) as $key) {
            $this->assertArrayNotHasKey($key, $oldInput);
        }
        $this->assertStringNotContainsString('CurrentPassword9', serialize(session('errors')));
        $this->assertStringNotContainsString('private-token', serialize(session('errors')));
    }

    /** @return array{AuthVerificationCode, string} */
    private function requestEmailChange(User $user, string $newEmail): array
    {
        $code = null;
        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'email' => $newEmail,
            'username' => $user->username,
            'current_password' => 'CurrentPassword9',
            'email_current_password' => 'CurrentPassword9',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $verification = AuthVerificationCode::query()
            ->where('purpose', AuthVerificationCode::PURPOSE_EMAIL_CHANGE)
            ->where('email', $newEmail)
            ->firstOrFail();
        Notification::assertSentOnDemand(AuthVerificationCodeNotification::class, function ($notification) use (&$code): bool {
            if ($notification->purpose !== AuthVerificationCode::PURPOSE_EMAIL_CHANGE) {
                return false;
            }

            $code = $notification->code;

            return true;
        });
        $this->assertIsString($code);

        return [$verification, $code];
    }

    private function createUser(string $role, string $email): User
    {
        return User::factory()->create([
            'role' => $role,
            'email' => $email,
            'password' => 'CurrentPassword9',
            'email_verified_at' => now(),
        ]);
    }

    private function assertOldAddressWasNotified(string $oldEmail, string $newEmail): void
    {
        Notification::assertSentOnDemand(
            ProfileEmailChangeRequestedNotification::class,
            function ($notification, array $channels, object $notifiable) use ($oldEmail, $newEmail): bool {
                return $notification->newEmail === $newEmail
                    && $notifiable->routeNotificationFor('mail') === $oldEmail;
            },
        );
    }
}
