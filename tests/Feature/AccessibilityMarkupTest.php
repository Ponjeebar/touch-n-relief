<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessibilityMarkupTest extends TestCase
{
    use RefreshDatabase;

    public function test_receptionist_dashboard_does_not_label_a_non_form_time_slot_list(): void
    {
        $receptionist = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);

        $this->actingAs($receptionist)
            ->get(route('receptionist.dashboard'))
            ->assertOk()
            ->assertDontSee('for="tnr-reschedule-slots"', false)
            ->assertSee('id="tnr-reschedule-slots-label"', false)
            ->assertSee('aria-labelledby="tnr-reschedule-slots-label"', false)
            ->assertSee('aria-describedby="tnr-reschedule-slots-hint"', false);
    }

    public function test_customer_mobile_navigation_has_clear_destinations_and_modal_state(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_USER]);

        $response = $this->actingAs($customer)->get(route('landing'));

        $response->assertOk()
            ->assertSee('aria-label="Customer mobile shortcuts"', false)
            ->assertSee('data-customer-mobile-item="home"', false)
            ->assertSee('data-customer-mobile-item="services"', false)
            ->assertSee('data-customer-mobile-item="book"', false)
            ->assertSee('data-customer-mobile-item="appointments"', false)
            ->assertSee('data-customer-mobile-item="profile"', false)
            ->assertSee('customer-mobile-shortcut-primary', false)
            ->assertSee('aria-controls="tnr-transactions-modal"', false)
            ->assertSee('aria-expanded="false"', false);
    }
}
