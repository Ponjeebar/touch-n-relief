<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class RememberMeTest extends TestCase
{
    use RefreshDatabase;

    public function test_remember_me_creates_a_persistent_login_cookie_and_logout_clears_it(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_USER,
            'password' => 'CorrectPassword123!',
        ]);
        $cookieName = Auth::guard()->getRecallerName();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('name="remember"', false);

        $loginResponse = $this->post(route('login.attempt'), [
            'login' => $user->email,
            'password' => 'CorrectPassword123!',
            'remember' => '1',
        ]);

        $loginResponse->assertCookie($cookieName);

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->getRememberToken());

        $rememberCookie = collect($loginResponse->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === $cookieName);
        $this->assertNotNull($rememberCookie);
        $this->withCookie($cookieName, $rememberCookie->getValue())
            ->post(route('logout'))
            ->assertCookieExpired($cookieName);
        $this->assertGuest();
    }

    public function test_login_without_remember_me_does_not_create_a_persistent_cookie(): void
    {
        $user = User::factory()->create(['password' => 'CorrectPassword123!']);

        $this->post(route('login.attempt'), [
            'login' => $user->email,
            'password' => 'CorrectPassword123!',
        ])->assertCookieMissing(Auth::guard()->getRecallerName());

        $this->assertAuthenticatedAs($user);
    }

    public function test_staff_and_admin_cannot_create_persistent_login_cookies(): void
    {
        foreach ([User::ROLE_RECEPTIONIST, User::ROLE_ADMIN] as $role) {
            $user = User::factory()->create([
                'role' => $role,
                'password' => 'CorrectPassword123!',
            ]);
            $originalRememberToken = $user->getRememberToken();

            $this->post(route('login.attempt'), [
                'login' => $user->email,
                'password' => 'CorrectPassword123!',
                'remember' => '1',
            ])->assertCookieMissing(Auth::guard()->getRecallerName());

            $this->assertAuthenticatedAs($user);
            $this->assertSame($originalRememberToken, $user->fresh()->getRememberToken());

            Auth::logout();
        }
    }

    public function test_regular_session_cookie_expires_when_the_browser_session_ends(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_RECEPTIONIST,
            'password' => 'CorrectPassword123!',
        ]);

        $response = $this->post(route('login.attempt'), [
            'login' => $user->email,
            'password' => 'CorrectPassword123!',
        ]);

        $sessionCookie = collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));

        $this->assertTrue((bool) config('session.expire_on_close'));
        $this->assertNotNull($sessionCookie);
        $this->assertSame(0, $sessionCookie->getExpiresTime());
    }
}
