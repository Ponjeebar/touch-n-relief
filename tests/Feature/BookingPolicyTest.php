<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\SpaBooking;
use App\Models\Therapist;
use App\Models\User;
use App\Services\BookingCancellationService;
use App\Services\BookingSlotService;
use App\Services\SiteSettingsService;
use App\Services\SpaSessionService;
use App\Services\TherapistAvailabilityService;
use App\Support\PaymentMethodCatalog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
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

    public function test_same_day_past_slots_are_disabled_and_rejected(): void
    {
        Carbon::setTestNow('2026-09-23 07:00:00 PM');
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $slots = app(BookingSlotService::class);

        $this->assertSame(
            ['08:00 AM', '06:30 PM'],
            $slots->pastSlotLabelsForDate('2026-09-23', ['08:00 AM', '06:30 PM', '07:30 PM']),
        );
        $this->assertSame([], $slots->pastSlotLabelsForDate('2026-09-24', ['08:00 AM']));

        try {
            $slots->assertBookingAvailable(
                $customer->id,
                'Swedish Massage',
                'Test Therapist',
                '2026-09-23',
                '08:00 AM',
                60,
            );
            $this->fail('A past time slot was accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'That time has already passed. Please choose a later time or another date.',
                $exception->errors()['time_slot'][0],
            );
        }
    }

    public function test_session_can_only_start_during_its_first_ten_minutes(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = $this->booking($customer, '2026-09-24', '02:30 PM');
        $booking->update([
            'amount' => 100,
            'payment_amount' => 100,
            'payment_status' => PaymentMethodCatalog::STATUS_PAID,
        ]);
        $sessions = app(SpaSessionService::class);

        Carbon::setTestNow('2026-09-24 02:29:59 PM');
        $this->assertFalse($sessions->canStart($booking));

        Carbon::setTestNow('2026-09-24 02:30:00 PM');
        $this->assertTrue($sessions->canStart($booking));

        Carbon::setTestNow('2026-09-24 02:39:59 PM');
        $this->assertTrue($sessions->canStart($booking));

        Carbon::setTestNow('2026-09-24 02:40:00 PM');
        $this->assertFalse($sessions->canStart($booking));
        $sessions->autoCancelMissedAppointments();
        $this->assertSame(SpaBooking::STATUS_CANCELLED, $booking->fresh()->session_status);
    }

    public function test_busy_status_comes_from_an_active_session_instead_of_stored_flag(): void
    {
        Carbon::setTestNow('2026-09-24 10:05:00 AM');
        $therapist = Therapist::query()->where('name', 'Juan dela Cruz')->firstOrFail();
        $availability = app(TherapistAvailabilityService::class);

        $this->assertSame('available', $availability->effectiveStatus($therapist));

        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $this->booking($customer, '2026-09-24', '10:00 AM')->update([
            'therapist_name' => $therapist->name,
            'session_status' => SpaBooking::STATUS_IN_SESSION,
            'session_started_at' => now(),
        ]);

        $this->assertSame('busy', $availability->effectiveStatus($therapist));
    }

    public function test_therapist_gets_one_hour_rest_after_three_back_to_back_clients(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        foreach (['09:00 AM', '10:00 AM', '11:00 AM'] as $time) {
            $this->booking($customer, '2026-09-25', $time)->update([
                'therapist_name' => 'Liza Reyes',
                'duration_minutes' => 60,
            ]);
        }

        $slots = app(BookingSlotService::class);
        $this->assertTrue($slots->therapistSlotTaken('Liza Reyes', '2026-09-25', '12:00 PM', 30));
        $this->assertTrue($slots->therapistSlotTaken('Liza Reyes', '2026-09-25', '12:30 PM', 30));
        $this->assertFalse($slots->therapistSlotTaken('Liza Reyes', '2026-09-25', '01:00 PM', 30));
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
