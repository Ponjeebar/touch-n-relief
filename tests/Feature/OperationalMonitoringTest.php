<?php

namespace Tests\Feature;

use App\Models\BookingRefund;
use App\Models\GatewayPaymentReconciliation;
use App\Models\SiteSetting;
use App\Models\SpaBooking;
use App\Models\StaffNotification;
use App\Models\User;
use App\Services\NoShowService;
use App\Services\OperationalMonitoringService;
use App\Services\SiteSettingsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

class OperationalMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-19 13:20:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_check_reports_only_overdue_eligible_work_and_aged_gateway_cases_without_changing_them(): void
    {
        $overdue = $this->booking();
        $recent = $this->booking(['time_slot' => '1:01 PM']);
        $session = $this->booking(['session_status' => 'in_session', 'session_started_at' => now()->subMinutes(65)]);
        $unpaid = $this->booking(['session_status' => 'in_session', 'session_started_at' => now()->subHours(2), 'payment_amount' => 50]);
        $hold = $this->booking(['booking_source' => 'online', 'payment_method' => 'paymongo', 'payment_status' => 'pending', 'time_slot' => '10:00 AM']);
        $this->booking(['session_status' => 'cancelled', 'cancelled_at' => now()]);
        GatewayPaymentReconciliation::create(['gateway_payment_id' => 'pay_aged', 'spa_booking_id' => $overdue->id, 'amount' => 100, 'currency' => 'PHP', 'reason' => 'distinct_paid_reference', 'status' => 'open', 'received_at' => now()->subDay()]);
        GatewayPaymentReconciliation::create(['gateway_payment_id' => 'pay_recent', 'spa_booking_id' => $overdue->id, 'reason' => 'distinct_paid_reference', 'status' => 'open', 'received_at' => now()]);
        $refund = BookingRefund::create(['spa_booking_id' => $overdue->id, 'processing_channel' => 'paymongo', 'payment_component' => 'initial_payment', 'amount' => 100, 'status' => 'pending', 'reference' => 'ref_uncertain', 'requested_at' => now()->subDay()]);
        BookingRefund::create(['spa_booking_id' => $overdue->id, 'processing_channel' => 'manual', 'payment_component' => 'initial_payment', 'amount' => 100, 'status' => 'pending', 'reference' => 'manual_old', 'requested_at' => now()->subDays(2)]);
        foreach ([$overdue, $recent, $session, $unpaid, $hold] as $booking) {
            $booking->refresh();
        }

        $this->assertSame(1, Artisan::call('operations:check', ['--json' => true]));
        $snapshot = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(['overdue_no_shows' => 1, 'overdue_sessions' => 1, 'aged_gateway_payments' => 1, 'aged_gateway_refunds' => 1], $snapshot['counts']);
        foreach ([$overdue, $recent, $session, $unpaid, $hold] as $booking) {
            $this->assertSame($booking->getRawOriginal(), $booking->fresh()->getRawOriginal());
        }
        $this->assertSame('pending', $refund->fresh()->status);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('payment_ledger_entries', 0);
        $this->assertDatabaseCount('staff_notifications', 0);
        User::factory()->create(['role' => User::ROLE_ADMIN]);
        Artisan::call('operations:check', ['--notify' => true]);
        Artisan::call('operations:check', ['--notify' => true]);
        $this->assertDatabaseCount('staff_notifications', 4);
        $this->assertDatabaseCount('payment_ledger_entries', 0);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertSame('pending', $refund->fresh()->status);
    }

    public function test_successful_processors_become_stale_and_alerts_are_deduplicated_for_administrators(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $receptionist = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);
        $this->assertSame('unverified', app(OperationalMonitoringService::class)->snapshot()['status']);
        Artisan::call('appointments:process-no-shows');
        Artisan::call('appointments:complete-expired-sessions');
        $this->assertSame('healthy', app(OperationalMonitoringService::class)->snapshot()['status']);
        Carbon::setTestNow(now()->addMinutes(11));
        foreach (range(1, 2) as $attempt) {
            $this->assertSame(1, Artisan::call('operations:check', ['--notify' => true]));
        }
        $this->assertDatabaseCount('staff_notifications', 2);
        $this->assertDatabaseMissing('staff_notifications', ['staff_user_id' => $receptionist->id]);
        $this->assertSame(2, StaffNotification::where('staff_user_id', $admin->id)->count());
        Artisan::call('appointments:process-no-shows');
        Artisan::call('appointments:complete-expired-sessions');
        $this->assertSame(0, Artisan::call('operations:check', ['--json' => true]));
        $this->assertSame('healthy', json_decode(Artisan::output(), true)['status']);
        $this->assertDatabaseCount('staff_notifications', 2);
    }

    public function test_processor_failure_preserves_its_previous_success_and_remains_visible(): void
    {
        Artisan::call('appointments:process-no-shows');
        $success = now()->toIso8601String();
        Carbon::setTestNow(now()->addMinute());
        $noShows = app(NoShowService::class);
        $this->mock(NoShowService::class)->shouldReceive('processOverdue')->once()->andThrow(new RuntimeException('Simulated processor failure'));
        try {
            Artisan::call('appointments:process-no-shows');
            $this->fail('The processor error must remain visible to the command runner.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated processor failure', $exception->getMessage());
        }
        $this->app->instance(NoShowService::class, $noShows);
        $processor = collect(app(OperationalMonitoringService::class)->snapshot()['processors'])->firstWhere('key', 'no_show');
        $this->assertSame('failed', $processor['status']);
        $this->assertSame($success, $processor['lastSuccessAt']);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('payment_ledger_entries', 0);
    }

    public function test_monitoring_is_visible_to_admins_across_report_periods_without_changing_sales(): void
    {
        $booking = $this->booking(['session_status' => 'cancelled', 'cancelled_at' => now()]);
        GatewayPaymentReconciliation::create(['gateway_payment_id' => 'pay_old_review', 'spa_booking_id' => $booking->id, 'reason' => 'distinct_paid_reference', 'status' => 'open', 'received_at' => now()->subMonths(2)]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->getJson(route('reporting.data', ['period' => 'daily']))->assertOk()
            ->assertJsonPath('operationalMonitoring.counts.aged_gateway_payments', 1)->assertJsonPath('primaryAmount', 0);
        $response = $this->get(route('reporting.index'))->assertOk()->assertSee('Operational checks');
        $this->assertSame('unknown', $response->viewData('operationalMonitoring')['processors'][0]['status']);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_USER]))->getJson(route('reporting.data'))->assertForbidden();
        $this->assertDatabaseCount('gateway_payment_reconciliations', 1);
        $this->assertDatabaseCount('payment_ledger_entries', 0);
    }

    public function test_cache_failure_does_not_prevent_committed_no_show_processing(): void
    {
        $booking = $this->booking(['time_slot' => '12:00 PM']);
        Cache::shouldReceive('store')->andThrow(new RuntimeException('Monitoring cache unavailable'));
        $this->assertSame(0, Artisan::call('appointments:process-no-shows'));
        $this->assertSame('no_show', $booking->fresh()->session_status);
        $this->assertDatabaseHas('customer_notifications', ['spa_booking_id' => $booking->id, 'type' => 'no_show']);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_overdue_detection_respects_current_system_settings_and_the_processing_allowance(): void
    {
        SiteSetting::put(SiteSettingsService::KEY_LATE_GRACE_MINUTES, '20');
        SiteSetting::put(SiteSettingsService::KEY_NO_SHOW_REVIEW_MINUTES, '10');
        $booking = $this->booking();
        Carbon::setTestNow('2026-10-19 13:34:59');
        $this->assertSame(0, app(OperationalMonitoringService::class)->snapshot()['counts']['overdue_no_shows']);
        Carbon::setTestNow('2026-10-19 13:35:00');
        $this->assertSame(1, app(OperationalMonitoringService::class)->snapshot()['counts']['overdue_no_shows']);
        $this->assertSame('confirmed', $booking->fresh()->session_status);
    }

    private function booking(array $values = []): SpaBooking
    {
        $client = User::factory()->create(['role' => User::ROLE_USER]);

        return SpaBooking::create(array_replace(['user_id' => $client->id, 'client_name' => $client->name, 'service_name' => 'Swedish Massage', 'therapist_name' => 'Monitoring Therapist', 'booking_date' => now()->toDateString(), 'time_slot' => '1:00 PM', 'duration_minutes' => 60, 'amount' => 100, 'payment_amount' => 100, 'payment_method' => 'cash_counter', 'payment_status' => 'paid', 'session_status' => 'confirmed'], $values));
    }
}
