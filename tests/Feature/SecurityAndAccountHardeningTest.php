<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityAndAccountHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_attempts_are_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.attempt'), [
                'login' => 'missing@example.test',
                'password' => 'WrongPassword9',
            ])->assertRedirect(route('login'));
        }

        $this->post(route('login.attempt'), [
            'login' => 'missing@example.test',
            'password' => 'WrongPassword9',
        ])->assertTooManyRequests();
    }

    public function test_customer_profile_update_keeps_the_linked_customer_record_in_sync(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_USER,
            'name' => 'Old Name',
            'email' => 'old@example.test',
            'username' => 'old_name',
            'password' => 'CurrentPassword9',
        ]);
        $customer = Customer::create([
            'customer_id' => 'CUS-001',
            'full_name' => 'Old Name',
            'email' => 'old@example.test',
            'password' => 'CurrentPassword9',
        ]);

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => 'Updated Name',
            'email' => $user->email,
            'username' => 'updated_name',
            'contact_number' => '09171234567',
            'current_password' => 'CurrentPassword9',
            'password' => 'NewPassword9',
            'password_confirmation' => 'NewPassword9',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $user->refresh();
        $customer->refresh();
        $this->assertSame('Updated Name', $customer->full_name);
        $this->assertSame('old@example.test', $customer->email);
        $this->assertSame('09171234567', $customer->number);
        $this->assertTrue(Hash::check('NewPassword9', $user->password));
        $this->assertTrue(Hash::check('NewPassword9', $customer->password));
        $this->assertCount(1, $user->passwordHistories);
    }

    public function test_profile_password_uses_the_same_strong_password_rule_as_registration(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_USER,
            'password' => 'CurrentPassword9',
        ]);

        $this->actingAs($user)->from(route('profile.edit'))->put(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'current_password' => 'CurrentPassword9',
            'password' => 'alllowercase',
            'password_confirmation' => 'alllowercase',
        ])->assertRedirect(route('profile.edit'))->assertSessionHasErrors(['password'], null, 'profile');
    }

    public function test_receptionist_can_update_the_shared_profile_but_cannot_modify_services(): void
    {
        $receptionist = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);

        $this->actingAs($receptionist)->put(route('profile.update'), [
            'name' => $receptionist->name,
            'email' => $receptionist->email,
            'username' => $receptionist->username,
            'contact_number' => '09171234567',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($receptionist)->post(route('services.store'), [
            'name' => 'Unauthorized Service',
            'price_amount' => 100,
            'duration_minutes' => 60,
        ])->assertRedirect(route('receptionist.dashboard'));

        $this->assertDatabaseMissing('spa_services', ['name' => 'Unauthorized Service']);
    }

    public function test_responses_include_browser_security_headers(): void
    {
        $this->get(route('landing'))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=()')
            ->assertHeader('Content-Security-Policy', "base-uri 'self'; object-src 'none'; frame-ancestors 'none'; form-action 'self' https://checkout.paymongo.com");
    }

    public function test_registered_client_search_results_are_inserted_as_text(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);

        $this->actingAs($staff)
            ->get(route('appointments.index'))
            ->assertOk()
            ->assertSee("clientName.textContent = client.name ?? '';", false)
            ->assertDontSee('button.innerHTML = `<strong>${client.name', false);
    }
}
