<?php

namespace Tests\Feature;

use App\Models\CustomerNotification;
use App\Models\SiteSetting;
use App\Models\SpaBooking;
use App\Models\Therapist;
use App\Models\User;
use App\Services\BookingCancellationService;
use App\Services\BookingRescheduleService;
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
        $this->assertSame(3, CustomerNotification::query()
            ->where('user_id', $customer->id)
            ->where('type', CustomerNotification::TYPE_NO_SHOW)
            ->count());
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $staff->id,
            'action' => 'appointment.no_show',
        ]);
    }

    public function test_admin_can_correct_no_show_and_recalculate_account_restriction(): void
    {
        Carbon::setTestNow('2026-09-21 12:00:00');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $customer = User::factory()->create(['role' => User::ROLE_USER, 'banned_at' => now()]);

        $bookings = collect(['2026-09-18', '2026-09-19', '2026-09-20'])
            ->map(function (string $date) use ($customer): SpaBooking {
                $booking = $this->booking($customer, $date, '09:00 AM');
                $booking->update(['session_status' => SpaBooking::STATUS_NO_SHOW]);

                return $booking;
            });

        $this->actingAs($admin)
            ->patch(route('appointments.no-show.reverse', $bookings->last()))
            ->assertRedirect(route('appointments.index', ['date' => '2026-09-20']));

        $this->assertSame(SpaBooking::STATUS_CONFIRMED, $bookings->last()->fresh()->session_status);
        $this->assertNotNull($bookings->last()->fresh()->no_show_reversed_at);
        $this->assertSame(2, SpaBooking::query()
            ->where('user_id', $customer->id)
            ->where('session_status', SpaBooking::STATUS_NO_SHOW)
            ->count());
        $this->assertNull($customer->fresh()->banned_at);
        $this->assertDatabaseHas('customer_notifications', [
            'user_id' => $customer->id,
            'spa_booking_id' => $bookings->last()->id,
            'type' => CustomerNotification::TYPE_NO_SHOW_REVERSED,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'appointment.no_show_reversed',
        ]);
    }

    public function test_receptionist_cannot_correct_a_no_show(): void
    {
        $receptionist = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = $this->booking($customer, '2026-09-20', '09:00 AM');
        $booking->update(['session_status' => SpaBooking::STATUS_NO_SHOW]);

        $this->actingAs($receptionist)
            ->patch(route('appointments.no-show.reverse', $booking))
            ->assertRedirect(route('receptionist.dashboard'));

        $this->assertSame(SpaBooking::STATUS_NO_SHOW, $booking->fresh()->session_status);
        $this->assertNull($booking->fresh()->no_show_reversed_at);
    }

    public function test_customer_cannot_record_or_correct_a_no_show(): void
    {
        Carbon::setTestNow('2026-09-21 12:00:00');
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = $this->booking($customer, '2026-09-21', '09:00 AM');

        $this->actingAs($customer)
            ->patch(route('appointments.no-show', $booking))
            ->assertForbidden();
        $this->actingAs($customer)
            ->patch(route('appointments.no-show.reverse', $booking))
            ->assertForbidden();

        $this->assertSame(SpaBooking::STATUS_CONFIRMED, $booking->fresh()->session_status);
        $this->assertNull($booking->fresh()->no_show_reversed_at);
    }

    public function test_rescheduling_a_corrected_no_show_starts_a_new_attendance_cycle(): void
    {
        Carbon::setTestNow('2026-09-21 12:00:00');
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        Therapist::query()->create([
            'therapist_code' => 'RESCHEDULE-1',
            'name' => 'Liza Reyes',
            'status' => 'available',
            'work_on_off_day' => true,
            'is_active' => true,
        ]);
        app(BookingSlotService::class)->seedDefaults();
        $booking = $this->booking($customer, '2026-09-20', '09:00 AM');
        $booking->forceFill([
            'session_status' => SpaBooking::STATUS_CONFIRMED,
            'no_show_reversed_at' => now(),
        ])->save();

        app(BookingRescheduleService::class)->staffReschedule($booking, '2026-09-22', '10:00 AM');

        $booking->refresh();
        $this->assertNull($booking->no_show_reversed_at);
        $this->assertSame('2026-09-22', $booking->booking_date->format('Y-m-d'));
        $this->assertSame('10:00 AM', $booking->time_slot);
    }

    public function test_customer_must_accept_no_show_payment_policy_before_checkout(): void
    {
        Carbon::setTestNow('2026-10-04 09:00:00');
        config(['services.paymongo.secret_key' => 'sk_test_policy_validation']);
        $customer = User::factory()->create(['role' => User::ROLE_USER]);

        $this->actingAs($customer)
            ->get(route('booking.index'))
            ->assertOk()
            ->assertSee('I understand the no-show payment policy.')
            ->assertSee('name="no_show_policy_accepted"', false);

        $this->actingAs($customer)
            ->from(route('booking.index'))
            ->post(route('booking.store'), [
                'service' => 'Swedish Massage',
                'therapist' => 'Liza Reyes',
                'booking_date' => '2026-10-05',
                'time_slot' => '10:00 AM',
                'payment_type' => PaymentMethodCatalog::TYPE_FULL,
            ])
            ->assertRedirect(route('booking.index'))
            ->assertSessionHasErrors('no_show_policy_accepted', errorBag: 'booking');

        $this->assertDatabaseCount('spa_bookings', 0);
    }

    public function test_paid_no_show_history_explains_retained_payment_and_unpaid_balance(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = $this->booking($customer, '2026-10-03', '10:00 AM');
        $booking->update([
            'amount' => 100,
            'payment_amount' => 50,
            'payment_status' => PaymentMethodCatalog::STATUS_PAID,
            'session_status' => SpaBooking::STATUS_NO_SHOW,
        ]);

        $transaction = app(SpaSessionService::class)->toUserTransactionRow($booking->fresh());

        $this->assertStringContainsString('retained as a no-show fee', $transaction['no_show_payment_notice']);
        $this->assertStringContainsString('No unpaid balance will be collected', $transaction['no_show_payment_notice']);
    }

    public function test_late_appointment_requires_staff_confirmation_and_shows_the_next_count(): void
    {
        Carbon::setTestNow('2026-09-21 10:11:00');
        $receptionist = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $this->booking($customer, '2026-09-20', '09:00 AM')->update([
            'session_status' => SpaBooking::STATUS_NO_SHOW,
        ]);
        $lateBooking = $this->booking($customer, '2026-09-21', '10:00 AM');

        $this->actingAs($receptionist)
            ->get(route('appointments.index', ['date' => '2026-09-21']))
            ->assertOk()
            ->assertSee('Late')
            ->assertSee('data-open-no-show="true"', false)
            ->assertSee('data-current-no-show-count="1"', false)
            ->assertSee('data-next-no-show-count="2"', false)
            ->assertSee('data-no-show-countdown="true"', false)
            ->assertSee('Automatic no-show deadline')
            ->assertSee('Mark appointment as no-show?');

        $this->assertSame(SpaBooking::STATUS_CONFIRMED, $lateBooking->fresh()->session_status);
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
        $sessions->releaseAvailabilityBlocks();
        $this->assertSame(SpaBooking::STATUS_CONFIRMED, $booking->fresh()->session_status);

        $row = $sessions->toAppointmentRow($booking->fresh(), now(), 1);
        $this->assertSame('Late', $row['status']);
        $this->assertTrue($row['can_mark_no_show']);
        $this->assertSame(2, $row['next_no_show_count']);
        $this->assertStringContainsString('must confirm', $sessions->startEligibilityMessage($booking->fresh()));
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

    public function test_unpaid_online_booking_releases_its_slot_after_fifteen_minutes(): void
    {
        Carbon::setTestNow('2026-09-24 09:00:00');
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = SpaBooking::create([
            'user_id' => $customer->id,
            'client_name' => $customer->name,
            'booking_source' => SpaBooking::SOURCE_ONLINE,
            'service_name' => 'Swedish Massage',
            'therapist_name' => 'Liza Reyes',
            'booking_date' => '2026-09-25',
            'time_slot' => '10:00 AM',
            'duration_minutes' => 60,
            'payment_method' => PaymentMethodCatalog::METHOD_PAYMONGO,
            'payment_status' => PaymentMethodCatalog::STATUS_PENDING,
            'session_status' => SpaBooking::STATUS_CONFIRMED,
        ]);
        $slots = app(BookingSlotService::class);

        $this->assertTrue($booking->hasActivePaymentHold());
        $this->assertTrue($slots->therapistSlotTaken('Liza Reyes', '2026-09-25', '10:00 AM', 60));

        Carbon::setTestNow('2026-09-24 09:15:00');

        $this->assertTrue($booking->fresh()->isPaymentHoldExpired());
        $this->assertFalse($slots->therapistSlotTaken('Liza Reyes', '2026-09-25', '10:00 AM', 60));
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
