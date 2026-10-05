<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\SpaBooking;
use App\Models\Therapist;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BookingSlotService;
use App\Services\PaymentLedgerService;
use App\Services\SpaSessionService;
use App\Support\PaymentMethodCatalog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditWorkflowRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-06 13:09:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_expired_and_failed_holds_do_not_create_rest_but_paid_sessions_do(): void
    {
        $slots = app(BookingSlotService::class);
        collect(['08:00 AM', '09:00 AM', '10:00 AM'])->each(fn ($time) => $this->booking([
            'time_slot' => $time, 'booking_source' => SpaBooking::SOURCE_ONLINE,
            'payment_method' => 'paymongo', 'payment_status' => 'pending', 'payment_amount' => 0,
        ])->forceFill(['created_at' => now()->subDay()])->save());
        $this->assertSame([], $slots->therapistRestWindowsForDate('Audit Therapist', now()->toDateString()));
        $this->assertFalse($slots->therapistSlotTaken('Audit Therapist', now()->toDateString(), '11:00 AM', 60));
        SpaBooking::query()->update(['payment_status' => 'failed']);
        $this->assertSame([], $slots->therapistRestWindowsForDate('Audit Therapist', now()->toDateString()));
        SpaBooking::query()->update(['payment_status' => 'paid', 'payment_amount' => 100, 'session_status' => 'completed', 'completed_at' => now()]);
        $this->assertCount(1, $slots->therapistRestWindowsForDate('Audit Therapist', now()->toDateString()));
        $this->assertTrue($slots->therapistSlotTaken('Audit Therapist', now()->toDateString(), '11:00 AM', 60));
    }

    public function test_late_started_session_blocks_therapist_customer_and_displayed_slots_until_actual_end(): void
    {
        $booking = $this->booking(['session_status' => 'in_session', 'session_started_at' => now()]);
        $slots = app(BookingSlotService::class);
        $date = now()->toDateString();
        $this->assertTrue($slots->therapistSlotTaken('Audit Therapist', $date, '02:00 PM', 60));
        $this->assertNotNull($slots->userConflictAt($booking->user_id, $date, '02:00 PM', 60));
        $this->assertSame(['2:00 PM'], $slots->therapistBusySlotsForDate('Audit Therapist', $date, ['2:00 PM'], 60));
        $this->assertArrayHasKey('2:00 PM', $slots->therapistBusyDetailsForDate('Audit Therapist', $date, ['2:00 PM'], 60));
        $this->assertFalse($slots->therapistSlotTaken('Audit Therapist', $date, '02:09 PM', 60));
    }

    public function test_client_history_preserves_terminal_status_and_deduplicates_linked_transactions(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);
        $client = User::factory()->create(['role' => User::ROLE_USER]);
        $customer = Customer::create(['customer_id' => 'AUDIT-CLIENT', 'full_name' => $client->name, 'email' => $client->email, 'password' => 'unused']);
        $noShow = $this->booking(['user_id' => $client->id, 'client_name' => $client->name, 'booking_date' => now()->subDay(), 'session_status' => 'no_show']);
        $completed = $this->booking(['user_id' => $client->id, 'therapist_name' => Therapist::firstOrFail()->name, 'booking_date' => now()->subDays(2), 'session_status' => 'completed', 'completed_at' => now()->subDays(2)]);
        app(SpaSessionService::class)->syncTransaction($completed);
        Transaction::create(['transaction_id' => 'LEGACY-AUDIT', 'therapist_id' => Therapist::firstOrFail()->id, 'user_id' => $client->id, 'client_name' => $client->name, 'service_name' => 'Legacy service', 'date' => now()->subDays(3)->toDateString(), 'time' => '10:00:00', 'duration' => 60, 'amount' => 100]);
        $response = $this->actingAs($staff)->get(route('client-records.show', $customer))->assertOk();
        $rows = $response->viewData('transactions');
        $this->assertSame('no-show', collect($rows)->firstWhere('transaction_id', 'BKG-'.str_pad($noShow->id, 5, '0', STR_PAD_LEFT))['status_key']);
        $this->assertCount(3, $rows);
        $this->assertCount(1, collect($rows)->where('status_key', 'completed')->where('service', 'Swedish Massage'));
        $response->assertSee('value="no-show"', false)->assertSee('LEGACY-AUDIT');
    }

    public function test_past_unstarted_history_is_not_inferred_completed_and_expired_is_not_pending(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_USER]);
        $customer = Customer::create(['customer_id' => 'AUDIT-STATES', 'full_name' => $client->name, 'email' => $client->email, 'password' => 'unused']);
        $common = ['user_id' => $client->id];
        $confirmed = $this->booking($common + ['booking_date' => now()->subDay()]);
        $expired = $this->booking($common + ['booking_source' => 'online', 'payment_method' => 'paymongo', 'payment_status' => 'pending', 'payment_amount' => 0]);
        $expired->forceFill(['created_at' => now()->subDay()])->save();
        $cancelled = $this->booking($common + ['session_status' => 'cancelled', 'cancelled_at' => now()]);
        $pending = $this->booking($common + ['booking_source' => 'online', 'payment_method' => 'paymongo', 'payment_status' => 'pending', 'payment_amount' => 0]);
        $balance = $this->booking($common + ['payment_amount' => 50]);
        $rescheduled = $this->booking($common + ['rescheduled_at' => now()]);
        $expected = [$confirmed->id => 'confirmed', $expired->id => 'expired', $cancelled->id => 'cancelled', $pending->id => 'pending', $balance->id => 'balance-due', $rescheduled->id => 'rescheduled'];
        foreach ([User::ROLE_ADMIN, User::ROLE_RECEPTIONIST] as $role) {
            $staff = User::factory()->create(['role' => $role]);
            $response = $this->actingAs($staff)->get(route('client-records.show', $customer))->assertOk();
            $rows = collect($response->viewData('transactions'))->keyBy('transaction_id');
            foreach ($expected as $id => $key) {
                $this->assertSame($key, $rows->get('BKG-'.str_pad($id, 5, '0', STR_PAD_LEFT))['status_key']);
                $response->assertSee('value="'.$key.'"', false);
            }
        }
        $this->assertSame('Payment hold expired', app(SpaSessionService::class)->toUserTransactionRow($expired)['status']);
    }

    public function test_zero_available_therapists_is_preserved_and_inactive_are_excluded(): void
    {
        Therapist::query()->update(['status' => 'off-duty', 'work_on_off_day' => false, 'day_off_until' => now()->addDay()]);
        $snapshot = app(SpaSessionService::class)->dashboardSnapshot();
        $this->assertSame(0, $snapshot['active_therapists']);
        $therapist = Therapist::query()->firstOrFail();
        $therapist->forceFill(['is_active' => false, 'work_on_off_day' => true])->save();
        $this->assertSame(0, app(SpaSessionService::class)->dashboardSnapshot()['active_therapists']);
        $therapist->forceFill(['is_active' => true])->save();
        $this->assertSame(1, app(SpaSessionService::class)->dashboardSnapshot()['active_therapists']);
    }

    public function test_dashboard_labels_completed_service_value_separately_from_collections(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $booking = $this->booking(['booking_date' => now()->addDay()]);
        app(PaymentLedgerService::class)->recordInitialPayment($booking);
        $this->assertSame('0', app(SpaSessionService::class)->dashboardSnapshot()['today_sales']);
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee('Completed service value today')->assertDontSee("Today's sales");
        $this->getJson(route('reporting.data', ['period' => 'daily', 'period_value' => now()->toDateString()]))
            ->assertOk()->assertJsonPath('grossCollections', 100)->assertJsonPath('primaryAmount', 100);
    }

    private function booking(array $values = []): SpaBooking
    {
        $user = User::factory()->create(['role' => User::ROLE_USER]);

        return SpaBooking::create(array_replace([
            'user_id' => $user->id, 'client_name' => $user->name, 'service_name' => 'Swedish Massage',
            'therapist_name' => 'Audit Therapist', 'booking_date' => now()->toDateString(), 'time_slot' => '01:00 PM',
            'duration_minutes' => 60, 'amount' => 100, 'payment_amount' => 100,
            'payment_method' => PaymentMethodCatalog::METHOD_CASH_COUNTER, 'payment_status' => 'paid', 'session_status' => 'confirmed',
        ], $values));
    }
}
