<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTourTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_replay_the_landing_page_tour_while_guests_cannot(): void
    {
        $this->get(route('landing'))
            ->assertOk()
            ->assertDontSee('data-start-customer-tour', false)
            ->assertDontSee('driver.js@1.8.0', false);

        $customer = User::factory()->create([
            'role' => User::ROLE_USER,
            'profile_completed_at' => now(),
        ]);

        $this->actingAs($customer)
            ->get(route('landing'))
            ->assertOk()
            ->assertSee('data-start-customer-tour', false)
            ->assertSee('driver.js@1.8.0', false)
            ->assertSee('data-customer-tour-auto-start="0"', false);
    }

    public function test_completing_wellness_onboarding_starts_the_tour_on_the_next_landing_visit(): void
    {
        $customer = User::factory()->create([
            'role' => User::ROLE_USER,
            'sex' => User::SEX_MALE,
            'profile_completed_at' => null,
        ]);

        $this->actingAs($customer)
            ->post(route('onboarding.store'), [
                'therapist_gender_preference' => User::THERAPIST_PREF_NO,
                'pressure_preference' => User::PRESSURE_MEDIUM,
            ])
            ->assertRedirect(route('landing'))
            ->assertSessionHas('customer_tour_pending', true);

        $this->actingAs($customer)
            ->get(route('landing'))
            ->assertOk()
            ->assertSee('data-customer-tour-auto-start="1"', false);

        $this->actingAs($customer)
            ->get(route('landing'))
            ->assertOk()
            ->assertSee('data-customer-tour-auto-start="0"', false);
    }
}
