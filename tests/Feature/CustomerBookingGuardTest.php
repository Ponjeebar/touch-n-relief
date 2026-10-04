<?php

namespace Tests\Feature;

use App\Models\SpaBooking;
use App\Models\User;
use App\Services\BookingCancellationService;
use App\Services\BookingRefundService;
use App\Services\BookingSlotService;
use App\Services\CustomerBookingPolicy;
use App\Support\PaymentMethodCatalog;
use Carbon\Carbon;
use Database\Seeders\SpaServiceSeeder;
use Database\Seeders\TherapistSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerBookingGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SpaServiceSeeder::class, TherapistSeeder::class]);
        app(BookingSlotService::class)->seedDefaults();
        app(BookingSlotService::class)->attachDefaultSlotsForServicesWithoutSchedule();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_customer_slots_require_thirty_minutes_lead_while_staff_slots_do_not(): void
    {
        Carbon::setTestNow('2026-10-05 07:51:00 PM');
        $slots = app(BookingSlotService::class);
        $times = ['08:00 PM', '08:30 PM'];

        $this->assertSame([], $slots->pastSlotLabelsForDate('2026-10-05', $times));
        $this->assertSame(
            ['08:00 PM'],
            $slots->pastSlotLabelsForDate('2026-10-05', $times, minimumLeadMinutes: 30),
        );
        $this->assertTrue($slots->isBeforeMinimumLeadTime('2026-10-05', '08:00 PM', 30));
        $this->assertFalse($slots->isBeforeMinimumLeadTime('2026-10-05', '08:30 PM', 30));
    }

    public function test_customer_availability_and_submission_enforce_the_lead_time(): void
    {
        Carbon::setTestNow('2026-10-05 07:51:00 PM');
        config()->set('services.paymongo.secret_key', 'sk_test_booking_guard');
        $customer = User::factory()->create(['role' => User::ROLE_USER]);

        $availability = $this->actingAs($customer)
            ->getJson(route('booking.availability', [
                'service' => 'Thai Massage',
                'therapist' => 'Juan dela Cruz',
                'booking_date' => '2026-10-05',
            ]))
            ->assertOk();

        $this->assertContains('8:00 PM', $availability->json('past_slots'));
        $this->assertNotContains('8:30 PM', $availability->json('past_slots'));

        $this->from(route('booking.index'))
            ->post(route('booking.store'), [
                'service' => 'Thai Massage',
                'therapist' => 'Juan dela Cruz',
                'booking_date' => '2026-10-05',
                'time_slot' => '08:00 PM',
                'payment_type' => PaymentMethodCatalog::TYPE_FULL,
                'no_show_policy_accepted' => '1',
            ])
            ->assertRedirect(route('booking.index'))
            ->assertSessionHasErrors('time_slot', errorBag: 'booking');

        $this->assertDatabaseCount('spa_bookings', 0);
    }

    public function test_customer_cannot_create_a_second_active_unpaid_reservation(): void
    {
        Carbon::setTestNow('2026-10-05 10:00:00 AM');
        config()->set('services.paymongo.secret_key', 'sk_test_booking_guard');
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $this->pendingBooking($customer, now()->subMinutes(5));

        $this->actingAs($customer)
            ->from(route('booking.index'))
            ->post(route('booking.store'), [
                'service' => 'Thai Massage',
                'therapist' => 'Juan dela Cruz',
                'booking_date' => now()->addDay()->toDateString(),
                'time_slot' => '08:00 PM',
                'payment_type' => PaymentMethodCatalog::TYPE_DOWNPAYMENT,
                'no_show_policy_accepted' => '1',
            ])
            ->assertRedirect(route('booking.index'))
            ->assertSessionHasErrors('payment', errorBag: 'booking');

        $this->assertDatabaseCount('spa_bookings', 1);
    }

    public function test_three_recent_expired_holds_create_a_one_hour_cooldown(): void
    {
        Carbon::setTestNow('2026-10-05 10:00:00 AM');
        $customer = User::factory()->create(['role' => User::ROLE_USER]);

        foreach ([31, 46, 61] as $minutesAgo) {
            $this->pendingBooking($customer, now()->subMinutes($minutesAgo));
        }

        $policy = app(CustomerBookingPolicy::class);

        try {
            $policy->assertMayCreatePaymentHold($customer);
            $this->fail('The repeated expired-hold cooldown was not enforced.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Try again after 10:44 AM', $exception->errors()['payment'][0]);
        }

        Carbon::setTestNow('2026-10-05 10:45:00 AM');
        $policy->assertMayCreatePaymentHold($customer);
        $this->addToAssertionCount(1);
    }

    public function test_customer_can_cancel_an_active_unpaid_reservation_without_a_refund(): void
    {
        Carbon::setTestNow('2026-10-05 10:00:00 AM');
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = $this->pendingBooking($customer, now()->subMinutes(5));

        $this->assertTrue(app(BookingCancellationService::class)->canCancel($booking));

        $this->actingAs($customer)
            ->post(route('booking.cancel', $booking), [
                'cancellation_reason' => 'schedule_conflict',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $booking->refresh();
        $this->assertNotNull($booking->cancelled_at);
        $this->assertSame(SpaBooking::STATUS_CANCELLED, $booking->session_status);
        $this->assertSame(BookingRefundService::STATUS_NOT_APPLICABLE, $booking->refund_status);
        $this->assertSame(PaymentMethodCatalog::STATUS_PENDING, $booking->payment_status);
    }

    private function pendingBooking(User $customer, Carbon $createdAt): SpaBooking
    {
        $booking = SpaBooking::query()->create([
            'user_id' => $customer->id,
            'client_name' => $customer->name,
            'booking_source' => SpaBooking::SOURCE_ONLINE,
            'service_name' => 'Thai Massage',
            'therapist_name' => 'Juan dela Cruz',
            'booking_date' => now()->addDay()->toDateString(),
            'time_slot' => '08:00 PM',
            'duration_minutes' => 60,
            'amount' => 110,
            'payment_method' => PaymentMethodCatalog::METHOD_PAYMONGO,
            'payment_type' => PaymentMethodCatalog::TYPE_DOWNPAYMENT,
            'payment_amount' => 55,
            'payment_status' => PaymentMethodCatalog::STATUS_PENDING,
            'session_status' => SpaBooking::STATUS_CONFIRMED,
        ]);

        $booking->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->saveQuietly();

        return $booking->fresh();
    }
}
