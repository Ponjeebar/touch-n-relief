<?php

namespace Tests\Feature;

use App\Models\SpaBooking;
use App\Models\SpaService;
use App\Models\User;
use App\Services\BookingSlotService;
use App\Services\PaymongoService;
use App\Support\PaymentMethodCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StaffAppointmentPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_payment_choices_are_paymongo_and_counter_for_both_roles(): void
    {
        $this->assertSame(['paymongo', 'cash_counter'], PaymentMethodCatalog::staffMethodKeys());
        $this->assertSame(50.0, PaymentMethodCatalog::calculateAmount(100, 'downpayment'));
        $this->assertSame(100.0, PaymentMethodCatalog::calculateAmount(100, 'full'));

        foreach ([User::ROLE_ADMIN, User::ROLE_RECEPTIONIST] as $role) {
            $staff = User::factory()->create(['role' => $role]);
            $this->actingAs($staff)->get(route('appointments.index'))
                ->assertOk()
                ->assertSee('data-add-payment-method="paymongo"', false)
                ->assertSee('data-add-payment-method="cash_counter"', false)
                ->assertDontSee('data-add-payment-method="gcash"', false);
        }
    }

    public function test_staff_paymongo_return_confirms_payment_only_after_gateway_verification(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);
        $client = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = SpaBooking::create([
            'user_id' => $client->id,
            'service_name' => 'Massage',
            'booking_date' => now()->addDay()->toDateString(),
            'time_slot' => '10:00 AM',
            'booking_source' => SpaBooking::SOURCE_WALK_IN,
            'payment_method' => PaymentMethodCatalog::METHOD_PAYMONGO,
            'payment_type' => PaymentMethodCatalog::TYPE_DOWNPAYMENT,
            'payment_amount' => 50,
            'payment_status' => PaymentMethodCatalog::STATUS_PENDING,
            'paymongo_checkout_session_id' => 'cs_test_123',
        ]);

        $session = ['id' => 'cs_test_123'];
        $this->mock(PaymongoService::class, function ($mock) use ($session): void {
            $mock->shouldReceive('retrieveCheckoutSession')->once()->with('cs_test_123')->andReturn($session);
            $mock->shouldReceive('isCheckoutSessionPaid')->once()->with($session)->andReturn(true);
            $mock->shouldReceive('extractPaidPaymentIdFromSession')->once()->with($session)->andReturn('pay_test_123');
            $mock->shouldReceive('extractPaymentChannelFromSession')->once()->with($session)->andReturn('gcash');
        });

        $this->actingAs($staff)->get(route('appointments.paymongo.success', $booking))
            ->assertRedirect(route('appointments.index', ['date' => $booking->booking_date->format('Y-m-d')]));

        $booking->refresh();
        $this->assertSame(PaymentMethodCatalog::STATUS_PAID, $booking->payment_status);
        $this->assertSame('pay_test_123', $booking->payment_transaction_id);
        $this->assertSame(50.0, (float) $booking->payment_amount);
    }

    public function test_counter_booking_records_half_payment_for_an_existing_client(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $client = User::factory()->create(['role' => User::ROLE_USER]);
        $this->actingAs($staff)->get(route('appointments.index'))->assertOk();

        $response = $this->post(route('appointments.store'), $this->bookingInput($client, 'cash_counter', 'downpayment'));
        $response->assertRedirect();
        $this->assertFalse($response->getSession()->has('errors'));

        $booking = SpaBooking::query()->where('user_id', $client->id)->firstOrFail();
        $this->assertSame(PaymentMethodCatalog::STATUS_PAID, $booking->payment_status);
        $this->assertSame(PaymentMethodCatalog::METHOD_CASH_COUNTER, $booking->payment_method);
        $this->assertSame(round((float) $booking->amount * .5, 2), (float) $booking->payment_amount);
    }

    public function test_staff_cannot_submit_a_removed_payment_method(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $client = User::factory()->create(['role' => User::ROLE_USER]);
        $this->actingAs($staff)->get(route('appointments.index'))->assertOk();

        $this->post(route('appointments.store'), $this->bookingInput($client, 'gcash', 'full'))
            ->assertRedirect()->assertSessionHasErrors(['payment_method'], null, 'appointment');

        $this->assertDatabaseCount('spa_bookings', 0);
    }

    public function test_staff_paymongo_booking_opens_checkout_for_full_payment(): void
    {
        config()->set('services.paymongo.secret_key', 'sk_test_example');
        Http::fake(['api.paymongo.com/*' => Http::response([
            'data' => [
                'id' => 'cs_test_staff',
                'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/staff-test'],
            ],
        ], 200)]);

        $staff = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);
        $client = User::factory()->create(['role' => User::ROLE_USER]);
        $this->actingAs($staff)->get(route('appointments.index'))->assertOk();

        $this->post(route('appointments.store'), $this->bookingInput($client, 'paymongo', 'full'))
            ->assertRedirect('https://checkout.paymongo.com/staff-test');

        $booking = SpaBooking::query()->where('user_id', $client->id)->firstOrFail();
        $this->assertSame(PaymentMethodCatalog::STATUS_PENDING, $booking->payment_status);
        $this->assertSame('cs_test_staff', $booking->paymongo_checkout_session_id);
        $this->assertSame((float) $booking->amount, (float) $booking->payment_amount);
    }

    public function test_staff_can_retry_checkout_when_session_creation_failed(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);
        $client = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = SpaBooking::create([
            'user_id' => $client->id,
            'service_name' => 'Massage',
            'booking_date' => now()->addDay()->toDateString(),
            'time_slot' => '10:00 AM',
            'booking_source' => SpaBooking::SOURCE_WALK_IN,
            'payment_method' => PaymentMethodCatalog::METHOD_PAYMONGO,
            'payment_type' => PaymentMethodCatalog::TYPE_FULL,
            'payment_amount' => 100,
            'payment_status' => PaymentMethodCatalog::STATUS_PENDING,
        ]);

        $this->actingAs($client)->get(route('appointments.paymongo.retry', $booking))->assertForbidden();

        $this->mock(PaymongoService::class, function ($mock): void {
            $mock->shouldReceive('startStaffBookingCheckout')->once()
                ->andReturn('https://checkout.paymongo.com/retry-test');
        });

        $this->actingAs($staff)->get(route('appointments.paymongo.retry', $booking))
            ->assertRedirect('https://checkout.paymongo.com/retry-test');
    }

    public function test_failed_checkout_creation_keeps_the_unpaid_booking_available_for_retry(): void
    {
        config()->set('services.paymongo.secret_key', 'sk_test_example');
        Http::fake(['api.paymongo.com/*' => Http::response(['errors' => []], 500)]);

        $staff = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $client = User::factory()->create(['role' => User::ROLE_USER]);
        $this->actingAs($staff)->get(route('appointments.index'))->assertOk();

        $this->post(route('appointments.store'), $this->bookingInput($client, 'paymongo', 'downpayment'))
            ->assertRedirect();

        $booking = SpaBooking::query()->where('user_id', $client->id)->firstOrFail();
        $this->assertSame(PaymentMethodCatalog::STATUS_PENDING, $booking->payment_status);
        $this->assertNull($booking->paymongo_checkout_session_id);

        $this->get(route('appointments.index', ['date' => $booking->booking_date->format('Y-m-d')]))
            ->assertOk()->assertSee(route('appointments.paymongo.retry', $booking), false);
    }

    private function bookingInput(User $client, string $method, string $type): array
    {
        $service = SpaService::query()->where('is_active', true)->firstOrFail();
        $slots = app(BookingSlotService::class)->slotMapByService();

        return [
            'client_type' => 'registered',
            'client_user_id' => $client->id,
            'client_name' => $client->name,
            'client_email' => $client->email,
            'client_phone' => $client->contact_number,
            'service' => $service->name,
            'therapist' => '',
            'booking_date' => now()->next(1)->addWeek()->toDateString(),
            'time_slot' => $slots[$service->name][0],
            'payment_method' => $method,
            'payment_type' => $type,
        ];
    }
}
