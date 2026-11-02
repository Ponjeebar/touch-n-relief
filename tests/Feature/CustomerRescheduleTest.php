<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\SpaBooking;
use App\Models\Therapist;
use App\Models\User;
use App\Services\BookingCancellationService;
use App\Services\BookingRescheduleService;
use App\Services\BookingSlotService;
use App\Services\PaymentLedgerService;
use App\Services\SiteSettingsService;
use Carbon\Carbon;
use Database\Seeders\SpaServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CustomerRescheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-02 08:00:00');
        $this->seed(SpaServiceSeeder::class);
        app(BookingSlotService::class)->seedDefaults();
        app(BookingSlotService::class)->attachDefaultSlotsForServicesWithoutSchedule();
        SiteSetting::put(SiteSettingsService::KEY_CANCELLATION_CUTOFF_HOURS, '24');
        foreach (['Ada', 'Bea'] as $name) {
            Therapist::query()->create(['therapist_code' => $name, 'name' => $name, 'is_active' => true, 'status' => 'available', 'work_on_off_day' => true]);
        }
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function booking(array $overrides = []): SpaBooking
    {
        $user = User::factory()->create(['role' => User::ROLE_USER]);

        return SpaBooking::query()->create(array_replace([
            'user_id' => $user->id, 'service_name' => 'Aromatherapy', 'therapist_name' => 'Ada',
            'booking_date' => '2026-11-05', 'time_slot' => '10:00 AM', 'duration_minutes' => 60,
            'amount' => 80, 'payment_amount' => 40, 'payment_type' => 'downpayment',
            'payment_method' => 'paymongo', 'payment_status' => 'paid', 'booking_source' => 'online',
            'paymongo_checkout_session_id' => 'cs_preserved', 'payment_transaction_id' => 'pay_preserved',
            'session_status' => SpaBooking::STATUS_CONFIRMED,
        ], $overrides));
    }

    private function change(array $overrides = []): array
    {
        return array_replace(['therapist' => 'Bea', 'booking_date' => '2026-11-05', 'time_slot' => '10:00 AM'], $overrides);
    }

    public function test_therapist_only_change_preserves_full_and_downpayments_without_financial_mutations(): void
    {
        foreach (['downpayment' => 40, 'full' => 80] as $type => $paid) {
            $booking = $this->booking(['payment_type' => $type, 'payment_amount' => $paid]);
            app(PaymentLedgerService::class)->recordInitialPayment($booking);
            $ledger = DB::table('payment_ledger_entries')->get()->toJson();
            $original = $booking->only(['amount', 'payment_amount', 'payment_type', 'payment_method', 'payment_status', 'balance_amount', 'paymongo_checkout_session_id', 'payment_transaction_id', 'duration_minutes', 'service_name']);
            $response = $this->actingAs($booking->user)->patchJson(route('booking.reschedule', $booking), $this->change())->assertOk();
            $response->assertJsonPath('booking.therapist_name', 'Bea');
            $this->assertSame($original, $booking->fresh()->only(array_keys($original)));
            $this->assertSame('Ada', $booking->fresh()->rescheduled_from_therapist_name);
            $this->assertFalse(app(BookingSlotService::class)->therapistSlotTaken('Ada', '2026-11-05', '10:00 AM', 60));
            $this->assertTrue(app(BookingSlotService::class)->therapistSlotTaken('Bea', '2026-11-05', '10:00 AM', 60));
            $this->assertEquals('2026-11-04 10:00:00', $booking->fresh()->cancellation_deadline_at->format('Y-m-d H:i:s'));
            $this->assertEquals(80 - $paid, $booking->fresh()->remainingBalance());
            $this->assertSame($ledger, DB::table('payment_ledger_entries')->get()->toJson());
            $this->assertDatabaseCount('booking_refunds', 0);
            $this->assertDatabaseCount('transactions', 0);
            $booking->forceFill(['session_status' => SpaBooking::STATUS_CANCELLED, 'cancelled_at' => now()])->save();
        }
        Http::assertNothingSent();
    }

    public function test_unverified_payments_and_terminal_sessions_cannot_be_rescheduled(): void
    {
        foreach ([['payment_status' => 'pending'], ['payment_status' => 'failed'], ['payment_status' => 'refunded'], ['payment_amount' => 0], ['session_status' => 'in_session'], ['session_status' => 'cancelled'], ['session_status' => 'completed'], ['session_status' => 'no_show']] as $state) {
            $booking = $this->booking($state);
            $this->actingAs($booking->user)->patchJson(route('booking.reschedule', $booking), $this->change())
                ->assertUnprocessable()->assertJsonValidationErrors('booking');
            $this->assertSame('Ada', $booking->fresh()->therapist_name);
            $this->assertNull($booking->fresh()->rescheduled_at);
        }
        $this->assertDatabaseCount('booking_refunds', 0);
        Http::assertNothingSent();
    }

    public function test_conflict_off_duty_and_customer_overlap_leave_the_original_booking_unchanged(): void
    {
        $booking = $this->booking();
        $other = $this->booking(['therapist_name' => 'Bea']);
        $this->actingAs($booking->user)->patchJson(route('booking.reschedule', $booking), $this->change())->assertUnprocessable();
        $this->assertSame('Ada', $booking->fresh()->therapist_name);
        $other->delete();
        Therapist::where('name', 'Bea')->update(['work_on_off_day' => false, 'day_off_until' => '2026-11-09']);
        $this->patchJson(route('booking.reschedule', $booking), $this->change())->assertUnprocessable();
        Therapist::where('name', 'Bea')->update(['work_on_off_day' => true]);
        $this->booking(['user_id' => $booking->user_id, 'booking_date' => '2026-11-06']);
        $this->patchJson(route('booking.reschedule', $booking), $this->change(['booking_date' => '2026-11-06']))->assertUnprocessable();
        $this->assertSame('2026-11-05', $booking->fresh()->booking_date->toDateString());
        $this->assertNull($booking->fresh()->rescheduled_at);
        $this->assertDatabaseCount('activity_logs', 0);
        $this->assertDatabaseCount('booking_refunds', 0);
    }

    public function test_availability_uses_the_target_therapist_and_saved_duration(): void
    {
        $booking = $this->booking(['duration_minutes' => 90]);
        $this->booking(['therapist_name' => 'Bea', 'time_slot' => '11:00 AM']);
        $response = $this->actingAs($booking->user)->getJson(route('booking.reschedule.availability', [$booking, 'booking_date' => '2026-11-05', 'therapist' => 'Bea']))->assertOk();
        $this->assertContains('10:00 AM', $response->json('booked_slots'));
        $this->assertSame('Ada', $booking->fresh()->therapist_name);
    }

    public function test_customer_cannot_access_another_customers_booking_or_choose_unknown_therapist(): void
    {
        $booking = $this->booking();
        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->patchJson(route('booking.reschedule', $booking), $this->change())->assertForbidden();
        $this->getJson(route('booking.reschedule.availability', [$booking, 'booking_date' => '2026-11-05']))->assertForbidden();
        $this->getJson(route('booking.cancellation-preview', $booking))->assertForbidden();
        $this->actingAs($booking->user)->patchJson(route('booking.reschedule', $booking), $this->change(['therapist' => 'Unknown']))
            ->assertUnprocessable()->assertJsonValidationErrors('therapist');
    }

    public function test_repeated_rescheduling_does_not_extend_cancellation_deadline(): void
    {
        $booking = $this->booking();
        $this->actingAs($booking->user)->patchJson(route('booking.reschedule', $booking), $this->change(['booking_date' => '2026-11-07']))->assertOk();
        $this->patchJson(route('booking.reschedule', $booking), $this->change(['booking_date' => '2026-11-09']))->assertOk();
        Carbon::setTestNow('2026-11-04 10:00:00');
        $this->postJson(route('booking.cancel', $booking), ['cancellation_reason' => 'schedule_conflict'])->assertUnprocessable();
        $this->patchJson(route('booking.reschedule', $booking), $this->change(['booking_date' => '2026-11-10']))->assertUnprocessable();
        $this->getJson(route('booking.cancellation-preview', $booking))->assertOk()->assertJsonPath('can_cancel', false);
        $this->assertDatabaseCount('booking_refunds', 0);
    }

    public function test_staff_rescheduling_after_cutoff_does_not_restore_customer_cancellation(): void
    {
        $booking = $this->booking();
        Carbon::setTestNow('2026-11-05 09:00:00');
        app(BookingRescheduleService::class)->staffReschedule($booking, '2026-11-09', '10:00 AM');
        $this->assertFalse(app(BookingCancellationService::class)->canCancel($booking->fresh()));
    }

    public function test_legacy_refund_reservations_do_not_hide_a_refundable_counter_balance(): void
    {
        $booking = $this->booking(['refund_status' => 'pending', 'refund_amount' => 80, 'balance_amount' => 40, 'balance_payment_method' => 'cash_counter', 'balance_paid_at' => now()]);
        $this->actingAs($booking->user)->getJson(route('booking.cancellation-preview', $booking))
            ->assertOk()->assertJsonPath('refund_amount', '₱40.00');
        $this->assertDatabaseCount('booking_refunds', 0);
    }

    public function test_cancellation_after_rescheduling_previews_verified_payments_and_refunds_only_once(): void
    {
        $booking = $this->booking(['payment_method' => 'cash_counter', 'paymongo_checkout_session_id' => null, 'payment_transaction_id' => null, 'balance_amount' => 40, 'balance_payment_method' => 'cash_counter', 'balance_paid_at' => now()]);
        $this->actingAs($booking->user)->patchJson(route('booking.reschedule', $booking), $this->change())->assertOk();
        $this->getJson(route('booking.cancellation-preview', $booking))->assertOk()
            ->assertJsonPath('was_rescheduled', true)->assertJsonPath('paid_amount', '₱80.00')->assertJsonPath('refund_amount', '₱80.00');
        $this->postJson(route('booking.cancel', $booking), ['cancellation_reason' => 'schedule_conflict'])->assertOk();
        $this->assertSame(80.0, (float) DB::table('booking_refunds')->sum('amount'));
        $this->assertDatabaseCount('booking_refunds', 2);
        $this->postJson(route('booking.cancel', $booking), ['cancellation_reason' => 'schedule_conflict'])->assertUnprocessable();
        $this->assertDatabaseCount('booking_refunds', 2);
        $this->assertSame('cancelled', $booking->fresh()->session_status);
        Http::assertNothingSent();
    }
}
