<?php

namespace Tests\Feature;

use App\Models\MembershipPlan;
use App\Models\MembershipPurchase;
use App\Models\PaymentLedgerEntry;
use App\Models\SpaBooking;
use App\Models\User;
use App\Services\BackupRecoveryService;
use App\Services\GatewayPaymentReconciliationService;
use App\Services\MembershipPurchaseService;
use App\Services\ReportingSpreadsheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GatewayPaymentReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_distinct_paid_references_are_retained_once_without_changing_allocated_sales_or_refunds(): void
    {
        config(['services.paymongo.webhook_secret' => 'audit-secret', 'services.paymongo.secret_key' => 'sk_test_audit']);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['data' => ['attributes' => ['source' => ['type' => 'gcash']]]])]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $client = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = SpaBooking::create(['user_id' => $client->id, 'client_name' => $client->name, 'service_name' => 'Swedish Massage', 'therapist_name' => 'Audit Therapist', 'booking_date' => now()->addDay()->toDateString(), 'time_slot' => '1:00 PM', 'duration_minutes' => 60, 'amount' => 100, 'payment_amount' => 100, 'payment_method' => 'paymongo', 'payment_status' => 'pending', 'booking_source' => 'online', 'session_status' => 'confirmed']);
        foreach (['pay_audit_first', 'pay_audit_extra', 'pay_audit_extra'] as $reference) {
            $payload = ['data' => ['attributes' => ['type' => 'payment.paid', 'data' => ['id' => $reference, 'attributes' => ['status' => 'paid', 'amount' => 10000, 'currency' => 'PHP', 'metadata' => ['booking_id' => $booking->id]]]]]];
            $time = (string) time();
            $signature = 't='.$time.',te='.hash_hmac('sha256', $time.'.'.json_encode($payload), 'audit-secret');
            $this->postJson(route('paymongo.webhook'), $payload, ['Paymongo-Signature' => $signature])->assertOk();
        }
        $payload['data']['attributes']['data']['id'] = 'pay_audit_first';
        $payload['data']['attributes']['data']['attributes']['amount'] = 9900;
        $time = (string) time();
        $signature = 't='.$time.',te='.hash_hmac('sha256', $time.'.'.json_encode($payload), 'audit-secret');
        $this->postJson(route('paymongo.webhook'), $payload, ['Paymongo-Signature' => $signature])->assertOk();
        $this->assertSame('pay_audit_first', $booking->fresh()->payment_transaction_id);
        $this->assertSame(1, PaymentLedgerEntry::where('spa_booking_id', $booking->id)->count());
        $this->assertSame(100.0, (float) PaymentLedgerEntry::sum('amount'));
        $this->assertDatabaseCount('gateway_payment_reconciliations', 1);
        $this->assertDatabaseHas('gateway_payment_reconciliations', ['gateway_payment_id' => 'pay_audit_extra', 'spa_booking_id' => $booking->id, 'amount' => 100, 'status' => 'open']);
        $this->assertDatabaseCount('booking_refunds', 0);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseHas('activity_logs', ['action' => 'payment.reconciliation_required']);
        $this->assertSame(1, DB::table('staff_notifications')->where('staff_user_id', $admin->id)->where('title', 'Extra payment requires review')->count());
        $report = $this->actingAs($admin)->getJson(route('reporting.data', ['period' => 'monthly']))->assertOk()->assertJsonPath('primaryAmount', 100)->assertJsonPath('unallocatedGatewayAmount', 100)->json();
        $this->assertSame('pay_audit_extra', $report['gatewayReconciliations'][0]['reference']);
        $backup = json_decode(app(BackupRecoveryService::class)->createJson(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('pay_audit_extra', $backup['tables']['gateway_payment_reconciliations'][0]['gateway_payment_id']);
        $restored = app(BackupRecoveryService::class)->rehearseRestore($backup['tables']);
        $this->assertSame(1, $restored['gateway_payment_reconciliations']);
        $report['salesTrendChart'] = $report['serviceRevenueChart'] = 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg"/>');
        $this->assertStringContainsString('pay_audit_extra', view('reporting.pdf', $report)->render());
        $archivePath = tempnam(sys_get_temp_dir(), 'gateway-report-');
        unlink($archivePath);
        $archivePath .= '.zip';
        file_put_contents($archivePath, app(ReportingSpreadsheetService::class)->build($report));
        try {
            $archive = new \PharData($archivePath);
            $summary = $archive['xl/worksheets/sheet1.xml']->getContent();
            $this->assertStringContainsString('pay_audit_extra', $summary);
            $this->assertStringContainsString('excluded from sales', $summary);
        } finally {
            unset($archive);
            unlink($archivePath);
        }
    }

    public function test_membership_extra_payment_does_not_extend_membership_again(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->create(['role' => User::ROLE_USER]);
        $plan = MembershipPlan::create(['name' => 'Audit Membership', 'price_amount' => 100, 'validity_days' => 30, 'is_active' => true, 'description' => 'Membership test fixture']);
        $purchase = MembershipPurchase::create(['user_id' => $user->id, 'membership_plan_id' => $plan->id, 'plan_name' => $plan->name, 'validity_days' => 30, 'amount' => 100, 'payment_method' => 'paymongo', 'payment_status' => 'pending']);
        $service = app(MembershipPurchaseService::class);
        $service->confirm($purchase, 'pay_membership_original', 'gcash');
        $expiry = $purchase->fresh()->expires_at->toIso8601String();
        foreach (range(1, 2) as $repeat) {
            $service->confirm($purchase->fresh(), 'pay_membership_extra', 'gcash', paymentEvidence: ['attributes' => ['amount' => 10000, 'currency' => 'PHP']]);
        }
        $this->assertSame($expiry, $purchase->fresh()->expires_at->toIso8601String());
        $this->assertSame('pay_membership_original', $purchase->fresh()->payment_transaction_id);
        $this->assertDatabaseCount('gateway_payment_reconciliations', 1);
        $this->assertDatabaseHas('gateway_payment_reconciliations', ['membership_purchase_id' => $purchase->id, 'gateway_payment_id' => 'pay_membership_extra', 'amount' => 100]);
        $this->assertSame(1, DB::table('staff_notifications')->where('staff_user_id', $admin->id)->where('title', 'Extra payment requires review')->count());
        $this->actingAs($admin)->getJson(route('reporting.data', ['period' => 'monthly']))
            ->assertOk()->assertJsonPath('primaryAmount', 100)->assertJsonPath('unallocatedGatewayAmount', 100);
        $this->assertDatabaseCount('booking_refunds', 0);
    }

    public function test_unknown_amounts_and_contradictory_replays_never_replace_original_evidence(): void
    {
        $user = User::factory()->create();
        $booking = SpaBooking::create(['user_id' => $user->id, 'client_name' => $user->name, 'service_name' => 'Swedish Massage', 'booking_date' => now()->toDateString(), 'time_slot' => '1:00 PM', 'amount' => 100, 'payment_amount' => 100, 'payment_status' => 'paid', 'payment_transaction_id' => 'pay_original']);
        $service = app(GatewayPaymentReconciliationService::class);
        $service->record($booking, 'pay_unknown');
        $this->assertDatabaseHas('gateway_payment_reconciliations', ['gateway_payment_id' => 'pay_unknown', 'amount' => null, 'currency' => 'UNK']);
        $service->record($booking, 'pay_known', ['attributes' => ['amount' => 10000, 'currency' => 'PHP']]);
        $other = $booking->replicate();
        $other->save();
        $service->record($other, 'pay_known', ['attributes' => ['amount' => 50000, 'currency' => 'USD']]);
        $this->assertDatabaseCount('gateway_payment_reconciliations', 2);
        $this->assertDatabaseHas('gateway_payment_reconciliations', ['gateway_payment_id' => 'pay_known', 'spa_booking_id' => $booking->id, 'amount' => 100, 'currency' => 'PHP']);
        $this->assertDatabaseCount('payment_ledger_entries', 0);
        $this->assertDatabaseCount('booking_refunds', 0);
    }

    public function test_checkout_reconciliation_uses_only_the_matching_payment_amount(): void
    {
        $user = User::factory()->create();
        $booking = SpaBooking::create(['user_id' => $user->id, 'client_name' => $user->name, 'service_name' => 'Swedish Massage', 'booking_date' => now()->toDateString(), 'time_slot' => '1:00 PM', 'amount' => 100, 'payment_status' => 'paid']);
        app(GatewayPaymentReconciliationService::class)->record($booking, 'pay_matching', ['attributes' => ['payments' => [
            ['id' => 'pay_other', 'attributes' => ['status' => 'paid', 'amount' => 50000, 'currency' => 'USD']],
            ['id' => 'pay_matching', 'attributes' => ['status' => 'paid', 'amount' => 10000, 'currency' => 'PHP']],
        ]]]);
        $this->assertDatabaseHas('gateway_payment_reconciliations', ['gateway_payment_id' => 'pay_matching', 'amount' => 100, 'currency' => 'PHP']);
    }
}
