<?php

namespace Tests\Feature;

use App\Models\AuthVerificationCode;
use App\Models\User;
use App\Notifications\AuthVerificationCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_contact_check_rejects_taken_details_without_creating_records(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'existing@example.test', 'contact_number' => '09123456789']);
        $this->postJson(route('register.contact-check'), [
            'name' => 'New Customer', 'email' => 'EXISTING@example.test', 'contact_number' => '09123456789',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email', 'contact_number']);
        $this->postJson(route('register.contact-check'), [
            'name' => 'New Customer', 'email' => 'available@example.test', 'contact_number' => '09987654321',
        ])->assertOk()->assertExactJson(['valid' => true]);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('auth_verification_codes', 0);
        Notification::assertNothingSent();
    }

    public function test_contact_check_preserves_existing_walk_in_registration_matching(): void
    {
        $walkIn = app(\App\Services\WalkInClientService::class)->resolveUser('Walk In Customer', 'walkin@example.test', '09123456789');
        $this->assertTrue($walkIn->isWalkIn());
        $this->postJson(route('register.contact-check'), [
            'name' => $walkIn->name, 'email' => $walkIn->email, 'contact_number' => $walkIn->contact_number,
        ])->assertOk()->assertJson(['valid' => true]);
        $this->assertTrue($walkIn->fresh()->isWalkIn());
    }

    public function test_duplicate_phone_is_rechecked_at_signup_and_after_email_verification(): void
    {
        Notification::fake();
        $registration = [
            'name' => 'New Customer', 'username' => 'new_customer', 'email' => 'new@example.test',
            'contact_number' => '09123456789', 'birthday' => '2000-01-01', 'sex' => User::SEX_MALE,
            'password' => 'SecurePass1', 'password_confirmation' => 'SecurePass1', 'terms_accepted' => '1',
        ];
        $this->post(route('register'), $registration)->assertRedirect();
        $verification = AuthVerificationCode::query()->firstOrFail();
        $code = '';
        Notification::assertSentOnDemand(AuthVerificationCodeNotification::class, function ($notification) use (&$code) {
            $code = $notification->code;
            return true;
        });
        User::factory()->create(['contact_number' => $registration['contact_number']]);
        $this->post(route('register'), $registration)->assertSessionHasErrors(['contact_number'], null, 'register');
        $this->postJson(route('verification.verify', $verification), [
            'purpose' => AuthVerificationCode::PURPOSE_REGISTRATION, 'code' => $code,
        ])->assertUnprocessable()->assertJsonValidationErrors('contact_number');
        $this->assertDatabaseMissing('users', ['email' => $registration['email']]);
    }

    public function test_verification_emails_include_copyable_code_expiry_and_purpose_in_both_formats(): void
    {
        config()->set('mail.default', 'array');
        foreach ([
            AuthVerificationCode::PURPOSE_PASSWORD_RESET => 'reset your password',
            AuthVerificationCode::PURPOSE_REGISTRATION => 'complete your registration',
            AuthVerificationCode::PURPOSE_EMAIL_CHANGE => 'confirm your new email address',
        ] as $purpose => $instruction) {
            Notification::route('mail', 'customer@example.test')->notify(
                new AuthVerificationCodeNotification('678901', $purpose, 'Customer <preview>')
            );
            $message = Mail::mailer('array')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
            $html = $message->getHtmlBody();
            $text = $message->getTextBody();
            foreach ([$html, $text] as $body) {
                $this->assertStringContainsString('678901', $body);
                $this->assertStringContainsString($instruction, $body);
                $this->assertStringContainsString('10', $body);
                $this->assertStringContainsString('Do not share this code', $body);
                $this->assertStringContainsString('where you requested this code', $body);
            }
            $this->assertStringContainsString('Customer &lt;preview&gt;', $html);
            $this->assertStringContainsString('cid:touch-n-relief-logo', $html);
            $this->assertStringContainsString('Content-ID:', $message->toString());
        }
    }

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
        $this->get(route('data-deletion'))->assertOk()->assertSee('User Data Deletion');
    }

    public function test_authentication_page_makes_only_the_visible_form_keyboard_interactive(): void
    {
        $loginHtml = $this->get(route('login'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/class="form-container sign-up"[^>]*aria-hidden="true"[^>]*\binert\b/', $loginHtml);
        $this->assertMatchesRegularExpression('/class="form-container sign-in"[^>]*aria-hidden="false"/', $loginHtml);
        $this->assertMatchesRegularExpression('/class="auth-forgot-panel"[^>]*aria-hidden="true"[^>]*\binert\b/', $loginHtml);

        $registerHtml = $this->get(route('login', ['register' => 1]))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/class="form-container sign-up"[^>]*aria-hidden="false"/', $registerHtml);
        $this->assertMatchesRegularExpression('/class="form-container sign-in"[^>]*aria-hidden="true"[^>]*\binert\b/', $registerHtml);
    }

    public function test_authentication_csrf_fields_do_not_use_hidden_input_autocomplete(): void
    {
        $response = $this->get(route('login'))->assertOk();
        $content = $response->getContent();

        $this->assertGreaterThanOrEqual(3, substr_count($content, 'name="_token"'));
        $this->assertDoesNotMatchRegularExpression(
            '/<input\b(?=[^>]*name="_token")(?=[^>]*autocomplete=)[^>]*>/i',
            $content,
        );
    }

    public function test_authentication_header_provides_home_access_without_a_navigation_menu(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('aria-label="Back to home"', false)
            ->assertDontSee('aria-label="Open navigation menu"', false)
            ->assertDontSee('tnr-landing-theme-toggle');

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('aria-label="Back to home"', false)
            ->assertDontSee('aria-label="Open navigation menu"', false);

        $this->get(route('landing'))
            ->assertOk()
            ->assertDontSee('tnr-landing-theme-toggle');
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
