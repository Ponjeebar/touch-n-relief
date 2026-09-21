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
        $user = User::factory()->create(['password' => 'CorrectPassword123!']);
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
}
