<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\BrandedResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_sign_in_errors_return_to_login_after_visiting_forgot_password(): void
    {
        $user = User::factory()->create(['username' => 'spa_guest', 'password' => 'CorrectPassword123!']);

        $this->from(route('password.request'))
            ->post(route('login.attempt'), ['login' => $user->username, 'password' => 'WrongPassword123!'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('login');

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('class="container  sign-in-forgot-active"', false);

        $this->from(route('password.request'))
            ->post(route('login.attempt'), ['login' => '', 'password' => ''])
            ->assertRedirect(route('login'));
    }

    public function test_reset_link_request_and_password_change(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => 'OldPassword123!']);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status');

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('sign-in-forgot-active');

        Notification::assertSentTo($user, BrandedResetPassword::class);
        $notification = Notification::sent($user, BrandedResetPassword::class)->first();
        $token = $notification->token;
        $mail = $notification->toMail($user);
        $html = view($mail->view['html'], $mail->viewData)->render();
        $text = view($mail->view['text'], $mail->viewData)->render();

        $this->assertSame('Reset your TouchNRelief password', $mail->subject);
        $this->assertStringContainsString('TouchNRelief', $html);
        $this->assertStringContainsString('cid:touch-n-relief-logo', $html);
        $this->assertStringContainsString($token, $html);
        $this->assertStringContainsString($token, $text);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk();

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
    }

    public function test_reset_email_renders_branded_html_with_embedded_logo(): void
    {
        config()->set('mail.default', 'array');
        $user = User::factory()->create();

        $user->notify(new BrandedResetPassword('test-reset-token'));

        $message = Mail::mailer('array')->getSymfonyTransport()->messages()->first()->getOriginalMessage();
        $mime = $message->toString();

        $this->assertStringContainsString('Reset your TouchNRelief password', $mime);
        $this->assertStringContainsString('Content-ID:', $mime);
        $this->assertStringContainsString('touch-n-relief-logo', $mime);
        $this->assertStringContainsString('test-reset-token', $mime);
    }

    public function test_reset_link_can_be_delivered_through_resend_https_api(): void
    {
        config()->set('services.resend.key', 're_test_key');
        config()->set('services.resend.from_address', 'noreply@example.test');
        config()->set('services.resend.from_name', 'TouchNRelief');
        Http::fake([
            'api.resend.com/emails' => Http::response(['id' => 'email_test_123'], 200),
        ]);
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status');

        Http::assertSent(function ($request) use ($user): bool {
            $payload = $request->data();

            return $request->url() === 'https://api.resend.com/emails'
                && $request->hasHeader('Authorization', 'Bearer re_test_key')
                && $payload['to'] === [$user->email]
                && str_contains((string) $payload['html'], 'Reset your password')
                && str_contains((string) $payload['html'], '/reset-password/');
        });
    }

    public function test_resend_api_failure_returns_to_form_instead_of_server_error(): void
    {
        config()->set('services.resend.key', 're_test_key');
        config()->set('services.resend.from_address', 'noreply@example.test');
        Http::fake([
            'api.resend.com/emails' => Http::response(['message' => 'Rejected'], 422),
        ]);
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email');
    }
}
