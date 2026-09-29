<?php

namespace Tests\Feature;

use App\Models\SpaBooking;
use App\Models\User;
use App\Services\TherapistCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_pages_redirect_staff_to_their_own_dashboard(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $receptionist = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);

        $this->actingAs($admin)
            ->get(route('booking.index'))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($receptionist)
            ->get(route('profile.edit'))
            ->assertRedirect(route('receptionist.dashboard'));
    }

    public function test_customer_can_still_access_customer_pages(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_USER]);

        $this->actingAs($customer)
            ->get(route('booking.index'))
            ->assertOk();
    }

    public function test_onboarding_rejects_protocol_relative_return_url(): void
    {
        $customer = User::factory()->create([
            'role' => User::ROLE_USER,
            'sex' => User::SEX_MALE,
        ]);

        $this->actingAs($customer)
            ->post(route('onboarding.store'), [
                'therapist_gender_preference' => User::THERAPIST_PREF_NO,
                'pressure_preference' => User::PRESSURE_MEDIUM,
                'return_to' => '//example.com/collect-session',
            ])
            ->assertRedirect(route('landing'));
    }

    public function test_therapist_session_count_uses_completed_bookings(): void
    {
        $catalog = app(TherapistCatalog::class);
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $therapist = collect($catalog->forLanding())->firstWhere('name', 'Liza Reyes');
        $this->assertSame('0', $therapist['sessions']);

        SpaBooking::query()->create([
            'user_id' => $customer->id,
            'client_name' => 'Completed Client',
            'service_name' => 'Swedish Massage',
            'therapist_name' => 'Liza Reyes',
            'booking_date' => now()->toDateString(),
            'time_slot' => '10:00 AM',
            'session_status' => SpaBooking::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
        SpaBooking::query()->create([
            'user_id' => $customer->id,
            'client_name' => 'Pending Client',
            'service_name' => 'Swedish Massage',
            'therapist_name' => 'Liza Reyes',
            'booking_date' => now()->addDay()->toDateString(),
            'time_slot' => '11:00 AM',
        ]);

        $therapist = collect($catalog->forLanding())->firstWhere('name', 'Liza Reyes');

        $this->assertSame('1', $therapist['sessions']);
    }
}
