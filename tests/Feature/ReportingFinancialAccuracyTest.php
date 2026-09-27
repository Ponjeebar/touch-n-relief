<?php

namespace Tests\Feature;

use App\Models\MembershipPlan;
use App\Models\PaymentLedgerEntry;
use App\Models\SpaBooking;
use App\Models\SpaService;
use App\Models\User;
use App\Services\PaymentLedgerService;
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

    public function test_excel_workbook_is_formatted_and_contains_complete_payment_ledger(): void
    {
        Carbon::setTestNow('2026-09-27 18:00:00');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $client = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = SpaBooking::query()->create([
            'user_id' => $client->id,
            'client_name' => 'Ledger Client', 'service_name' => 'THERA #1', 'therapist_name' => 'Erica Tamondong',
            'booking_date' => '2026-10-10', 'time_slot' => '10:00 AM', 'amount' => 399,
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
            $this->assertStringContainsString('COT-LEDGER-1', $ledgerXml);
            $this->assertStringContainsString('Ledger Client', $ledgerXml);
            $this->assertStringContainsString('THERA #1', $ledgerXml);
            $this->assertStringContainsString('formatCode="&quot;PHP &quot;#,##0.00', $stylesXml);
        } finally {
            unset($archive);
            @unlink($archivePath);
        }
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
}
