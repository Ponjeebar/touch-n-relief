<?php

namespace Tests\Feature;

use App\Models\SpaBooking;
use App\Models\User;
use App\Services\PaymongoService;
use App\Support\PaymentMethodCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPaymentRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_unpaid_online_booking_is_shown_as_payment_pending_with_continue_button(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = $this->pendingBooking($customer);

        $this->actingAs($customer)->get(route('landing'))
            ->assertOk()
            ->assertSee('Payment pending')
            ->assertSee('Continue payment')
            ->assertSee(route('booking.payment.retry', $booking), false)
            ->assertDontSee('Confirmed booking');
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

    public function test_customer_cannot_continue_another_customers_payment(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_USER]);
        $otherCustomer = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = $this->pendingBooking($owner);

        $this->actingAs($otherCustomer)
            ->post(route('booking.payment.retry', $booking))
            ->assertForbidden();
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
