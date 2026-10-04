<?php

namespace Tests\Feature;

use App\Models\SpaService;
use App\Models\User;
use App\Services\BookingSlotService;
use App\Services\SpaServiceCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageMembershipCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_keeps_services_and_shows_packages_and_membership_separately(): void
    {
        $this->get(route('landing'))
            ->assertOk()
            ->assertViewHas('services', fn (array $services): bool => collect($services)
                ->every(fn (array $service): bool => ($service['offering_type'] ?? 'service') === 'service'))
            ->assertViewHas('packages', fn (array $packages): bool => count($packages) === 3)
            ->assertSee('Buenos Touche Service Menu')
            ->assertSee('THERA Packages')
            ->assertSee('THERA #1')
            ->assertSee('Buenos Touché Membership')
            ->assertSee('Included treatments')
            ->assertSee('Member rate')
            ->assertSee('Regular rate')
            ->assertSee('days validity')
            ->assertSee('aria-labelledby="packages-heading"', false)
            ->assertSee('PHP 499.00');
    }

    public function test_customer_booking_page_lists_packages_in_their_own_group(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $slots = app(BookingSlotService::class);
        $slots->seedDefaults();
        $slots->attachDefaultSlotsForService('THERA #2');

        $this->actingAs($customer)
            ->get(route('booking.index', ['service' => 'THERA #2']))
            ->assertOk()
            ->assertSee('Individual services')
            ->assertSee('Packages')
            ->assertSee('THERA #2')
            ->assertSee('PHP 599.00');

        $this->assertSame(90, app(SpaServiceCatalog::class)->durationMinutesFor('THERA #2'));
        $this->assertNotEmpty($slots->slotMapByService()['THERA #2'] ?? []);
    }

    public function test_booking_page_back_link_uses_the_correct_role_destination(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($customer)
            ->get(route('booking.index'))
            ->assertOk()
            ->assertSee('Back to home')
            ->assertSee('href="'.route('landing').'"', false);

        $this->actingAs($admin)
            ->get(route('booking.index'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_booking_page_renders_the_guided_step_before_javascript_initializes(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_USER]);

        $this->actingAs($customer)
            ->get(route('booking.index'))
            ->assertOk()
            ->assertSee('class="booking-grid booking-mobile-wizard-ready"', false)
            ->assertSee('data-mobile-step="date"', false)
            ->assertSee('booking-panel-schedule is-mobile-active', false);

        $this->actingAs($customer)
            ->get(route('booking.index', ['date' => now()->addDay()->toDateString()]))
            ->assertOk()
            ->assertSee('data-mobile-step="service"', false)
            ->assertSee('booking-panel-service is-mobile-active', false)
            ->assertSee('aria-disabled="false"', false);
    }

    public function test_staff_can_create_a_package_without_turning_it_into_an_individual_service(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->post(route('services.store'), [
            'name' => 'Recovery Duo',
            'offering_type' => 'package',
            'price_amount' => 900,
            'member_price_amount' => 800,
            'duration_minutes' => 90,
            'best_for' => 'Recovery',
            'description' => 'A two-part recovery package.',
            'inclusions' => 'Foot reflexology + Sports massage',
        ])->assertRedirect(route('services.index'));

        $this->assertDatabaseHas('spa_services', [
            'name' => 'Recovery Duo',
            'offering_type' => 'package',
            'price_amount' => 900,
            'member_price_amount' => 800,
        ]);

        $this->assertSame(0, SpaService::query()->where('name', 'Recovery Duo')->where('offering_type', 'service')->count());
    }
}
