<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as ProviderUser;
use Tests\TestCase;

class SocialAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'google-client',
            'services.google.client_secret' => 'google-secret',
            'services.google.redirect' => '/auth/google/callback',
            'services.facebook.client_id' => 'facebook-client',
            'services.facebook.client_secret' => 'facebook-secret',
            'services.facebook.redirect' => '/auth/facebook/callback',
        ]);
    }

    public function test_login_and_signup_panels_offer_google_and_facebook(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Sign in with Google')
            ->assertSee('Sign in with Facebook')
            ->assertSee('Sign up with Google')
            ->assertSee('Sign up with Facebook');
    }

    public function test_existing_customer_can_sign_in_and_link_a_verified_google_account(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        Socialite::fake('google', ProviderUser::fake([
            'id' => 'google-existing-123',
            'name' => $customer->name,
            'email' => $customer->email,
            'email_verified' => true,
        ]));

        $this->get(route('social.callback', ['provider' => 'google']))
            ->assertRedirect(route('landing'));

        $this->assertAuthenticatedAs($customer);
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $customer->id,
            'provider' => 'google',
            'provider_user_id' => 'google-existing-123',
        ]);
    }

    public function test_social_signup_collects_required_customer_details_before_creating_account(): void
    {
        Socialite::fake('google', ProviderUser::fake([
            'id' => 'google-new-456',
            'name' => 'Social Customer',
            'email' => 'social.customer@example.com',
            'email_verified' => true,
        ]));

        $this->withSession(['social_auth.intent' => 'signup'])
            ->get(route('social.callback', ['provider' => 'google']))
            ->assertRedirect(route('social.complete'));

        $this->get(route('social.complete'))
            ->assertOk()
            ->assertSee('social.customer@example.com')
            ->assertDontSee('chatbot.js');

        $this->post(route('social.store'), [
            'contact_number' => '09171234567',
            'birthday' => now()->subYears(25)->toDateString(),
            'sex' => User::SEX_FEMALE,
            'terms_accepted' => '1',
        ])->assertRedirect(route('landing'));

        $user = User::query()->where('email', 'social.customer@example.com')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertSame(User::ROLE_USER, $user->role);
        $this->assertSame('09171234567', $user->contact_number);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->profile_completed_at);
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-new-456',
        ]);
        $this->assertDatabaseHas('registrations', [
            'user_id' => $user->id,
            'email' => 'social.customer@example.com',
        ]);
    }

    public function test_social_login_cannot_link_a_staff_or_admin_account(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        Socialite::fake('facebook', ProviderUser::fake([
            'id' => 'facebook-admin-789',
            'name' => $admin->name,
            'email' => $admin->email,
        ]));

        $this->get(route('social.callback', ['provider' => 'facebook']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('social');

        $this->assertGuest();
        $this->assertDatabaseMissing('social_accounts', [
            'provider' => 'facebook',
            'provider_user_id' => 'facebook-admin-789',
        ]);
    }

    public function test_social_signup_rejects_an_existing_customer_email(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        Socialite::fake('google', ProviderUser::fake([
            'id' => 'google-existing-signup-123',
            'name' => $customer->name,
            'email' => $customer->email,
            'email_verified' => true,
        ]));

        $this->withSession(['social_auth.intent' => 'signup'])
            ->get(route('social.callback', ['provider' => 'google']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('social');

        $this->assertGuest();
        $this->assertDatabaseMissing('social_accounts', [
            'user_id' => $customer->id,
            'provider' => 'google',
        ]);
    }

    public function test_social_login_rejects_an_unregistered_email(): void
    {
        Socialite::fake('google', ProviderUser::fake([
            'id' => 'google-unregistered-login-456',
            'name' => 'Unknown Customer',
            'email' => 'unknown.customer@example.com',
            'email_verified' => true,
        ]));

        $this->withSession(['social_auth.intent' => 'login'])
            ->get(route('social.callback', ['provider' => 'google']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('social');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'unknown.customer@example.com']);
    }
}
