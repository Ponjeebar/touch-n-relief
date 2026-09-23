<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\SpaBooking;
use App\Models\User;
use App\Services\BookingCancellationService;
use App\Services\SiteSettingsService;
use App\Services\SpaSessionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_customer_cancellation_uses_the_cms_cutoff(): void
    {
        Carbon::setTestNow('2026-09-21 09:00:00');
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = $this->booking($customer, '2026-09-23', '10:00 AM');
        $service = app(BookingCancellationService::class);

        SiteSetting::put(SiteSettingsService::KEY_CANCELLATION_CUTOFF_HOURS, '24');
        $this->assertTrue($service->canCancel($booking));

        SiteSetting::put(SiteSettingsService::KEY_CANCELLATION_CUTOFF_HOURS, '72');
        $this->assertFalse($service->canCancel($booking));
    }

    public function test_third_no_show_bans_customer_account(): void
    {
        Carbon::setTestNow('2026-09-21 12:00:00');
        $staff = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);
        $customer = User::factory()->create(['role' => User::ROLE_USER]);

        foreach (['2026-09-19', '2026-09-20', '2026-09-21'] as $date) {
            $booking = $this->booking($customer, $date, '09:00 AM');
            $this->actingAs($staff)
                ->patch(route('appointments.no-show', $booking))
                ->assertRedirect();
        }

        $this->assertSame(3, SpaBooking::query()->where('user_id', $customer->id)->where('session_status', SpaBooking::STATUS_NO_SHOW)->count());
        $this->assertNotNull($customer->fresh()->banned_at);
    }

    public function test_dashboard_counts_all_future_active_appointments_as_upcoming(): void
    {
        Carbon::setTestNow('2026-09-21 12:00:00');
        $customer = User::factory()->create(['role' => User::ROLE_USER]);

        $this->booking($customer, '2026-09-21', '01:00 PM');
        $this->booking($customer, '2026-09-23', '10:00 AM');
        $this->booking($customer, '2026-09-21', '09:00 AM');
        $this->booking($customer, '2026-09-24', '10:00 AM')->update([
            'cancelled_at' => now(),
            'session_status' => SpaBooking::STATUS_CANCELLED,
        ]);

        $snapshot = app(SpaSessionService::class)->dashboardSnapshot();

        $this->assertSame(2, $snapshot['upcoming_appointments']);
        $this->assertSame(2, $snapshot['appointments_today']);
    }

    private function booking(User $customer, string $date, string $time): SpaBooking
    {
        return SpaBooking::create([
            'user_id' => $customer->id,
            'client_name' => $customer->name,
            'service_name' => 'Swedish Massage',
            'booking_date' => $date,
            'time_slot' => $time,
            'duration_minutes' => 60,
            'session_status' => SpaBooking::STATUS_CONFIRMED,
        ]);
    }
}
