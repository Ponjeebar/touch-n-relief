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
        $response = $this->get(route('landing'))
            ->assertOk()
            ->assertSee('Buenos Touche Service Menu')
            ->assertSee('THERA Packages')
            ->assertSee('THERA #1')
            ->assertSee('Buenos Touché Membership')
            ->assertSee('PHP 499.00');

        $html = $response->getContent();
        $serviceMenu = substr(
            $html,
            strpos($html, '<section class="services"'),
            strpos($html, '<section class="package-membership"') - strpos($html, '<section class="services"'),
        );
        $this->assertStringNotContainsString('THERA #1', $serviceMenu);
    }

    public function test_customer_booking_page_lists_packages_in_their_own_group(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_USER]);

        $this->actingAs($customer)
            ->get(route('booking.index', ['service' => 'THERA #2']))
            ->assertOk()
            ->assertSee('Individual services')
            ->assertSee('Packages')
            ->assertSee('THERA #2')
            ->assertSee('PHP 599.00');

        $this->assertSame(90, app(SpaServiceCatalog::class)->durationMinutesFor('THERA #2'));
        $this->assertNotEmpty(app(BookingSlotService::class)->slotMapByService()['THERA #2'] ?? []);
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
