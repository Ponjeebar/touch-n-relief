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
            ->assertSee('data-customer-tour-role="customer"', false)
            ->assertSee('data-customer-tour-auto-start="0"', false);
    }

    public function test_customer_receives_contextual_tours_on_booking_and_profile_pages(): void
    {
        $customer = User::factory()->create([
            'role' => User::ROLE_USER,
            'profile_completed_at' => now(),
        ]);

        $this->actingAs($customer)
            ->get(route('booking.index'))
            ->assertOk()
            ->assertSee('data-customer-tour-page="booking.create"', false)
            ->assertSee('Booking guide');

        $this->actingAs($customer)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('data-customer-tour-page="profile.edit"', false)
            ->assertSee('driver.js@1.8.0', false);
    }

    public function test_receptionist_gets_first_visit_and_replay_tours_across_staff_pages(): void
    {
        $receptionist = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);

        $this->actingAs($receptionist)
            ->get(route('receptionist.dashboard'))
            ->assertOk()
            ->assertSee('data-customer-tour-role="receptionist"', false)
            ->assertSee('data-customer-tour-page="receptionist.dashboard"', false)
            ->assertSee('data-customer-tour-auto-start="1"', false)
            ->assertSee('data-start-customer-tour', false)
            ->assertSee('Take a tour');

        $this->actingAs($receptionist)
            ->get(route('appointments.index'))
            ->assertOk()
            ->assertSee('data-customer-tour-page="appointments.index"', false)
            ->assertSee('data-customer-tour-auto-start="0"', false)
            ->assertSee('data-start-customer-tour', false);
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
