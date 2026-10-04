<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use App\Services\SiteSettingsService;
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

    public function test_completed_customer_can_replay_contextual_tours_without_automatic_startup(): void
    {
        $customer = User::factory()->create([
            'role' => User::ROLE_USER,
            'profile_completed_at' => now(),
        ]);

        $this->actingAs($customer)
            ->get(route('booking.index'))
            ->assertOk()
            ->assertSee('data-customer-tour-page="booking.create"', false)
            ->assertSee('data-customer-tour-auto-start="0"', false)
            ->assertSee('data-start-customer-tour', false)
            ->assertSee('Booking guide');

        $this->actingAs($customer)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('data-customer-tour-page="profile.edit"', false)
            ->assertSee('data-customer-tour-auto-start="0"', false)
            ->assertSee('data-tour-scope="appointments"', false)
            ->assertSee('data-start-customer-tour', false)
            ->assertSee('driver.js@1.8.0', false);
    }

    public function test_existing_receptionist_gets_manual_replay_without_automatic_tours(): void
    {
        $receptionist = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);

        $this->actingAs($receptionist)
            ->get(route('receptionist.dashboard'))
            ->assertOk()
            ->assertSee('data-customer-tour-role="receptionist"', false)
            ->assertSee('data-customer-tour-page="receptionist.dashboard"', false)
            ->assertSee('data-customer-tour-auto-start="0"', false)
            ->assertSee('data-start-customer-tour', false)
            ->assertSee('Take a tour');

        $this->actingAs($receptionist)
            ->get(route('appointments.index'))
            ->assertOk()
            ->assertSee('data-customer-tour-page="appointments.index"', false)
            ->assertSee('data-customer-tour-auto-start="0"', false)
            ->assertSee('data-start-customer-tour', false);
    }

    public function test_new_receptionist_gets_automatic_tours_during_the_first_login_session_only(): void
    {
        $receptionist = User::factory()->create([
            'role' => User::ROLE_RECEPTIONIST,
            'password' => 'CorrectPassword123!',
        ]);

        $this->post(route('login.attempt'), [
            'login' => $receptionist->email,
            'password' => 'CorrectPassword123!',
        ])->assertRedirect(route('receptionist.dashboard'))
            ->assertSessionHas('receptionist_tour_enabled', true);

        $this->get(route('receptionist.dashboard'))
            ->assertOk()
            ->assertSee('data-customer-tour-auto-start="1"', false);

        $this->get(route('appointments.index'))
            ->assertOk()
            ->assertSee('data-customer-tour-auto-start="1"', false);

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->post(route('login.attempt'), [
            'login' => $receptionist->email,
            'password' => 'CorrectPassword123!',
        ])->assertRedirect(route('receptionist.dashboard'))
            ->assertSessionMissing('receptionist_tour_enabled');

        $this->get(route('receptionist.dashboard'))
            ->assertOk()
            ->assertSee('data-customer-tour-auto-start="0"', false);
    }

    public function test_receptionist_training_covers_every_major_operational_page_with_examples(): void
    {
        $receptionist = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);

        foreach (['ongoing-sessions.index', 'completed-sessions.index', 'therapist-tracking.index', 'client-records.index'] as $routeName) {
            $this->actingAs($receptionist)
                ->get(route($routeName))
                ->assertOk()
                ->assertSee('data-customer-tour-role="receptionist"', false)
                ->assertSee('data-customer-tour-page="'.$routeName.'"', false)
                ->assertSee('data-customer-tour-auto-start="0"', false);
        }

        $tourScript = file_get_contents(public_path('js/customer-tour.js'));

        $this->assertStringContainsString("'appointments.index'", $tourScript);
        $this->assertStringContainsString("'ongoing-sessions.index'", $tourScript);
        $this->assertStringContainsString("'completed-sessions.index'", $tourScript);
        $this->assertStringContainsString("'therapist-tracking.index'", $tourScript);
        $this->assertStringContainsString("'client-records.index'", $tourScript);
        $this->assertStringContainsString('Example:', $tourScript);
        $this->assertStringNotContainsString('return staffFoundationSteps().concat(pageSteps[page]', $tourScript);
        $this->assertStringContainsString("'Appointments guide'", $tourScript);
        $this->assertStringContainsString("'Client Records guide'", $tourScript);
        $this->assertLessThan(
            strpos($tourScript, "step(['a[href*=\"/appointments\"]']"),
            strpos($tourScript, "step(['a[href*=\"/ongoing-sessions\"]']")
        );
        $this->assertStringContainsString("example.dataset.tourDemo = 'ongoing'", $tourScript);
        $this->assertStringContainsString("example.dataset.tourDemo = 'completed'", $tourScript);
        $this->assertStringContainsString('prepareTutorialExamples()', $tourScript);
        $this->assertStringContainsString('resolveVisibleSteps(buildSteps(scope))', $tourScript);
        $this->assertStringContainsString('cleanupTutorialExamples()', $tourScript);
        $this->assertStringContainsString('allowClose: false', $tourScript);
        $this->assertStringNotContainsString('allowClose: true', $tourScript);
    }

    public function test_completing_wellness_onboarding_starts_the_tour_on_the_next_landing_visit(): void
    {
        $customer = User::factory()->create([
            'role' => User::ROLE_USER,
            'password' => 'CorrectPassword123!',
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

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->post(route('login.attempt'), [
            'login' => $customer->email,
            'password' => 'CorrectPassword123!',
        ])->assertRedirect(route('landing'))
            ->assertSessionMissing('customer_tour_pending');

        $this->get(route('booking.index'))
            ->assertOk()
            ->assertSee('data-customer-tour-auto-start="0"', false)
            ->assertSee('data-start-customer-tour', false);

        $this->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('data-customer-tour-auto-start="0"', false)
            ->assertSee('data-start-customer-tour', false);
    }

    public function test_customer_tour_receives_current_payment_and_arrival_timing(): void
    {
        SiteSetting::put(SiteSettingsService::KEY_PAYMENT_HOLD_MINUTES, '9');
        SiteSetting::put(SiteSettingsService::KEY_LATE_GRACE_MINUTES, '7');
        $customer = User::factory()->create(['role' => User::ROLE_USER]);

        $this->actingAs($customer)
            ->get(route('landing'))
            ->assertOk()
            ->assertSee('data-payment-hold-minutes="9"', false)
            ->assertSee('data-late-grace-minutes="7"', false);

        $tourScript = file_get_contents(public_path('js/customer-tour.js'));
        $this->assertStringContainsString("'+paymentHoldMinutes+'-minute", $tourScript);
        $this->assertStringContainsString("'+lateGraceMinutes+'-minute", $tourScript);
        $this->assertStringNotContainsString('the 15-minute payment window', $tourScript);
        $this->assertStringNotContainsString('more than 10 minutes late', $tourScript);
    }
}
