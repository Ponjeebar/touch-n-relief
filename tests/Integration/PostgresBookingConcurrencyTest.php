<?php

namespace Tests\Integration;

use App\Models\BookingRefund;
use App\Models\MembershipPurchase;
use App\Models\PaymentLedgerEntry;
use App\Models\RefundConfirmation;
use App\Models\SpaBooking;
use App\Models\Therapist;
use App\Models\User;
use App\Services\BookingRefundService;
use App\Services\SiteSettingsService;
use App\Support\PaymentMethodCatalog;
use Database\Seeders\SiteSettingsSeeder;
use Database\Seeders\SpaServiceSeeder;
use Database\Seeders\SpaTimeSlotSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PostgresBookingConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('This test requires PostgreSQL.');
        }

        if (! str_contains((string) config('database.connections.pgsql.database'), 'test')) {
            $this->fail('The PostgreSQL concurrency test must only run against a test database.');
        }

        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->seed([
            SiteSettingsSeeder::class,
            SpaServiceSeeder::class,
            SpaTimeSlotSeeder::class,
        ]);
    }

    public function test_two_simultaneous_requests_cannot_claim_the_same_therapist_slot(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $therapist = Therapist::query()->create([
            'therapist_code' => 'PG-LOCK-1',
            'name' => 'Concurrency Therapist',
            'status' => 'available',
            'work_on_off_day' => true,
            'is_active' => true,
        ]);
        $bookingDate = now()->addDays(7)->toDateString();
        $barrier = storage_path('framework/testing/concurrency-'.bin2hex(random_bytes(8)));

        $environment = array_filter(array_merge($_SERVER, $_ENV, [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => (string) config('database.connections.pgsql.host'),
            'DB_PORT' => (string) config('database.connections.pgsql.port'),
            'DB_DATABASE' => (string) config('database.connections.pgsql.database'),
            'DB_USERNAME' => (string) config('database.connections.pgsql.username'),
            'DB_PASSWORD' => (string) config('database.connections.pgsql.password'),
        ]), static fn (mixed $value): bool => is_scalar($value));
        $worker = base_path('tests/Support/postgres-booking-worker.php');
        $phpCommand = [PHP_BINARY];
        $processes = collect([$firstUser, $secondUser])->map(fn (User $user): Process => new Process(array_merge($phpCommand, [
            $worker,
            (string) $user->id,
            $therapist->name,
            $bookingDate,
            $barrier,
        ]), base_path(), $environment, null, 30))->all();

        try {
            foreach ($processes as $process) {
                $process->start();
            }
            usleep(250000);
            touch($barrier);
            foreach ($processes as $process) {
                $process->wait();
            }

            $results = collect($processes)->map(fn (Process $process): string => trim($process->getOutput()))->sort()->values()->all();
            $this->assertSame(['created', 'unavailable'], $results, collect($processes)->map(fn (Process $process): string => $process->getErrorOutput())->implode("\n"));
            $this->assertSame(1, DB::table('spa_bookings')
                ->where('therapist_name', $therapist->name)
                ->whereDate('booking_date', $bookingDate)
                ->where('time_slot', '10:00 AM')
                ->count());
        } finally {
            @unlink($barrier);
        }
    }

    public function test_two_simultaneous_staff_requests_collect_one_balance_and_start_once(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $staff = collect([User::ROLE_ADMIN, User::ROLE_RECEPTIONIST])
            ->map(fn (string $role): User => User::factory()->create(['role' => $role]));
        $therapist = Therapist::query()->create([
            'therapist_code' => 'PG-LOCK-START',
            'name' => 'Session Lock Therapist',
            'status' => 'available',
            'work_on_off_day' => true,
            'is_active' => true,
        ]);
        $booking = SpaBooking::query()->create([
            'user_id' => $customer->id,
            'client_name' => $customer->name,
            'service_name' => 'Foot Reflexology',
            'therapist_name' => $therapist->name,
            'booking_date' => now()->toDateString(),
            'time_slot' => now()->format('g:i A'),
            'duration_minutes' => 45,
            'amount' => 100,
            'payment_amount' => 50,
            'payment_method' => PaymentMethodCatalog::METHOD_PAYMONGO,
            'payment_type' => PaymentMethodCatalog::TYPE_DOWNPAYMENT,
            'payment_status' => PaymentMethodCatalog::STATUS_PAID,
            'session_status' => SpaBooking::STATUS_CONFIRMED,
        ]);
        $barrier = storage_path('framework/testing/session-start-'.bin2hex(random_bytes(8)));
        $environment = array_filter(array_merge($_SERVER, $_ENV, [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => (string) config('database.connections.pgsql.host'),
            'DB_PORT' => (string) config('database.connections.pgsql.port'),
            'DB_DATABASE' => (string) config('database.connections.pgsql.database'),
            'DB_USERNAME' => (string) config('database.connections.pgsql.username'),
            'DB_PASSWORD' => (string) config('database.connections.pgsql.password'),
        ]), static fn (mixed $value): bool => is_scalar($value));
        $phpCommand = [PHP_BINARY];
        $processes = $staff->map(fn (User $user): Process => new Process(array_merge($phpCommand, [
            base_path('tests/Support/postgres-session-start-worker.php'),
            (string) $user->id,
            (string) $booking->id,
            $barrier,
        ]), base_path(), $environment, null, 30))->all();

        try {
            foreach ($processes as $process) {
                $process->start();
            }
            usleep(250000);
            touch($barrier);
            foreach ($processes as $process) {
                $process->wait();
            }

            $results = collect($processes)->map(fn (Process $process): string => trim($process->getOutput()))->sort()->values()->all();
            $this->assertSame(['stale', 'started'], $results, collect($processes)->map(fn (Process $process): string => $process->getErrorOutput())->implode("\n"));
            $this->assertSame(1, PaymentLedgerEntry::query()->where('spa_booking_id', $booking->id)->where('entry_type', PaymentLedgerEntry::TYPE_BALANCE_PAYMENT)->count());
            $this->assertSame(1, DB::table('activity_logs')->where('subject_id', $booking->id)->where('action', 'session.started')->count());
        } finally {
            @unlink($barrier);
        }
    }

    public function test_two_simultaneous_manual_refund_confirmations_create_one_refund_ledger_entry(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $staff = collect([User::ROLE_ADMIN, User::ROLE_RECEPTIONIST])
            ->map(fn (string $role): User => User::factory()->create(['role' => $role]));
        $booking = SpaBooking::query()->create([
            'user_id' => $customer->id,
            'client_name' => $customer->name,
            'service_name' => 'Foot Reflexology',
            'booking_date' => now()->addDay()->toDateString(),
            'time_slot' => '10:00 AM',
            'amount' => 50,
            'payment_amount' => 50,
            'payment_method' => PaymentMethodCatalog::METHOD_CASH_COUNTER,
            'payment_status' => PaymentMethodCatalog::STATUS_PAID,
            'cancelled_at' => now(),
            'session_status' => SpaBooking::STATUS_CANCELLED,
        ]);
        BookingRefund::query()->create([
            'spa_booking_id' => $booking->id,
            'payment_component' => BookingRefund::COMPONENT_INITIAL,
            'processing_channel' => BookingRefund::CHANNEL_MANUAL,
            'payment_method' => PaymentMethodCatalog::METHOD_CASH_COUNTER,
            'amount' => 50,
            'status' => BookingRefundService::STATUS_PENDING,
            'reference' => 'RF-PND-PG-CONCURRENT',
            'requested_at' => now(),
        ]);
        $barrier = storage_path('framework/testing/refund-'.bin2hex(random_bytes(8)));
        $environment = array_filter(array_merge($_SERVER, $_ENV, [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => (string) config('database.connections.pgsql.host'),
            'DB_PORT' => (string) config('database.connections.pgsql.port'),
            'DB_DATABASE' => (string) config('database.connections.pgsql.database'),
            'DB_USERNAME' => (string) config('database.connections.pgsql.username'),
            'DB_PASSWORD' => (string) config('database.connections.pgsql.password'),
        ]), static fn (mixed $value): bool => is_scalar($value));
        $phpCommand = [PHP_BINARY];
        $processes = $staff->map(fn (User $user): Process => new Process(array_merge($phpCommand, [
            base_path('tests/Support/postgres-refund-worker.php'),
            (string) $user->id,
            (string) $booking->id,
            $barrier,
        ]), base_path(), $environment, null, 30))->all();

        try {
            foreach ($processes as $process) {
                $process->start();
            }
            usleep(250000);
            touch($barrier);
            foreach ($processes as $process) {
                $process->wait();
            }

            $results = collect($processes)->map(fn (Process $process): string => trim($process->getOutput()))->sort()->values()->all();
            $this->assertSame(['processed', 'stale'], $results, collect($processes)->map(fn (Process $process): string => $process->getErrorOutput())->implode("\n"));
            $this->assertSame(1, DB::table('refund_confirmations')->where('spa_booking_id', $booking->id)->count());
            $this->assertSame(1, PaymentLedgerEntry::query()->where('spa_booking_id', $booking->id)
                ->where('entry_type', PaymentLedgerEntry::TYPE_REFUND)->count());
        } finally {
            @unlink($barrier);
        }
    }

    public function test_repeated_paymongo_webhooks_record_one_verified_collection(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = SpaBooking::query()->create([
            'user_id' => $customer->id,
            'client_name' => $customer->name,
            'booking_source' => SpaBooking::SOURCE_ONLINE,
            'service_name' => 'Foot Reflexology',
            'therapist_name' => 'Concurrency Therapist',
            'booking_date' => now()->addDay()->toDateString(),
            'time_slot' => '10:00 AM',
            'duration_minutes' => 45,
            'amount' => 100,
            'payment_amount' => 50,
            'payment_method' => PaymentMethodCatalog::METHOD_PAYMONGO,
            'payment_type' => PaymentMethodCatalog::TYPE_DOWNPAYMENT,
            'payment_status' => PaymentMethodCatalog::STATUS_PENDING,
            'session_status' => SpaBooking::STATUS_CONFIRMED,
        ]);

        $results = $this->runWorkflowWorkers('webhook', $booking->id, ['first', 'second']);

        $this->assertSame(['accepted', 'accepted'], $results);
        $this->assertSame(PaymentMethodCatalog::STATUS_PAID, $booking->fresh()->payment_status);
        $this->assertSame(1, PaymentLedgerEntry::query()
            ->where('spa_booking_id', $booking->id)
            ->where('entry_type', PaymentLedgerEntry::TYPE_INITIAL_PAYMENT)
            ->count());
    }

    public function test_late_payment_and_replacement_booking_leave_one_slot_owner(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $competitor = User::factory()->create(['role' => User::ROLE_USER]);
        $therapist = Therapist::query()->create([
            'therapist_code' => 'PG-LATE-PAYMENT',
            'name' => 'Late Payment Therapist',
            'status' => 'available',
            'work_on_off_day' => true,
            'is_active' => true,
        ]);
        $booking = SpaBooking::query()->create([
            'user_id' => $customer->id,
            'client_name' => $customer->name,
            'booking_source' => SpaBooking::SOURCE_ONLINE,
            'service_name' => 'Foot Reflexology',
            'therapist_name' => $therapist->name,
            'booking_date' => now()->addDays(7)->toDateString(),
            'time_slot' => '10:00 AM',
            'duration_minutes' => 45,
            'amount' => 70,
            'payment_amount' => 70,
            'payment_method' => PaymentMethodCatalog::METHOD_PAYMONGO,
            'payment_type' => PaymentMethodCatalog::TYPE_FULL,
            'payment_status' => PaymentMethodCatalog::STATUS_PENDING,
            'session_status' => SpaBooking::STATUS_CONFIRMED,
        ]);
        $booking->forceFill([
            'created_at' => now()->subMinutes(20),
            'updated_at' => now()->subMinutes(20),
        ])->saveQuietly();

        $results = $this->runWorkflowWorkers(
            'late-payment-race',
            $booking->id,
            ['webhook', 'booking-'.$competitor->id],
        );

        $this->assertContains($results, [['accepted', 'booked'], ['accepted', 'stale']]);
        $this->assertSame(1, SpaBooking::query()
            ->blocksAvailability()
            ->where('therapist_name', $therapist->name)
            ->whereDate('booking_date', $booking->booking_date)
            ->where('time_slot', $booking->time_slot)
            ->count());
        $this->assertSame(1, PaymentLedgerEntry::query()
            ->where('spa_booking_id', $booking->id)
            ->where('entry_type', PaymentLedgerEntry::TYPE_INITIAL_PAYMENT)
            ->count());
    }

    public function test_manual_and_automatic_no_show_processing_mark_once(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = SpaBooking::query()->create([
            'user_id' => $customer->id,
            'client_name' => $customer->name,
            'service_name' => 'Foot Reflexology',
            'booking_date' => now()->subDay()->toDateString(),
            'time_slot' => '10:00 AM',
            'amount' => 70,
            'payment_amount' => 70,
            'payment_method' => PaymentMethodCatalog::METHOD_CASH_COUNTER,
            'payment_status' => PaymentMethodCatalog::STATUS_PAID,
            'session_status' => SpaBooking::STATUS_CONFIRMED,
        ]);

        $results = $this->runWorkflowWorkers('no-show', $booking->id, ['manual', 'automatic']);

        $this->assertSame(['marked', 'stale'], $results);
        $this->assertSame(SpaBooking::STATUS_NO_SHOW, $booking->fresh()->session_status);
    }

    public function test_manual_and_automatic_completion_create_one_transaction(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = SpaBooking::query()->create([
            'user_id' => $customer->id,
            'client_name' => $customer->name,
            'service_name' => 'Foot Reflexology',
            'booking_date' => now()->toDateString(),
            'time_slot' => now()->subMinutes(2)->format('g:i A'),
            'duration_minutes' => 1,
            'amount' => 70,
            'payment_amount' => 70,
            'payment_method' => PaymentMethodCatalog::METHOD_CASH_COUNTER,
            'payment_status' => PaymentMethodCatalog::STATUS_PAID,
            'session_status' => SpaBooking::STATUS_IN_SESSION,
            'session_started_at' => now()->subMinutes(2),
        ]);

        $this->runWorkflowWorkers('completion', $booking->id, ['manual', 'automatic']);

        $this->assertSame(SpaBooking::STATUS_COMPLETED, $booking->fresh()->session_status);
        $this->assertSame(1, DB::table('transactions')->where('spa_booking_id', $booking->id)->count());
    }

    public function test_membership_activation_replay_preserves_one_activation(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $purchase = MembershipPurchase::query()->create([
            'user_id' => $customer->id,
            'plan_name' => 'Buenos Touché Membership',
            'validity_days' => 365,
            'amount' => 499,
            'payment_status' => PaymentMethodCatalog::STATUS_PENDING,
            'status' => MembershipPurchase::STATUS_PENDING,
        ]);

        $this->runWorkflowWorkers('membership', $purchase->id, ['first', 'second']);

        $purchase->refresh();
        $this->assertSame(PaymentMethodCatalog::STATUS_PAID, $purchase->payment_status);
        $this->assertContains($purchase->payment_transaction_id, ['pay_pg_membership_first', 'pay_pg_membership_second']);
        $this->assertEquals(365, $purchase->starts_at->diffInDays($purchase->expires_at));
    }

    public function test_concurrent_system_setting_updates_leave_one_complete_rule_set(): void
    {
        $admins = collect([1, 2])->map(fn (): User => User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->runWorkflowWorkers('settings', $admins->first()->id, ['a', 'b']);

        $settings = app(SiteSettingsService::class)->systemRules();
        $this->assertContains(
            [$settings['payment_hold_minutes'], $settings['customer_minimum_lead_minutes'], $settings['no_show_restriction_threshold']],
            [[10, 40, 2], [20, 60, 4]],
        );
        $this->assertSame(2, DB::table('activity_logs')->where('action', 'system.settings_updated')->count());
    }

    public function test_staff_cancellation_and_actual_session_start_cannot_both_commit(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);
        $therapist = Therapist::create(['therapist_code' => 'PG-CANCEL-START', 'name' => 'Cancellation Race Therapist', 'status' => 'available', 'work_on_off_day' => true, 'is_active' => true]);
        $booking = SpaBooking::query()->create([
            'user_id' => $customer->id,
            'client_name' => $customer->name,
            'service_name' => 'Foot Reflexology',
            'therapist_name' => $therapist->name,
            'booking_date' => now()->toDateString(),
            'time_slot' => now()->format('g:i A'),
            'amount' => 70,
            'payment_amount' => 70,
            'payment_method' => PaymentMethodCatalog::METHOD_CASH_COUNTER,
            'payment_status' => PaymentMethodCatalog::STATUS_PAID,
            'session_status' => SpaBooking::STATUS_CONFIRMED,
        ]);

        $results = $this->runWorkflowWorkers('cancel-start', $booking->id, ['cancel', 'start']);

        $this->assertContains($results, [['cancelled', 'stale'], ['stale', 'started']]);
        $booking->refresh();
        $this->assertFalse($booking->cancelled_at !== null && $booking->session_started_at !== null);
        if ($booking->session_started_at !== null) {
            $this->assertDatabaseMissing('booking_refunds', ['spa_booking_id' => $booking->id]);
        }
    }

    public function test_simultaneous_reconciliation_requests_accept_only_one_observed_dispute_version(): void
    {
        $customer = User::factory()->create();
        $booking = SpaBooking::query()->create([
            'user_id' => $customer->id, 'client_name' => $customer->name,
            'service_name' => 'Massage', 'booking_date' => now()->addDay()->toDateString(),
            'time_slot' => '10:00 AM', 'amount' => 100,
        ]);
        $staff = collect([User::ROLE_ADMIN, User::ROLE_RECEPTIONIST])->map(fn (string $role): User => User::factory()->create(['role' => $role]));
        $record = RefundConfirmation::query()->create([
            'spa_booking_id' => $booking->id, 'confirmed_by' => $staff->first()->id,
            'amount' => 25, 'method' => 'cash', 'recipient' => $customer->name,
            'evidence_mime' => 'application/pdf', 'evidence' => base64_encode('%PDF-1.4'),
            'confirmed_at' => now(), 'disputed_at' => now(), 'dispute_note' => 'Not received.',
        ]);
        $version = $record->disputeVersion();
        $results = $this->runWorkflowWorkers('refund-dispute', $record->id, $staff->map(fn (User $user): string => $user->id.':'.$version)->all());
        $this->assertSame(['resolved', 'stale'], $results);
        $this->assertNotNull($record->fresh()->resolved_at);
        $this->assertSame(1, DB::table('activity_logs')->where('action', 'refund.dispute.resolve')->count());
        $this->assertDatabaseCount('payment_ledger_entries', 0);
    }

    /** @param list<string> $actors
     * @return list<string>
     */
    public function test_concurrent_gateway_requests_reserve_one_refund_only(): void
    {
        $booking = $this->gatewayRefundBooking(100);
        $this->assertSame(['accepted', 'accepted'], $this->runWorkflowWorkers('refund-request', $booking->id, ['a', 'b']));
        $this->assertSame(1, $booking->refunds()->count());
        $this->assertSame(100.0, (float) $booking->refunds()->sum('amount'));
        $this->assertSame('pending', $booking->fresh()->refund_status);
        $this->assertSame(0, PaymentLedgerEntry::where('entry_type', 'refund')->count());
    }

    public function test_concurrent_distinct_refund_updates_preserve_records_summary_and_source_limit(): void
    {
        $booking = $this->gatewayRefundBooking(100);
        $this->runWorkflowWorkers('refund-update', $booking->id, ['a', 'b']);
        $this->assertSame(2, $booking->refunds()->count());
        $this->assertSame(60.0, (float) $booking->fresh()->refund_amount);
        $this->assertSame(60.0, (float) PaymentLedgerEntry::where('entry_type', 'refund')->sum('amount'));
        $this->runWorkflowWorkers('refund-update', $booking->id, ['c', 'd']);
        $this->assertSame(3, $booking->refunds()->count());
        $this->assertSame(90.0, (float) $booking->fresh()->refund_amount);
        $this->assertSame(90.0, (float) PaymentLedgerEntry::where('entry_type', 'refund')->sum('amount'));
    }

    public function test_competing_immediate_walk_in_actions_create_one_booking_collection_and_start(): void
    {
        User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);
        $clients = [User::factory()->create(['role' => User::ROLE_USER]), User::factory()->create(['role' => User::ROLE_USER])];
        $therapist = Therapist::create(['therapist_code' => 'PG-WALK-IN', 'name' => 'Walk In Race Therapist', 'status' => 'available', 'work_on_off_day' => true, 'is_active' => true]);
        $this->assertSame(['created', 'stale'], $this->runWorkflowWorkers('walk-in', $therapist->id, array_map(fn ($client) => (string) $client->id, $clients)));
        $booking = SpaBooking::where('therapist_name', $therapist->name)->sole();
        $this->assertSame('in_session', $booking->session_status);
        $this->assertSame('13:00', $booking->session_started_at->format('H:i'));
        $this->assertSame(1, PaymentLedgerEntry::where('spa_booking_id', $booking->id)->count());
        $this->assertSame((float) $booking->amount, (float) PaymentLedgerEntry::where('spa_booking_id', $booking->id)->sum('amount'));
        $this->assertSame(1, DB::table('activity_logs')->where('subject_id', $booking->id)->where('action', 'session.started')->count());
    }

    private function gatewayRefundBooking(float $amount): SpaBooking
    {
        $client = User::factory()->create(['role' => User::ROLE_USER]);

        return SpaBooking::create([
            'user_id' => $client->id, 'client_name' => $client->name, 'service_name' => 'Foot Reflexology',
            'booking_date' => now()->addDay()->toDateString(), 'time_slot' => '10:00 AM',
            'amount' => $amount, 'payment_amount' => $amount, 'payment_method' => 'paymongo',
            'payment_transaction_id' => 'pay_pg_refund', 'payment_status' => 'paid',
            'cancelled_at' => now(), 'session_status' => 'cancelled',
        ]);
    }

    private function runWorkflowWorkers(string $action, int $recordId, array $actors): array
    {
        $barrier = storage_path('framework/testing/workflow-'.bin2hex(random_bytes(8)));
        $environment = array_filter(array_merge($_SERVER, $_ENV, [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => (string) config('database.connections.pgsql.host'),
            'DB_PORT' => (string) config('database.connections.pgsql.port'),
            'DB_DATABASE' => (string) config('database.connections.pgsql.database'),
            'DB_USERNAME' => (string) config('database.connections.pgsql.username'),
            'DB_PASSWORD' => (string) config('database.connections.pgsql.password'),
        ]), static fn (mixed $value): bool => is_scalar($value));
        $phpCommand = [PHP_BINARY];
        $processes = collect($actors)->map(fn (string $actor): Process => new Process(array_merge($phpCommand, [
            base_path('tests/Support/postgres-workflow-worker.php'),
            $action,
            (string) $recordId,
            $actor,
            $barrier,
        ]), base_path(), $environment, null, 30))->all();

        try {
            foreach ($processes as $process) {
                $process->start();
            }
            usleep(250000);
            touch($barrier);
            foreach ($processes as $process) {
                $process->wait();
            }

            foreach ($processes as $process) {
                $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            }

            return collect($processes)
                ->map(fn (Process $process): string => trim($process->getOutput()))
                ->sort()->values()->all();
        } finally {
            @unlink($barrier);
        }
    }
}
