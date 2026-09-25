<?php

namespace Tests\Feature;

use App\Models\AuthVerificationCode;
use App\Models\User;
use App\Notifications\AuthVerificationCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_account_only_after_email_code_is_verified(): void
    {
        Notification::fake();
        $code = null;
        $registration = [
            'name' => 'Verified Customer',
            'username' => 'verified_customer',
            'email' => 'verified@example.test',
            'contact_number' => '09123456789',
            'birthday' => '2000-01-01',
            'sex' => User::SEX_FEMALE,
            'password' => 'SecurePass1',
            'password_confirmation' => 'SecurePass1',
            'terms_accepted' => '1',
        ];

        $this->post(route('register'), $registration)->assertRedirect();
        $this->assertDatabaseMissing('users', ['email' => $registration['email']]);
        $verification = AuthVerificationCode::query()->firstOrFail();

        Notification::assertSentOnDemand(AuthVerificationCodeNotification::class, function ($notification) use (&$code): bool {
            $code = $notification->code;

            return $notification->purpose === AuthVerificationCode::PURPOSE_REGISTRATION;
        });

        $this->post(route('verification.verify', $verification), [
            'purpose' => AuthVerificationCode::PURPOSE_REGISTRATION,
            'code' => $code,
        ])->assertRedirect();

        $user = User::query()->where('email', $registration['email'])->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseMissing('auth_verification_codes', ['id' => $verification->id]);
    }

    public function test_registration_password_requires_uppercase_number_and_eight_characters(): void
    {
        $base = [
            'name' => 'Customer',
            'username' => 'customer_name',
            'email' => 'customer@example.test',
            'contact_number' => '09123456789',
            'birthday' => '2000-01-01',
            'sex' => User::SEX_MALE,
            'terms_accepted' => '1',
        ];

        $this->post(route('register'), [...$base, 'password' => 'lowercase1', 'password_confirmation' => 'lowercase1'])
            ->assertSessionHasErrors(['password'], null, 'register');
        $this->post(route('register'), [...$base, 'password' => 'NoNumberHere', 'password_confirmation' => 'NoNumberHere'])
            ->assertSessionHasErrors(['password'], null, 'register');
    }

    public function test_registration_requires_terms_and_privacy_agreement(): void
    {
        $this->post(route('register'), [
            'name' => 'Customer',
            'username' => 'customer_terms',
            'email' => 'terms@example.test',
            'contact_number' => '09123456789',
            'birthday' => '2000-01-01',
            'sex' => User::SEX_MALE,
            'password' => 'SecurePass1',
            'password_confirmation' => 'SecurePass1',
        ])->assertSessionHasErrors(['terms_accepted'], null, 'register');
    }

    public function test_login_and_legal_pages_expose_privacy_and_terms_links(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('data-auth-legal-open="privacy"', false)
            ->assertSee('data-auth-legal-open="terms"', false)
            ->assertSee('Information we collect')
            ->assertSee('Account responsibility');

        $this->get(route('privacy-policy'))->assertOk()->assertSee('Privacy Policy');
        $this->get(route('terms-and-conditions'))->assertOk()->assertSee('Terms and Conditions');
    }

    public function test_authentication_header_does_not_show_redundant_home_navigation(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('>Home<', false)
            ->assertDontSee('aria-label="Open navigation menu"', false);

        $this->get(route('password.request'))
            ->assertOk()
            ->assertDontSee('>Home<', false)
            ->assertDontSee('aria-label="Open navigation menu"', false);
    }

    public function test_password_reset_rejects_current_and_recent_passwords(): void
    {
        $user = User::factory()->create(['password' => 'OriginalPass1']);
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'OriginalPass1',
            'password_confirmation' => 'OriginalPass1',
        ])->assertSessionHasErrors('password');

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Replacement2',
            'password_confirmation' => 'Replacement2',
        ])->assertRedirect(route('login'));

        $token = Password::createToken($user->fresh());
        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'OriginalPass1',
            'password_confirmation' => 'OriginalPass1',
        ])->assertSessionHasErrors('password');
    }

    public function test_verification_code_locks_after_five_wrong_attempts(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post(route('password.email'), ['email' => $user->email]);
        $verification = AuthVerificationCode::query()->firstOrFail();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('verification.verify', $verification), [
                'purpose' => AuthVerificationCode::PURPOSE_PASSWORD_RESET,
                'code' => '000000',
            ])->assertSessionHasErrors('code');
        }

        $this->assertSame(5, $verification->fresh()->failed_attempts);
    }
}
