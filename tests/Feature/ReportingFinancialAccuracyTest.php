<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\CustomerNotification;
use App\Models\MembershipPlan;
use App\Models\MembershipPurchase;
use App\Models\PaymentLedgerEntry;
use App\Models\SpaBooking;
use App\Models\SpaService;
use App\Models\User;
use App\Services\NoShowService;
use App\Services\PaymentLedgerService;
use App\Services\SpaSessionService;
use App\Support\PaymentMethodCatalog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportingFinancialAccuracyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_report_uses_payment_time_deducts_refunds_counts_customers_once_and_includes_packages(): void
    {
        Carbon::setTestNow('2026-09-27 18:00:00');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'created_at' => '2026-09-27 09:00:00']);
        $customer = User::factory()->create(['role' => User::ROLE_USER, 'created_at' => '2026-09-27 09:00:00']);

        DB::table('customers')->insert([
            'customer_id' => 'CUS-REPORT',
            'full_name' => $customer->name,
            'email' => $customer->email,
            'password' => bcrypt('password'),
            'created_at' => '2026-09-27 09:00:00',
            'updated_at' => '2026-09-27 09:00:00',
        ]);
        DB::table('receptionists')->insert([
            'receptionist_id' => 'REC-REPORT',
            'full_name' => 'Report Receptionist',
            'username' => 'report_receptionist',
            'email' => 'report-receptionist@example.com',
            'shift' => '—',
            'created_at' => '2026-09-27 09:00:00',
            'updated_at' => '2026-09-27 09:00:00',
        ]);

        $package = SpaService::query()->where('offering_type', 'package')->firstOrFail();
        $booking = SpaBooking::query()->create([
            'user_id' => $customer->id,
            'client_name' => $customer->name,
            'service_name' => $package->name,
            'therapist_name' => 'Angela Fernandez',
            'booking_date' => '2026-10-10',
            'time_slot' => '10:00 AM',
            'amount' => 300,
        ]);

        PaymentLedgerEntry::query()->create([
            'spa_booking_id' => $booking->id,
            'entry_type' => PaymentLedgerEntry::TYPE_INITIAL_PAYMENT,
            'amount' => 200,
            'payment_method' => 'gcash',
            'reference' => 'pay-report-1',
            'occurred_at' => '2026-09-27 10:00:00',
        ]);
        PaymentLedgerEntry::query()->create([
            'spa_booking_id' => $booking->id,
            'entry_type' => PaymentLedgerEntry::TYPE_REFUND,
            'amount' => 50,
            'payment_method' => 'gcash',
            'reference' => 'refund-report-1',
            'occurred_at' => '2026-09-27 11:00:00',
        ]);

        MembershipPlan::query()->create([
            'name' => 'Informational Only Membership',
            'price_amount' => 999,
            'description' => 'No purchase workflow.',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->getJson(route('reporting.data', [
            'period' => 'daily',
            'period_value' => '2026-09-27',
        ]));

        $response->assertOk()
            ->assertJsonPath('primaryAmount', 150)
            ->assertJsonPath('grossCollections', 200)
            ->assertJsonPath('refundTotal', 50)
            ->assertJsonPath('secondaryUserCount', 1)
            ->assertJsonCount(2, 'ledgerRows');

        $payload = $response->json();
        $packageIndex = array_search($package->name, $payload['serviceLabels'], true);
        $this->assertNotFalse($packageIndex);
        $this->assertSame(150, (int) $payload['serviceTotals'][$packageIndex]);
        $this->assertNotContains('Informational Only Membership', $payload['serviceLabels']);
        $this->assertSame(200, (int) $payload['trendData'][10]);
        $this->assertSame(-50, (int) $payload['trendData'][11]);
    }

    public function test_admin_dashboard_chart_uses_collections_memberships_and_refunds(): void
    {
        Carbon::setTestNow('2026-10-04 12:00:00');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = SpaBooking::query()->create([
            'user_id' => $customer->id,
            'client_name' => $customer->name,
            'service_name' => 'Aromatherapy',
            'therapist_name' => 'Liza Reyes',
            'booking_date' => '2026-10-04',
            'time_slot' => '10:00 AM',
            'amount' => 300,
        ]);

        PaymentLedgerEntry::query()->create([
            'spa_booking_id' => $booking->id,
            'entry_type' => PaymentLedgerEntry::TYPE_INITIAL_PAYMENT,
            'amount' => 200,
            'occurred_at' => '2026-10-03 10:00:00',
        ]);
        PaymentLedgerEntry::query()->create([
            'spa_booking_id' => $booking->id,
            'entry_type' => PaymentLedgerEntry::TYPE_REFUND,
            'amount' => 50,
            'occurred_at' => '2026-10-03 11:00:00',
        ]);
        MembershipPurchase::query()->create([
            'user_id' => $customer->id,
            'plan_name' => 'Annual Membership',
            'validity_days' => 365,
            'amount' => 25,
            'payment_status' => PaymentMethodCatalog::STATUS_PAID,
            'status' => MembershipPurchase::STATUS_ACTIVE,
            'paid_at' => '2026-10-03 12:00:00',
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('7-day net sales')
            ->assertSee('data-sales-date="2026-10-03"', false)
            ->assertSee('data-sales-amount="175.00"', false)
            ->assertSee('Collected payments and memberships, less refunds');
    }

    public function test_excel_workbook_is_formatted_and_contains_complete_payment_ledger(): void
    {
        Carbon::setTestNow('2026-09-27 18:00:00');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $client = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = SpaBooking::query()->create([
            'user_id' => $client->id,
            'client_name' => 'Ledger Client', 'service_name' => 'THERA #1', 'therapist_name' => 'Erica Tamondong',
            'booking_date' => '2026-10-10', 'time_slot' => '10:00 AM', 'amount' => 399,
            'session_status' => SpaBooking::STATUS_NO_SHOW,
        ]);
        PaymentLedgerEntry::query()->create([
            'spa_booking_id' => $booking->id,
            'entry_type' => PaymentLedgerEntry::TYPE_INITIAL_PAYMENT,
            'amount' => 399,
            'payment_method' => 'cash_counter',
            'reference' => 'COT-LEDGER-1',
            'occurred_at' => '2026-09-27 12:00:00',
        ]);

        $response = $this->actingAs($admin)->get(route('reporting.export', [
            'period' => 'daily', 'period_value' => '2026-09-27',
        ]))->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $workbook = $response->streamedContent();
        $this->assertStringStartsWith('PK', $workbook);
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));

        $temporaryBase = tempnam(sys_get_temp_dir(), 'report-test-');
        $archivePath = $temporaryBase.'.zip';
        @unlink($temporaryBase);
        file_put_contents($archivePath, $workbook);
        try {
            $archive = new \PharData($archivePath);
            $workbookXml = $archive['xl/workbook.xml']->getContent();
            $summaryXml = $archive['xl/worksheets/sheet1.xml']->getContent();
            $ledgerXml = $archive['xl/worksheets/sheet2.xml']->getContent();
            $stylesXml = $archive['xl/styles.xml']->getContent();

            $this->assertNotFalse(simplexml_load_string($workbookXml));
            $this->assertNotFalse(simplexml_load_string($summaryXml));
            $this->assertNotFalse(simplexml_load_string($ledgerXml));
            $this->assertStringContainsString('Report Summary', $workbookXml);
            $this->assertStringContainsString('Payment Ledger', $workbookXml);
            $this->assertStringContainsString('state="frozen"', $summaryXml);
            $this->assertStringContainsString('width="28"', $summaryXml);
            $this->assertStringContainsString('No-show fee sales', $summaryXml);
            $this->assertStringContainsString('COT-LEDGER-1', $ledgerXml);
            $this->assertStringContainsString('Ledger Client', $ledgerXml);
            $this->assertStringContainsString('THERA #1', $ledgerXml);
            $this->assertStringContainsString('No-show fee (initial payment)', $ledgerXml);
            $this->assertStringContainsString('formatCode="&quot;PHP &quot;#,##0.00', $stylesXml);
        } finally {
            unset($archive);
            @unlink($archivePath);
        }
    }

    public function test_no_show_payments_are_reported_as_fees_and_unpaid_balances_are_not_collectible(): void
    {
        Carbon::setTestNow('2026-10-04 18:00:00');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $customer = User::factory()->create(['role' => User::ROLE_USER]);

        $downpaymentBooking = SpaBooking::query()->create([
            'user_id' => $customer->id,
            'client_name' => $customer->name,
            'service_name' => 'Swedish Massage',
            'therapist_name' => 'Liza Reyes',
            'booking_date' => '2026-10-04',
            'time_slot' => '10:00 AM',
            'amount' => 100,
            'payment_amount' => 50,
            'payment_method' => PaymentMethodCatalog::METHOD_PAYMONGO,
            'payment_status' => PaymentMethodCatalog::STATUS_PAID,
            'session_status' => SpaBooking::STATUS_NO_SHOW,
        ]);
        $fullPaymentBooking = SpaBooking::query()->create([
            'user_id' => $customer->id,
            'client_name' => $customer->name,
            'service_name' => 'Aromatherapy',
            'therapist_name' => 'Juan dela Cruz',
            'booking_date' => '2026-10-04',
            'time_slot' => '01:00 PM',
            'amount' => 100,
            'payment_amount' => 100,
            'payment_method' => PaymentMethodCatalog::METHOD_PAYMONGO,
            'payment_status' => PaymentMethodCatalog::STATUS_PAID,
            'session_status' => SpaBooking::STATUS_NO_SHOW,
        ]);

        foreach ([[$downpaymentBooking, 50, 'pay-no-show-half'], [$fullPaymentBooking, 100, 'pay-no-show-full']] as [$booking, $amount, $reference]) {
            PaymentLedgerEntry::query()->create([
                'spa_booking_id' => $booking->id,
                'entry_type' => PaymentLedgerEntry::TYPE_INITIAL_PAYMENT,
                'amount' => $amount,
                'payment_method' => PaymentMethodCatalog::METHOD_PAYMONGO,
                'reference' => $reference,
                'occurred_at' => now(),
            ]);
        }
        PaymentLedgerEntry::query()->create([
            'spa_booking_id' => $fullPaymentBooking->id,
            'entry_type' => PaymentLedgerEntry::TYPE_REFUND,
            'amount' => 20,
            'payment_method' => PaymentMethodCatalog::METHOD_PAYMONGO,
            'reference' => 'refund-no-show-exception',
            'occurred_at' => now(),
        ]);

        $response = $this->actingAs($admin)->getJson(route('reporting.data', [
            'period' => 'daily',
            'period_value' => '2026-10-04',
        ]));

        $response->assertOk()
            ->assertJsonPath('grossCollections', 150)
            ->assertJsonPath('refundTotal', 20)
            ->assertJsonPath('primaryAmount', 130)
            ->assertJsonPath('noShowFeeRevenue', 130)
            ->assertJsonPath('outstandingBalanceTotal', 0)
            ->assertJsonPath('outstandingBalanceCount', 0);

        $payload = $response->json();
        $feeIndex = array_search('No-show fee sales', $payload['serviceLabels'], true);
        $this->assertNotFalse($feeIndex);
        $this->assertSame(130, (int) $payload['serviceTotals'][$feeIndex]);
        $this->assertCount(2, collect($payload['ledgerRows'])->where('type', 'no_show_fee'));
        $this->assertCount(1, collect($payload['ledgerRows'])->where('type', PaymentLedgerEntry::TYPE_REFUND));
        $this->assertTrue(collect($payload['ledgerRows'])->where('type', 'no_show_fee')->every(
            fn (array $entry): bool => str_starts_with($entry['typeLabel'], 'No-show fee'),
        ));
    }

    public function test_automatic_no_show_and_admin_correction_keep_availability_and_sales_connected(): void
    {
        Carbon::setTestNow('2026-10-04 10:15:00');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = SpaBooking::query()->create([
            'user_id' => $customer->id,
            'client_name' => $customer->name,
            'booking_source' => SpaBooking::SOURCE_ONLINE,
            'service_name' => 'Swedish Massage',
            'therapist_name' => 'Liza Reyes',
            'booking_date' => '2026-10-04',
            'time_slot' => '10:00 AM',
            'duration_minutes' => 60,
            'amount' => 100,
            'payment_amount' => 50,
            'payment_method' => PaymentMethodCatalog::METHOD_PAYMONGO,
            'payment_status' => PaymentMethodCatalog::STATUS_PAID,
            'session_status' => SpaBooking::STATUS_CONFIRMED,
        ]);
        PaymentLedgerEntry::query()->create([
            'spa_booking_id' => $booking->id,
            'entry_type' => PaymentLedgerEntry::TYPE_INITIAL_PAYMENT,
            'amount' => 50,
            'payment_method' => PaymentMethodCatalog::METHOD_PAYMONGO,
            'reference' => 'pay-auto-no-show',
            'occurred_at' => '2026-10-04 09:00:00',
        ]);

        $this->assertTrue(SpaBooking::query()->blocksAvailability()->whereKey($booking)->exists());
        $this->artisan('appointments:process-no-shows')->assertSuccessful();
        $this->assertSame(SpaBooking::STATUS_NO_SHOW, $booking->fresh()->session_status);
        $this->assertFalse(SpaBooking::query()->blocksAvailability()->whereKey($booking)->exists());

        $reportRoute = route('reporting.data', ['period' => 'daily', 'period_value' => '2026-10-04']);
        $this->actingAs($admin)->getJson($reportRoute)
            ->assertOk()
            ->assertJsonPath('noShowFeeRevenue', 50)
            ->assertJsonPath('outstandingBalanceTotal', 0)
            ->assertJsonPath('ledgerRows.0.type', 'no_show_fee');

        $this->actingAs($admin)
            ->patch(route('appointments.no-show.reverse', $booking))
            ->assertRedirect();
        $this->assertSame(SpaBooking::STATUS_CONFIRMED, $booking->fresh()->session_status);
        $this->assertTrue(SpaBooking::query()->blocksAvailability()->whereKey($booking)->exists());

        $correctedReport = $this->actingAs($admin)->getJson($reportRoute)->assertOk();
        $correctedReport->assertJsonPath('noShowFeeRevenue', 0)
            ->assertJsonPath('outstandingBalanceTotal', 50)
            ->assertJsonPath('ledgerRows.0.type', PaymentLedgerEntry::TYPE_INITIAL_PAYMENT);
        $serviceIndex = array_search('Swedish Massage', $correctedReport->json('serviceLabels'), true);
        $this->assertNotFalse($serviceIndex);
        $this->assertSame(50, (int) $correctedReport->json("serviceTotals.{$serviceIndex}"));

        $notificationCount = CustomerNotification::query()->count();
        $activityCount = ActivityLog::query()->count();
        Carbon::setTestNow('2026-10-04 10:16:00');
        $this->assertNull(app(NoShowService::class)->record($booking->fresh()));
        $this->assertFalse(app(SpaSessionService::class)->toAppointmentRow($booking->fresh())['can_mark_no_show']);
        $this->artisan('appointments:process-no-shows')
            ->expectsOutput('Processed 0 automatic no-show appointment(s).')
            ->assertSuccessful();

        $booking->refresh();
        $this->assertSame(SpaBooking::STATUS_CONFIRMED, $booking->session_status);
        $this->assertNotNull($booking->no_show_reversed_at);
        $this->assertSame($notificationCount, CustomerNotification::query()->count());
        $this->assertSame($activityCount, ActivityLog::query()->count());
        $this->assertSame(0, SpaBooking::query()
            ->where('user_id', $customer->id)
            ->where('session_status', SpaBooking::STATUS_NO_SHOW)
            ->count());

        $stableReport = $this->actingAs($admin)->getJson($reportRoute)->assertOk();
        $stableReport->assertJsonPath('noShowFeeRevenue', 0)
            ->assertJsonPath('outstandingBalanceTotal', 50)
            ->assertJsonPath('ledgerRows.0.type', PaymentLedgerEntry::TYPE_INITIAL_PAYMENT);
    }

    public function test_payment_ledger_recording_is_idempotent_and_preserves_collection_time(): void
    {
        Carbon::setTestNow('2026-09-27 10:00:00');
        $client = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = SpaBooking::query()->create([
            'user_id' => $client->id,
            'client_name' => $client->name,
            'service_name' => 'Swedish Massage',
            'therapist_name' => 'Liza Reyes',
            'booking_date' => '2026-10-10',
            'time_slot' => '10:00 AM',
            'amount' => 100,
            'payment_amount' => 50,
            'payment_method' => 'gcash',
            'payment_transaction_id' => 'pay-idempotent',
            'payment_status' => PaymentMethodCatalog::STATUS_PAID,
        ]);

        $ledger = app(PaymentLedgerService::class);
        $ledger->recordInitialPayment($booking);
        Carbon::setTestNow('2026-09-27 12:00:00');
        $ledger->recordInitialPayment($booking);

        $this->assertDatabaseCount('payment_ledger_entries', 1);
        $entry = PaymentLedgerEntry::query()->firstOrFail();
        $this->assertSame('2026-09-27 10:00:00', $entry->occurred_at->format('Y-m-d H:i:s'));
    }

    public function test_custom_report_includes_memberships_reconciliation_and_outstanding_balances(): void
    {
        Carbon::setTestNow('2026-09-30 18:00:00');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $plan = MembershipPlan::query()->firstOrFail();
        MembershipPurchase::query()->create([
            'user_id' => $customer->id,
            'membership_plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'validity_days' => 365,
            'amount' => 499,
            'payment_method' => 'gcash',
            'payment_status' => PaymentMethodCatalog::STATUS_PAID,
            'status' => MembershipPurchase::STATUS_ACTIVE,
            'payment_transaction_id' => 'pay_membership_report',
            'paid_at' => '2026-09-28 10:00:00',
            'starts_at' => '2026-09-28 10:00:00',
            'expires_at' => '2027-09-28 10:00:00',
        ]);
        SpaBooking::query()->create([
            'user_id' => $customer->id,
            'client_name' => $customer->name,
            'service_name' => 'THERA #2',
            'therapist_name' => 'Erica Tamondong',
            'booking_date' => '2026-09-29',
            'time_slot' => '10:00 AM',
            'amount' => 599,
            'payment_amount' => 299.50,
            'payment_method' => 'cash_counter',
            'payment_status' => PaymentMethodCatalog::STATUS_PAID,
        ]);

        $response = $this->actingAs($admin)->getJson(route('reporting.data', [
            'period' => 'custom',
            'date_from' => '2026-09-27',
            'date_to' => '2026-09-30',
        ]));

        $response->assertOk()
            ->assertJsonPath('period', 'custom')
            ->assertJsonPath('grossCollections', 499)
            ->assertJsonPath('membershipCollections', 499)
            ->assertJsonPath('paymentCount', 1)
            ->assertJsonPath('averagePayment', 499)
            ->assertJsonPath('outstandingBalanceTotal', 299.5)
            ->assertJsonPath('outstandingBalanceCount', 1)
            ->assertJsonPath('ledgerRows.0.type', 'membership_payment')
            ->assertJsonPath('paymentMethodBreakdown.0.method', 'GCash');
    }

    public function test_custom_report_clamps_a_future_only_range_to_today(): void
    {
        Carbon::setTestNow('2026-09-30 18:00:00');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->getJson(route('reporting.data', [
            'period' => 'custom',
            'date_from' => '2026-10-01',
            'date_to' => '2026-10-31',
        ]))
            ->assertOk()
            ->assertJsonPath('dateFrom', '2026-09-30')
            ->assertJsonPath('dateTo', '2026-09-30');
    }
}
