<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\MembershipPlan;
use App\Models\MembershipPurchase;
use App\Models\PaymentLedgerEntry;
use App\Models\SpaBooking;
use App\Models\SpaService;
use App\Models\User;
use App\Services\BookingRefundService;
use App\Services\BookingSlotService;
use App\Services\PaymentLedgerService;
use App\Services\PaymongoService;
use App\Services\SpaSessionService;
use App\Services\TherapistAvailabilityService;
use App\Support\PaymentMethodCatalog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StaffAppointmentPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $slots = app(BookingSlotService::class);
        $slots->seedDefaults();
        $slots->attachDefaultSlotsForServicesWithoutSchedule();
    }

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

    public function test_session_start_requires_payment_and_arrival_confirmation(): void
    {
        Carbon::setTestNow('2026-10-05 10:05:00');
        $staff = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);
        $client = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = SpaBooking::create([
            'user_id' => $client->id,
            'service_name' => 'Swedish Massage',
            'therapist_name' => 'Liza Reyes',
            'booking_date' => now()->toDateString(),
            'time_slot' => '10:00 AM',
            'amount' => 100,
            'payment_method' => PaymentMethodCatalog::METHOD_PAYMONGO,
            'payment_type' => PaymentMethodCatalog::TYPE_DOWNPAYMENT,
            'payment_amount' => 50,
            'payment_status' => PaymentMethodCatalog::STATUS_PAID,
        ]);
        PaymentLedgerEntry::query()->create([
            'spa_booking_id' => $booking->id,
            'entry_type' => PaymentLedgerEntry::TYPE_INITIAL_PAYMENT,
            'amount' => 50,
            'payment_method' => PaymentMethodCatalog::METHOD_PAYMONGO,
            'reference' => 'pay-downpayment',
            'occurred_at' => now(),
        ]);

        Carbon::setTestNow($booking->booking_date->copy()->setTime(10, 5));
        $this->actingAs($staff)->from(route('appointments.index'))->patch(route('appointments.start', $booking))
            ->assertRedirect(route('appointments.index'))
            ->assertSessionHasErrors('arrival_confirmed', errorBag: 'session_start');

        $this->assertNull($booking->fresh()->session_started_at);
        Carbon::setTestNow();
    }

    public function test_staff_collects_exact_balance_and_starts_session_atomically(): void
    {
        Carbon::setTestNow('2026-10-05 10:05:00');
        $staff = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);
        $client = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = SpaBooking::create([
            'user_id' => $client->id,
            'service_name' => 'Swedish Massage',
            'therapist_name' => 'Liza Reyes',
            'booking_date' => now()->toDateString(),
            'time_slot' => '10:00 AM',
            'amount' => 100,
            'payment_method' => PaymentMethodCatalog::METHOD_PAYMONGO,
            'payment_type' => PaymentMethodCatalog::TYPE_DOWNPAYMENT,
            'payment_amount' => 50,
            'payment_status' => PaymentMethodCatalog::STATUS_PAID,
        ]);
        PaymentLedgerEntry::query()->create([
            'spa_booking_id' => $booking->id,
            'entry_type' => PaymentLedgerEntry::TYPE_INITIAL_PAYMENT,
            'amount' => 50,
            'payment_method' => PaymentMethodCatalog::METHOD_PAYMONGO,
            'reference' => 'pay-atomic',
            'occurred_at' => now(),
        ]);

        Carbon::setTestNow($booking->booking_date->copy()->setTime(10, 5));
        $this->actingAs($staff)->patch(route('appointments.start', $booking), [
            'arrival_confirmed' => '1',
            'amount_tendered' => '100.00',
            'payment_reference' => 'OR-1001',
        ])->assertRedirect(route('ongoing-sessions.index'))
            ->assertSessionHas('payment_receipt', fn (array $receipt): bool => $receipt['payment_amount'] === '50.00'
                && $receipt['reference'] === 'OR-1001'
                && $receipt['payment_type'] === 'Balance payment');

        $booking->refresh();
        $this->assertSame(50.0, (float) $booking->balance_amount);
        $this->assertSame($staff->id, $booking->balance_collected_by);
        $this->assertSame('OR-1001', $booking->balance_payment_reference);
        $this->assertNotNull($booking->session_started_at);
        $this->assertDatabaseHas('payment_ledger_entries', [
            'spa_booking_id' => $booking->id,
            'entry_type' => PaymentLedgerEntry::TYPE_BALANCE_PAYMENT,
            'amount' => 50,
            'recorded_by' => $staff->id,
        ]);
        $this->assertSame(1, PaymentLedgerEntry::query()->where('spa_booking_id', $booking->id)->where('entry_type', PaymentLedgerEntry::TYPE_BALANCE_PAYMENT)->count());
        $paymentLog = ActivityLog::query()->where('action', 'payment.balance_collected')->where('subject_id', $booking->id)->firstOrFail();
        $this->assertSame(50.0, (float) $paymentLog->properties['change_due']);
        $this->assertSame(50.0, (float) $paymentLog->properties['amount']);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->getJson(route('reporting.data', ['period' => 'daily', 'period_value' => $booking->booking_date->format('Y-m-d')]))
            ->assertOk()
            ->assertJsonPath('grossCollections', 100)
            ->assertJsonPath('outstandingBalanceTotal', 0);
        Carbon::setTestNow();
    }

    public function test_booking_with_no_confirmed_payment_cannot_start_or_auto_complete(): void
    {
        Carbon::setTestNow('2026-10-05 10:05:00');
        $staff = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);
        $client = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = SpaBooking::create([
            'user_id' => $client->id,
            'service_name' => 'Swedish Massage',
            'therapist_name' => 'Liza Reyes',
            'booking_date' => now()->toDateString(),
            'time_slot' => '10:00 AM',
            'duration_minutes' => 60,
            'amount' => 100,
            'payment_method' => PaymentMethodCatalog::METHOD_PAYMONGO,
            'payment_type' => PaymentMethodCatalog::TYPE_FULL,
            'payment_amount' => 100,
            'payment_status' => PaymentMethodCatalog::STATUS_PENDING,
        ]);

        Carbon::setTestNow($booking->booking_date->copy()->setTime(10, 5));
        $this->actingAs($staff)->patch(route('appointments.start', $booking), ['arrival_confirmed' => '1'])
            ->assertSessionHasErrors('arrival_confirmed', errorBag: 'session_start');

        // Simulate a record created before payment enforcement existed.
        $booking->forceFill([
            'session_started_at' => now()->subHours(2),
            'session_status' => SpaBooking::STATUS_IN_SESSION,
        ])->save();

        $this->assertFalse(app(SpaSessionService::class)->autoCompleteIfExpired($booking->fresh(), now()));
        $this->assertNull($booking->fresh()->completed_at);
        $this->assertSame(SpaBooking::STATUS_IN_SESSION, $booking->fresh()->session_status);
    }

    public function test_insufficient_tender_is_rejected_without_recording_or_starting(): void
    {
        Carbon::setTestNow('2026-10-05 10:05:00');
        $staff = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $client = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = SpaBooking::query()->create([
            'user_id' => $client->id,
            'client_name' => $client->name,
            'service_name' => 'Swedish Massage',
            'therapist_name' => 'Liza Reyes',
            'booking_date' => '2026-10-05',
            'time_slot' => '10:00 AM',
            'amount' => 100,
            'payment_amount' => 50,
            'payment_method' => PaymentMethodCatalog::METHOD_PAYMONGO,
            'payment_type' => PaymentMethodCatalog::TYPE_DOWNPAYMENT,
            'payment_status' => PaymentMethodCatalog::STATUS_PAID,
        ]);

        $this->actingAs($staff)->patch(route('appointments.start', $booking), [
            'arrival_confirmed' => '1',
            'amount_tendered' => '49.99',
        ])->assertSessionHasErrors('amount_tendered', errorBag: 'session_start');

        $booking->refresh();
        $this->assertNull($booking->balance_paid_at);
        $this->assertNull($booking->session_started_at);
        $this->assertDatabaseMissing('payment_ledger_entries', [
            'spa_booking_id' => $booking->id,
            'entry_type' => PaymentLedgerEntry::TYPE_BALANCE_PAYMENT,
        ]);
        Carbon::setTestNow();
    }

    public function test_fully_paid_paymongo_start_creates_no_duplicate_payment_and_duplicate_submit_is_safe(): void
    {
        Carbon::setTestNow('2026-10-05 10:05:00');
        foreach ([User::ROLE_ADMIN, User::ROLE_RECEPTIONIST] as $index => $role) {
            $staff = User::factory()->create(['role' => $role]);
            $client = User::factory()->create(['role' => User::ROLE_USER]);
            $booking = SpaBooking::query()->create([
                'user_id' => $client->id,
                'client_name' => $client->name,
                'service_name' => 'Swedish Massage',
                'therapist_name' => $index === 0 ? 'Liza Reyes' : 'Juan dela Cruz',
                'booking_date' => '2026-10-05',
                'time_slot' => '10:00 AM',
                'amount' => 100,
                'payment_amount' => 100,
                'payment_method' => PaymentMethodCatalog::METHOD_PAYMONGO,
                'payment_type' => PaymentMethodCatalog::TYPE_FULL,
                'payment_status' => PaymentMethodCatalog::STATUS_PAID,
            ]);
            app(PaymentLedgerService::class)->recordInitialPayment($booking);
            $this->assertTrue(app(SpaSessionService::class)->toAppointmentRow($booking->fresh())['is_paymongo_verified']);

            $payload = ['arrival_confirmed' => '1'];
            $this->actingAs($staff)->patch(route('appointments.start', $booking), $payload)
                ->assertRedirect(route('ongoing-sessions.index'))
                ->assertSessionMissing('payment_receipt');
            $this->patch(route('appointments.start', $booking), $payload)
                ->assertSessionHasErrors('arrival_confirmed', errorBag: 'session_start');

            $this->assertNotNull($booking->fresh()->session_started_at);
            $this->assertSame(1, PaymentLedgerEntry::query()->where('spa_booking_id', $booking->id)->count());
            $this->assertSame(1, ActivityLog::query()->where('action', 'session.started')->where('subject_id', $booking->id)->count());
        }
        Carbon::setTestNow();
    }

    public function test_customer_cannot_confirm_payment_or_start_session(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = SpaBooking::query()->create([
            'user_id' => $client->id,
            'service_name' => 'Swedish Massage',
            'therapist_name' => 'Liza Reyes',
            'booking_date' => now()->toDateString(),
            'time_slot' => now()->format('g:i A'),
            'amount' => 100,
            'payment_amount' => 100,
            'payment_method' => PaymentMethodCatalog::METHOD_CASH_COUNTER,
            'payment_status' => PaymentMethodCatalog::STATUS_PAID,
        ]);

        $this->actingAs($client)->patch(route('appointments.start', $booking), ['arrival_confirmed' => '1'])
            ->assertForbidden();
        $this->assertNull($booking->fresh()->session_started_at);
    }

    public function test_terminal_expired_and_unavailable_therapist_appointments_cannot_start(): void
    {
        Carbon::setTestNow('2026-10-05 10:05:00');
        $staff = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);
        $client = User::factory()->create(['role' => User::ROLE_USER]);
        $states = [
            ['cancelled_at' => now(), 'session_status' => SpaBooking::STATUS_CANCELLED],
            ['completed_at' => now(), 'session_status' => SpaBooking::STATUS_COMPLETED],
            ['session_status' => SpaBooking::STATUS_NO_SHOW],
            ['time_slot' => '9:00 AM', 'session_status' => SpaBooking::STATUS_CONFIRMED],
            ['therapist_name' => 'Unknown Therapist', 'session_status' => SpaBooking::STATUS_CONFIRMED],
        ];

        foreach ($states as $index => $state) {
            $booking = SpaBooking::query()->create(array_replace([
                'user_id' => $client->id,
                'client_name' => $client->name,
                'service_name' => 'Swedish Massage',
                'therapist_name' => 'Liza Reyes',
                'booking_date' => '2026-10-05',
                'time_slot' => '10:00 AM',
                'amount' => 100,
                'payment_amount' => 100,
                'payment_method' => PaymentMethodCatalog::METHOD_PAYMONGO,
                'payment_status' => PaymentMethodCatalog::STATUS_PAID,
            ], $state));

            $this->actingAs($staff)->patch(route('appointments.start', $booking), ['arrival_confirmed' => '1'])
                ->assertSessionHasErrors('arrival_confirmed', errorBag: 'session_start');
            $this->assertNull($booking->fresh()->session_started_at, 'State '.$index.' was incorrectly started.');
        }
        Carbon::setTestNow();
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

    public function test_admin_and_receptionist_can_start_fully_paid_walk_ins_at_the_current_minute(): void
    {
        Carbon::setTestNow('2026-10-05 20:37:30');
        $therapists = app(TherapistAvailabilityService::class)
            ->bookableTherapistNamesForDate(now());
        $this->assertGreaterThanOrEqual(2, count($therapists));
        $expectedGross = 0.0;
        $admin = null;

        foreach ([User::ROLE_ADMIN, User::ROLE_RECEPTIONIST] as $index => $role) {
            $staff = User::factory()->create(['role' => $role]);
            $client = User::factory()->create(['role' => User::ROLE_USER]);
            $admin ??= $role === User::ROLE_ADMIN ? $staff : null;

            $response = $this->actingAs($staff)->post(
                route('appointments.store'),
                $this->immediateBookingInput($client, $therapists[$index]),
            );

            $response->assertSessionHasNoErrors()
                ->assertRedirect(route('appointments.index', ['date' => '2026-10-05']))
                ->assertSessionHas('status');

            $booking = SpaBooking::query()->where('user_id', $client->id)->firstOrFail();
            $this->assertSame('8:37 PM', $booking->time_slot);
            $this->assertSame(SpaBooking::SOURCE_WALK_IN, $booking->booking_source);
            $this->assertSame(SpaBooking::STATUS_IN_SESSION, $booking->session_status);
            $this->assertSame('2026-10-05 20:37:30', $booking->session_started_at?->format('Y-m-d H:i:s'));
            $this->assertSame(PaymentMethodCatalog::METHOD_CASH_COUNTER, $booking->payment_method);
            $this->assertSame(PaymentMethodCatalog::TYPE_FULL, $booking->payment_type);
            $this->assertSame(PaymentMethodCatalog::STATUS_PAID, $booking->payment_status);
            $this->assertTrue($booking->isFullyPaid());
            $this->assertDatabaseHas('payment_ledger_entries', [
                'spa_booking_id' => $booking->id,
                'entry_type' => PaymentLedgerEntry::TYPE_INITIAL_PAYMENT,
                'amount' => $booking->amount,
                'recorded_by' => $staff->id,
            ]);
            $this->assertDatabaseHas('activity_logs', [
                'user_id' => $staff->id,
                'action' => 'appointment.created',
                'subject_id' => $booking->id,
            ]);
            $this->assertDatabaseHas('activity_logs', [
                'user_id' => $staff->id,
                'action' => 'session.started',
                'subject_id' => $booking->id,
            ]);
            $sessionLog = ActivityLog::query()
                ->where('user_id', $staff->id)
                ->where('action', 'session.started')
                ->where('subject_id', $booking->id)
                ->firstOrFail();
            $this->assertSame($booking->session_started_at?->toIso8601String(), $sessionLog->properties['actual_start_at']);
            $this->assertSame(
                $booking->session_started_at?->copy()->addMinutes((int) $booking->duration_minutes)->toIso8601String(),
                $sessionLog->properties['expected_end_at'],
            );
            $expectedGross += (float) $booking->amount;
        }

        $this->actingAs($admin)->getJson(route('reporting.data', [
            'period' => 'daily',
            'period_value' => '2026-10-05',
        ]))->assertOk()->assertJsonPath('grossCollections', (int) $expectedGross);

        Carbon::setTestNow();
    }

    public function test_immediate_walk_in_rejects_overlaps_for_the_full_service_window(): void
    {
        Carbon::setTestNow('2026-10-05 20:37:30');
        $staff = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);
        $client = User::factory()->create(['role' => User::ROLE_USER]);
        $otherClient = User::factory()->create(['role' => User::ROLE_USER]);
        $therapist = app(TherapistAvailabilityService::class)->bookableTherapistNamesForDate(now())[0];
        $service = SpaService::query()->where('is_active', true)->firstOrFail();

        SpaBooking::query()->create([
            'user_id' => $otherClient->id,
            'client_name' => $otherClient->name,
            'booking_source' => SpaBooking::SOURCE_WALK_IN,
            'service_name' => $service->name,
            'therapist_name' => $therapist,
            'booking_date' => '2026-10-05',
            'time_slot' => '8:30 PM',
            'duration_minutes' => 60,
            'amount' => 100,
            'payment_amount' => 100,
            'payment_method' => PaymentMethodCatalog::METHOD_CASH_COUNTER,
            'payment_type' => PaymentMethodCatalog::TYPE_FULL,
            'payment_status' => PaymentMethodCatalog::STATUS_PAID,
            'session_status' => SpaBooking::STATUS_CONFIRMED,
        ]);

        $this->actingAs($staff)->from(route('appointments.index'))->post(
            route('appointments.store'),
            $this->immediateBookingInput($client, $therapist, (string) $service->name),
        )->assertRedirect(route('appointments.index'))
            ->assertSessionHasErrors('immediate_start_time', errorBag: 'appointment');

        $this->assertDatabaseCount('spa_bookings', 1);
        Carbon::setTestNow();
    }

    public function test_immediate_walk_in_insufficient_tender_rolls_back_booking_payment_and_session(): void
    {
        Carbon::setTestNow('2026-10-05 20:37:30');
        $staff = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);
        $client = User::factory()->create(['role' => User::ROLE_USER]);
        $therapist = app(TherapistAvailabilityService::class)->bookableTherapistNamesForDate(now())[0];

        $this->actingAs($staff)->post(route('appointments.store'), array_replace(
            $this->immediateBookingInput($client, $therapist),
            ['counter_amount_tendered' => '0.01'],
        ))->assertSessionHasErrors('counter_amount_tendered', errorBag: 'appointment');

        $this->assertDatabaseCount('spa_bookings', 0);
        $this->assertDatabaseCount('payment_ledger_entries', 0);
        $this->assertDatabaseMissing('activity_logs', ['action' => 'session.started']);
        Carbon::setTestNow();
    }

    public function test_immediate_walk_in_rejects_stale_future_and_unconfirmed_times(): void
    {
        Carbon::setTestNow('2026-10-05 20:37:30');
        $staff = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $client = User::factory()->create(['role' => User::ROLE_USER]);
        $therapist = app(TherapistAvailabilityService::class)->bookableTherapistNamesForDate(now())[0];

        foreach ([
            ['immediate_start_time' => '20:31'],
            ['immediate_start_time' => '20:38'],
            ['immediate_confirmed' => null],
        ] as $override) {
            $this->actingAs($staff)->post(
                route('appointments.store'),
                array_replace($this->immediateBookingInput($client, $therapist), $override),
            )->assertSessionHasErrors(
                array_key_exists('immediate_confirmed', $override) ? 'immediate_confirmed' : 'immediate_start_time',
                errorBag: 'appointment',
            );
        }

        $this->assertDatabaseCount('spa_bookings', 0);
        Carbon::setTestNow();
    }

    public function test_immediate_walk_in_respects_disabled_service_periods(): void
    {
        Carbon::setTestNow('2026-10-05 20:37:30');
        $staff = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);
        $client = User::factory()->create(['role' => User::ROLE_USER]);
        $therapist = app(TherapistAvailabilityService::class)->bookableTherapistNamesForDate(now())[0];
        $service = SpaService::query()->where('is_active', true)->firstOrFail();
        $slotId = DB::table('time_slots')->where('label', '8:30 PM')->value('id');
        $this->assertNotNull($slotId);

        DB::table('service_slot_date_overrides')->updateOrInsert(
            [
                'service_name' => $service->name,
                'slot_date' => '2026-10-05',
                'time_slot_id' => $slotId,
            ],
            [
                'is_enabled' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $this->actingAs($staff)->post(
            route('appointments.store'),
            $this->immediateBookingInput($client, $therapist, (string) $service->name),
        )->assertSessionHasErrors('immediate_start_time', errorBag: 'appointment');

        $this->assertDatabaseCount('spa_bookings', 0);
        Carbon::setTestNow();
    }

    public function test_immediate_walk_in_requires_full_counter_payment_and_staff_access(): void
    {
        Carbon::setTestNow('2026-10-05 20:37:30');
        $staff = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $client = User::factory()->create(['role' => User::ROLE_USER]);
        $therapist = app(TherapistAvailabilityService::class)->bookableTherapistNamesForDate(now())[0];
        $input = $this->immediateBookingInput($client, $therapist);

        $this->actingAs($staff)->post(
            route('appointments.store'),
            array_replace($input, ['payment_method' => PaymentMethodCatalog::METHOD_PAYMONGO]),
        )->assertSessionHasErrors('payment_method', errorBag: 'appointment');

        $this->actingAs($staff)->post(
            route('appointments.store'),
            array_replace($input, ['payment_type' => PaymentMethodCatalog::TYPE_DOWNPAYMENT]),
        )->assertSessionHasErrors('payment_method', errorBag: 'appointment');

        $this->actingAs($client)->post(route('appointments.store'), $input)->assertForbidden();
        $this->assertDatabaseCount('spa_bookings', 0);
        Carbon::setTestNow();
    }

    public function test_staff_appointment_form_explains_immediate_walk_in_controls(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);

        $this->actingAs($staff)->get(route('appointments.index'))
            ->assertOk()
            ->assertSee('Start walk-in now')
            ->assertSee('Actual start time')
            ->assertSee('Payment and arrival are confirmed in the next step.')
            ->assertSee('Confirm Payment and Start')
            ->assertSee('addServiceDurationMap', false);
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

    private function immediateBookingInput(User $client, string $therapist, ?string $serviceName = null): array
    {
        return array_replace(
            $this->bookingInput($client, PaymentMethodCatalog::METHOD_CASH_COUNTER, PaymentMethodCatalog::TYPE_FULL, $serviceName),
            [
                'booking_mode' => 'immediate',
                'booking_date' => '',
                'time_slot' => '',
                'immediate_start_time' => now()->format('H:i'),
                'immediate_confirmed' => '1',
                'counter_amount_tendered' => '100000.00',
                'counter_payment_reference' => 'OR-WALK-IN',
                'therapist' => $therapist,
            ],
        );
    }
}
