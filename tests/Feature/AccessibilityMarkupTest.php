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
            ->assertDontSee('customer-mobile-shortcut-primary', false)
            ->assertSee('aria-controls="tnr-transactions-modal"', false)
            ->assertSee('aria-expanded="false"', false);

        $profileResponse = $this->actingAs($customer)->get(route('profile.edit'));

        $profileResponse->assertOk();
        $this->assertMatchesRegularExpression(
            '/<a\b(?=[^>]*data-customer-mobile-item="book")(?![^>]*aria-current=)[^>]*>/',
            $profileResponse->getContent()
        );
        $this->assertMatchesRegularExpression(
            '/<a\b(?=[^>]*data-customer-mobile-item="profile")(?=[^>]*aria-current="page")[^>]*>/',
            $profileResponse->getContent()
        );
    }

    public function test_staff_mobile_navigation_uses_more_sheet_and_avatar_account_menu(): void
    {
        $receptionist = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);

        $receptionistResponse = $this->actingAs($receptionist)->get(route('receptionist.dashboard'));

        $receptionistResponse->assertOk()
            ->assertDontSee('class="staff-nav-toggle"', false)
            ->assertSee('aria-label="Staff mobile shortcuts"', false)
            ->assertSee('data-staff-mobile-item="home"', false)
            ->assertSee('data-staff-mobile-item="bookings"', false)
            ->assertSee('data-staff-mobile-item="sessions"', false)
            ->assertSee('data-staff-mobile-item="clients"', false)
            ->assertSee('data-staff-mobile-item="more"', false)
            ->assertSee('data-staff-mobile-header-actions', false)
            ->assertSee('id="staff-mobile-more-menu"', false)
            ->assertSee('aria-controls="staff-mobile-more-menu"', false)
            ->assertSee('Secondary staff pages')
            ->assertSee('id="tnr-profile-theme-toggle"', false)
            ->assertSee('mobile-navigation.js?v=', false)
            ->assertSee('Receptionist');
        $this->assertSame(5, substr_count($receptionistResponse->getContent(), 'data-staff-mobile-item='));

        $mobileNavigationScript = file_get_contents(public_path('js/mobile-navigation.js'));
        $this->assertStringContainsString('setupStaffHeaderActions()', $mobileNavigationScript);
        $this->assertStringContainsString("window.matchMedia('(max-width: 1024px)')", $mobileNavigationScript);
        $this->assertStringContainsString('.main > .topbar, .main > .tt-hero-with-profile', $mobileNavigationScript);

        $mobileStyles = file_get_contents(public_path('css/staff-mobile.css'));
        $this->assertMatchesRegularExpression(
            '/\.sidebar\s*\{[^}]*overflow:\s*visible;/s',
            $mobileStyles
        );
        $this->assertStringContainsString('max-height: calc(100dvh - 88px - env(safe-area-inset-bottom));', $mobileStyles);

        $therapistResponse = $this->actingAs($receptionist)
            ->get(route('therapist-tracking.index'));

        $therapistResponse->assertOk()
            ->assertSee('therapist-tracking.css?v=', false)
            ->assertSee('mobile-navigation.js?v=', false)
            ->assertSee('data-label="Specializations"', false)
            ->assertSee('data-label="'.now()->year.' service hours"', false);

        $therapistStyles = file_get_contents(public_path('css/therapist-tracking.css'));
        $this->assertStringContainsString('.tt-table tbody tr', $therapistStyles);
        $this->assertStringContainsString('grid-template-columns: minmax(0, 1fr) auto;', $therapistStyles);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('class="staff-nav-toggle"', false)
            ->assertSee('data-staff-mobile-item="more"', false)
            ->assertSee('Receptionists')
            ->assertSee('Customers')
            ->assertSee('Reports')
            ->assertSee('Team Profiles')
            ->assertSee('Landing Page')
            ->assertSee('Administrator');
    }
}
