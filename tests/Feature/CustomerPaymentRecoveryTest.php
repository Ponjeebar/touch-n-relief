<?php

namespace Tests\Feature;

use App\Models\SpaBooking;
use App\Models\User;
use App\Services\BookingCancellationService;
use App\Services\BookingRefundService;
use App\Services\BookingRescheduleService;
use App\Services\PaymongoService;
use App\Services\SpaSessionService;
use App\Support\PaymentMethodCatalog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPaymentRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_unpaid_online_booking_is_shown_as_payment_pending_with_continue_button(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = $this->pendingBooking($customer);

        $this->actingAs($customer)->get(route('landing'))
            ->assertOk()
            ->assertSee('Payment pending')
            ->assertSee('Continue payment')
            ->assertSee(route('booking.payment.retry', $booking), false)
            ->assertDontSee(route('booking.cancel', $booking), false)
            ->assertDontSee(route('booking.reschedule', $booking), false)
            ->assertDontSee('Confirmed booking');

        $this->assertFalse(app(BookingCancellationService::class)->canCancel($booking));
        $this->assertFalse(app(BookingRescheduleService::class)->canReschedule($booking));
    }

    public function test_customer_can_start_a_new_checkout_for_their_pending_booking(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = $this->pendingBooking($customer);

        $this->mock(PaymongoService::class, function ($mock) use ($booking): void {
            $mock->shouldReceive('startCustomerBookingCheckout')
                ->once()
                ->withArgs(fn (SpaBooking $candidate): bool => $candidate->is($booking))
                ->andReturn('https://checkout.paymongo.com/customer-retry');
        });

        $this->actingAs($customer)
            ->post(route('booking.payment.retry', $booking))
            ->assertRedirect('https://checkout.paymongo.com/customer-retry');
    }

    public function test_customer_gets_a_new_checkout_when_saved_session_has_an_untrusted_url(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = $this->pendingBooking($customer);
        $booking->forceFill(['paymongo_checkout_session_id' => 'cs_old'])->save();

        $this->mock(PaymongoService::class, function ($mock) use ($booking): void {
            $mock->shouldReceive('retrieveCheckoutSession')
                ->once()
                ->with('cs_old')
                ->andReturn([
                    'id' => 'cs_old',
                    'attributes' => [
                        'checkout_url' => 'https://dashboard.heroku.com/apps/buenostouche/resources',
                        'status' => 'active',
                    ],
                ]);
            $mock->shouldReceive('isCheckoutSessionPaid')->once()->andReturnFalse();
            $mock->shouldReceive('checkoutSessionHasUsablePaymentMethods')->once()->andReturnTrue();
            $mock->shouldReceive('isCheckoutUrl')->once()->andReturnFalse();
            $mock->shouldReceive('startCustomerBookingCheckout')
                ->once()
                ->withArgs(fn (SpaBooking $candidate): bool => $candidate->is($booking))
                ->andReturn('https://checkout.paymongo.com/customer-replacement');
        });

        $this->actingAs($customer)
            ->post(route('booking.payment.retry', $booking))
            ->assertRedirect('https://checkout.paymongo.com/customer-replacement');
    }

    public function test_customer_gets_a_new_checkout_when_saved_session_has_no_usable_payment_method(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = $this->pendingBooking($customer);
        $booking->forceFill(['paymongo_checkout_session_id' => 'cs_bad_methods'])->save();

        $this->mock(PaymongoService::class, function ($mock) use ($booking): void {
            $mock->shouldReceive('retrieveCheckoutSession')->once()->with('cs_bad_methods')->andReturn([
                'id' => 'cs_bad_methods',
                'attributes' => [
                    'checkout_url' => 'https://checkout.paymongo.com/no-methods',
                    'payment_method_types' => ['gcash qrph'],
                    'status' => 'active',
                ],
            ]);
            $mock->shouldReceive('isCheckoutSessionPaid')->once()->andReturnFalse();
            $mock->shouldReceive('checkoutSessionHasUsablePaymentMethods')->once()->andReturnFalse();
            $mock->shouldReceive('startCustomerBookingCheckout')
                ->once()
                ->withArgs(fn (SpaBooking $candidate): bool => $candidate->is($booking))
                ->andReturn('https://checkout.paymongo.com/replacement-methods');
        });

        $this->actingAs($customer)
            ->post(route('booking.payment.retry', $booking))
            ->assertRedirect('https://checkout.paymongo.com/replacement-methods');
    }

    public function test_customer_cannot_continue_another_customers_payment(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_USER]);
        $otherCustomer = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = $this->pendingBooking($owner);

        $this->actingAs($otherCustomer)
            ->post(route('booking.payment.retry', $booking))
            ->assertForbidden();
    }

    public function test_expired_payment_hold_cannot_be_continued(): void
    {
        Carbon::setTestNow('2026-09-24 09:00:00');
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = $this->pendingBooking($customer);
        $booking->forceFill([
            'created_at' => now()->subMinutes(15),
            'updated_at' => now()->subMinutes(15),
        ])->saveQuietly();

        $this->mock(PaymongoService::class, function ($mock): void {
            $mock->shouldNotReceive('startCustomerBookingCheckout');
        });

        $this->actingAs($customer)
            ->from(route('landing'))
            ->post(route('booking.payment.retry', $booking))
            ->assertRedirect(route('landing'))
            ->assertSessionHasErrors('payment');

        $row = app(SpaSessionService::class)->toUserTransactionRow($booking->fresh());
        $this->assertSame('Payment hold expired', $row['status']);
        $this->assertFalse($row['can_resume_payment']);
    }

    public function test_late_payment_is_cancelled_and_refunded_when_released_slot_was_taken(): void
    {
        Carbon::setTestNow('2026-09-24 09:30:00');
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = $this->pendingBooking($customer);
        $booking->forceFill([
            'paymongo_checkout_session_id' => 'cs_expired_hold',
            'created_at' => now()->subMinutes(16),
            'updated_at' => now()->subMinutes(16),
        ])->saveQuietly();

        SpaBooking::create([
            'user_id' => User::factory()->create(['role' => User::ROLE_USER])->id,
            'client_name' => 'Second Customer',
            'booking_source' => SpaBooking::SOURCE_ONLINE,
            'service_name' => $booking->service_name,
            'therapist_name' => $booking->therapist_name,
            'booking_date' => $booking->booking_date,
            'time_slot' => $booking->time_slot,
            'duration_minutes' => $booking->duration_minutes,
            'payment_method' => PaymentMethodCatalog::CHANNEL_GCASH,
            'payment_status' => PaymentMethodCatalog::STATUS_PAID,
            'session_status' => SpaBooking::STATUS_CONFIRMED,
        ]);

        $session = ['id' => 'cs_expired_hold'];
        $this->mock(PaymongoService::class, function ($mock) use ($session): void {
            $mock->shouldReceive('retrieveCheckoutSession')->once()->with('cs_expired_hold')->andReturn($session);
            $mock->shouldReceive('isCheckoutSessionPaid')->once()->with($session)->andReturnTrue();
            $mock->shouldReceive('extractPaidPaymentIdFromSession')->once()->with($session)->andReturn('pay_late');
            $mock->shouldReceive('extractPaymentChannelFromSession')->once()->with($session)->andReturn('gcash');
        });
        $this->mock(BookingRefundService::class, function ($mock): void {
            $mock->shouldReceive('processRefund')->once()->andReturnUsing(function (SpaBooking $booking): SpaBooking {
                $booking->forceFill([
                    'payment_status' => PaymentMethodCatalog::STATUS_REFUNDED,
                    'refund_status' => BookingRefundService::STATUS_PROCESSED,
                ])->saveQuietly();

                return $booking->fresh();
            });
        });

        $this->actingAs($customer)
            ->from(route('landing'))
            ->post(route('booking.payment.retry', $booking))
            ->assertRedirect(route('landing'))
            ->assertSessionHasErrors('payment');

        $booking->refresh();
        $this->assertNotNull($booking->cancelled_at);
        $this->assertSame(SpaBooking::STATUS_CANCELLED, $booking->session_status);
        $this->assertSame(PaymentMethodCatalog::STATUS_REFUNDED, $booking->payment_status);
    }

    public function test_late_payment_is_confirmed_when_released_slot_is_still_available(): void
    {
        Carbon::setTestNow('2026-09-24 09:30:00');
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = $this->pendingBooking($customer);
        $booking->forceFill([
            'paymongo_checkout_session_id' => 'cs_late_available',
            'created_at' => now()->subMinutes(16),
            'updated_at' => now()->subMinutes(16),
        ])->saveQuietly();

        $session = ['id' => 'cs_late_available'];
        $this->mock(PaymongoService::class, function ($mock) use ($session): void {
            $mock->shouldReceive('retrieveCheckoutSession')->once()->with('cs_late_available')->andReturn($session);
            $mock->shouldReceive('isCheckoutSessionPaid')->once()->with($session)->andReturnTrue();
            $mock->shouldReceive('extractPaidPaymentIdFromSession')->once()->with($session)->andReturn('pay_late_available');
            $mock->shouldReceive('extractPaymentChannelFromSession')->once()->with($session)->andReturn('gcash');
        });
        $this->mock(BookingRefundService::class, function ($mock): void {
            $mock->shouldNotReceive('processRefund');
        });

        $this->actingAs($customer)
            ->post(route('booking.payment.retry', $booking))
            ->assertSessionHas('status', 'Your payment is confirmed.');

        $booking->refresh();
        $this->assertNull($booking->cancelled_at);
        $this->assertSame(PaymentMethodCatalog::STATUS_PAID, $booking->payment_status);
        $this->assertSame('pay_late_available', $booking->payment_transaction_id);
    }

    private function pendingBooking(User $customer): SpaBooking
    {
        return SpaBooking::create([
            'user_id' => $customer->id,
            'client_name' => $customer->name,
            'booking_source' => SpaBooking::SOURCE_ONLINE,
            'service_name' => 'Thai Massage',
            'therapist_name' => 'Juan dela Cruz',
            'booking_date' => now()->addDay()->toDateString(),
            'time_slot' => '8:00 PM',
            'amount' => 110,
            'payment_method' => PaymentMethodCatalog::METHOD_PAYMONGO,
            'payment_type' => PaymentMethodCatalog::TYPE_DOWNPAYMENT,
            'payment_amount' => 55,
            'payment_status' => PaymentMethodCatalog::STATUS_PENDING,
            'session_status' => SpaBooking::STATUS_CONFIRMED,
        ]);
    }
}
