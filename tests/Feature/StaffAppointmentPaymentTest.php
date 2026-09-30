<?php

namespace Tests\Feature;

use App\Models\MembershipPlan;
use App\Models\MembershipPurchase;
use App\Models\SpaBooking;
use App\Models\SpaService;
use App\Models\User;
use App\Services\BookingRefundService;
use App\Services\BookingSlotService;
use App\Services\PaymongoService;
use App\Services\SpaSessionService;
use App\Support\PaymentMethodCatalog;
use Carbon\Carbon;
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

    public function test_unpaid_online_checkout_is_hidden_from_staff_appointments_until_payment_is_confirmed(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $date = now()->addDay()->toDateString();
        $pending = SpaBooking::create([
            'user_id' => $customer->id,
            'client_name' => 'Pending Online Customer',
            'booking_source' => SpaBooking::SOURCE_ONLINE,
            'service_name' => 'Aromatherapy (Special)',
            'therapist_name' => 'Liza Reyes',
            'booking_date' => $date,
            'time_slot' => '12:30 PM',
            'amount' => 100,
            'payment_method' => PaymentMethodCatalog::METHOD_PAYMONGO,
            'payment_type' => PaymentMethodCatalog::TYPE_FULL,
            'payment_amount' => 100,
            'payment_status' => PaymentMethodCatalog::STATUS_PENDING,
            'session_status' => SpaBooking::STATUS_CONFIRMED,
        ]);
        $paid = SpaBooking::create([
            'user_id' => $customer->id,
            'client_name' => 'Paid Online Customer',
            'booking_source' => SpaBooking::SOURCE_ONLINE,
            'service_name' => 'Swedish Massage',
            'therapist_name' => 'Angela Fernandez',
            'booking_date' => $date,
            'time_slot' => '02:00 PM',
            'amount' => 100,
            'payment_method' => PaymentMethodCatalog::CHANNEL_QRPH,
            'payment_type' => PaymentMethodCatalog::TYPE_FULL,
            'payment_amount' => 100,
            'payment_status' => PaymentMethodCatalog::STATUS_PAID,
            'session_status' => SpaBooking::STATUS_CONFIRMED,
        ]);

        foreach ([User::ROLE_ADMIN, User::ROLE_RECEPTIONIST] as $role) {
            $staff = User::factory()->create(['role' => $role]);
            $this->actingAs($staff)
                ->get(route('appointments.index', ['date' => $date]))
                ->assertOk()
                ->assertDontSee('Pending Online Customer')
                ->assertSee('Paid Online Customer');
        }

        $this->assertTrue($pending->fresh()->isAwaitingOnlinePayment());
        $this->assertFalse($paid->fresh()->isAwaitingOnlinePayment());
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

    public function test_staff_booking_uses_the_active_membership_price_for_a_registered_client(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);
        $client = User::factory()->create(['role' => User::ROLE_USER]);
        $plan = MembershipPlan::query()->firstOrFail();
        MembershipPurchase::query()->create([
            'user_id' => $client->id,
            'membership_plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'validity_days' => 365,
            'amount' => 499,
            'payment_status' => PaymentMethodCatalog::STATUS_PAID,
            'status' => MembershipPurchase::STATUS_ACTIVE,
            'paid_at' => now(),
            'starts_at' => now()->subMinute(),
            'expires_at' => now()->addYear(),
        ]);

        $this->actingAs($staff)->get(route('appointments.index'))->assertOk();
        $response = $this->post(
            route('appointments.store'),
            $this->bookingInput($client, 'cash_counter', 'full', 'THERA #2')
        );

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();
        $booking = SpaBooking::query()->where('user_id', $client->id)->firstOrFail();
        $this->assertSame(549.0, (float) $booking->amount);
        $this->assertSame(549.0, (float) $booking->payment_amount);
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

    public function test_downpayment_booking_must_be_fully_paid_before_session_can_start(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);
        $client = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = SpaBooking::create([
            'user_id' => $client->id,
            'service_name' => 'Swedish Massage',
            'booking_date' => now()->toDateString(),
            'time_slot' => '10:00 AM',
            'amount' => 100,
            'payment_method' => PaymentMethodCatalog::METHOD_PAYMONGO,
            'payment_type' => PaymentMethodCatalog::TYPE_DOWNPAYMENT,
            'payment_amount' => 50,
            'payment_status' => PaymentMethodCatalog::STATUS_PAID,
        ]);

        $this->actingAs($staff)->patch(route('appointments.start', $booking))
            ->assertRedirect(route('appointments.index', ['date' => $booking->booking_date->format('Y-m-d')]))
            ->assertSessionHas('error', 'Collect the remaining balance before starting this session.');

        $this->assertNull($booking->fresh()->session_started_at);
    }

    public function test_staff_can_collect_exact_balance_then_start_session(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);
        $client = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = SpaBooking::create([
            'user_id' => $client->id,
            'service_name' => 'Swedish Massage',
            'booking_date' => now()->toDateString(),
            'time_slot' => '10:00 AM',
            'amount' => 100,
            'payment_method' => PaymentMethodCatalog::METHOD_PAYMONGO,
            'payment_type' => PaymentMethodCatalog::TYPE_DOWNPAYMENT,
            'payment_amount' => 50,
            'payment_status' => PaymentMethodCatalog::STATUS_PAID,
        ]);

        $this->actingAs($staff)->patch(route('appointments.collect-balance', $booking), [
            'balance_payment_method' => PaymentMethodCatalog::METHOD_CASH_COUNTER,
            'balance_payment_reference' => 'OR-1001',
        ])->assertRedirect(route('appointments.index', ['date' => $booking->booking_date->format('Y-m-d')]))
            ->assertSessionHas('status');

        $booking->refresh();
        $this->assertSame(50.0, (float) $booking->balance_amount);
        $this->assertSame($staff->id, $booking->balance_collected_by);
        $this->assertSame('OR-1001', $booking->balance_payment_reference);
        $this->assertTrue($booking->isFullyPaid());

        Carbon::setTestNow($booking->booking_date->copy()->setTime(10, 5));
        $this->patch(route('appointments.start', $booking))->assertRedirect(route('ongoing-sessions.index'));
        $this->assertNotNull($booking->fresh()->session_started_at);
        Carbon::setTestNow();
    }

    public function test_booking_with_no_confirmed_payment_cannot_start_or_auto_complete(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);
        $client = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = SpaBooking::create([
            'user_id' => $client->id,
            'service_name' => 'Swedish Massage',
            'booking_date' => now()->toDateString(),
            'time_slot' => '10:00 AM',
            'duration_minutes' => 60,
            'amount' => 100,
            'payment_method' => PaymentMethodCatalog::METHOD_PAYMONGO,
            'payment_type' => PaymentMethodCatalog::TYPE_FULL,
            'payment_amount' => 100,
            'payment_status' => PaymentMethodCatalog::STATUS_PENDING,
        ]);

        $this->actingAs($staff)->patch(route('appointments.start', $booking))
            ->assertRedirect(route('appointments.index', ['date' => $booking->booking_date->format('Y-m-d')]))
            ->assertSessionHas('error', 'Collect the remaining balance before starting this session.');

        // Simulate a record created before payment enforcement existed.
        $booking->forceFill([
            'session_started_at' => now()->subHours(2),
            'session_status' => SpaBooking::STATUS_IN_SESSION,
        ])->save();

        $this->assertFalse(app(SpaSessionService::class)->autoCompleteIfExpired($booking->fresh(), now()));
        $this->assertNull($booking->fresh()->completed_at);
        $this->assertSame(SpaBooking::STATUS_IN_SESSION, $booking->fresh()->session_status);
    }

    public function test_staff_can_collect_an_outstanding_balance_on_a_legacy_completed_session(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);
        $client = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = SpaBooking::create([
            'user_id' => $client->id,
            'service_name' => 'Swedish Massage',
            'booking_date' => now()->subDay()->toDateString(),
            'time_slot' => '10:00 AM',
            'amount' => 100,
            'payment_method' => PaymentMethodCatalog::METHOD_PAYMONGO,
            'payment_type' => PaymentMethodCatalog::TYPE_DOWNPAYMENT,
            'payment_amount' => 50,
            'payment_status' => PaymentMethodCatalog::STATUS_PAID,
            'session_started_at' => now()->subDay(),
            'completed_at' => now()->subDay()->addHour(),
            'session_status' => SpaBooking::STATUS_COMPLETED,
        ]);

        $row = app(SpaSessionService::class)->toUserTransactionRow($booking->fresh());
        $this->assertSame('Payment incomplete', $row['status']);
        $this->assertTrue($row['can_collect_balance']);

        $this->actingAs($staff)->patch(route('appointments.collect-balance', $booking), [
            'balance_payment_method' => PaymentMethodCatalog::METHOD_CASH_COUNTER,
            'balance_payment_reference' => 'OR-LEGACY-1',
        ])->assertSessionHas('status');

        $booking->refresh();
        $this->assertTrue($booking->isFullyPaid());
        $this->assertSame(50.0, (float) $booking->balance_amount);
        $this->assertSame('OR-LEGACY-1', $booking->balance_payment_reference);
    }

    public function test_unconfirmed_initial_payment_cannot_have_balance_collected(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $client = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = SpaBooking::create([
            'user_id' => $client->id,
            'service_name' => 'Swedish Massage',
            'booking_date' => now()->toDateString(),
            'time_slot' => '10:00 AM',
            'amount' => 100,
            'payment_method' => PaymentMethodCatalog::METHOD_PAYMONGO,
            'payment_type' => PaymentMethodCatalog::TYPE_DOWNPAYMENT,
            'payment_amount' => 50,
            'payment_status' => PaymentMethodCatalog::STATUS_PENDING,
        ]);

        $this->actingAs($staff)->patch(route('appointments.collect-balance', $booking), [
            'balance_payment_method' => PaymentMethodCatalog::METHOD_CASH_COUNTER,
        ])->assertSessionHasErrors('balance_payment_method', null, 'balance');

        $this->assertNull($booking->fresh()->balance_paid_at);
    }

    public function test_customer_cannot_record_their_own_balance_payment(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = SpaBooking::create([
            'user_id' => $client->id,
            'service_name' => 'Swedish Massage',
            'booking_date' => now()->toDateString(),
            'time_slot' => '10:00 AM',
            'amount' => 100,
            'payment_method' => PaymentMethodCatalog::METHOD_PAYMONGO,
            'payment_type' => PaymentMethodCatalog::TYPE_DOWNPAYMENT,
            'payment_amount' => 50,
            'payment_status' => PaymentMethodCatalog::STATUS_PAID,
        ]);

        $this->actingAs($client)->patch(route('appointments.collect-balance', $booking), [
            'balance_payment_method' => PaymentMethodCatalog::METHOD_CASH_COUNTER,
        ])->assertForbidden();

        $this->assertNull($booking->fresh()->balance_paid_at);
    }

    public function test_receptionist_can_complete_a_pending_manual_refund(): void
    {
        $receptionist = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);
        $client = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = SpaBooking::create([
            'user_id' => $client->id,
            'service_name' => 'Massage',
            'booking_date' => now()->addDay()->toDateString(),
            'time_slot' => '10:00 AM',
            'amount' => 100,
            'payment_method' => PaymentMethodCatalog::METHOD_PAYMONGO,
            'payment_status' => PaymentMethodCatalog::STATUS_PAID,
            'cancelled_at' => now(),
            'refund_status' => BookingRefundService::STATUS_PENDING,
            'refund_amount' => 50,
            'refund_reference' => 'RF-PND-ABC123',
        ]);

        $this->actingAs($client)
            ->patchJson(route('appointments.refund.complete', $booking))
            ->assertForbidden();

        $this->actingAs($receptionist)
            ->patchJson(route('appointments.refund.complete', $booking))
            ->assertOk()
            ->assertJsonPath('refund_status', BookingRefundService::STATUS_PROCESSED);

        $booking->refresh();
        $this->assertSame(BookingRefundService::STATUS_PROCESSED, $booking->refund_status);
        $this->assertNotNull($booking->refunded_at);
        $this->assertStringStartsWith('RF-MAN-', (string) $booking->refund_reference);
    }

    public function test_unstarted_past_appointment_is_not_auto_completed(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = SpaBooking::create([
            'user_id' => $client->id,
            'service_name' => 'Swedish Massage',
            'booking_date' => now()->subDay()->toDateString(),
            'time_slot' => '8:00 AM',
            'duration_minutes' => 60,
            'amount' => 100,
            'payment_status' => PaymentMethodCatalog::STATUS_PENDING,
        ]);

        $this->assertFalse(app(SpaSessionService::class)->autoCompleteIfExpired($booking, now()));
        $this->assertNull($booking->fresh()->completed_at);
    }

    private function bookingInput(User $client, string $method, string $type, ?string $serviceName = null): array
    {
        $service = SpaService::query()
            ->where('is_active', true)
            ->when($serviceName, fn ($query) => $query->where('name', $serviceName))
            ->firstOrFail();
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
