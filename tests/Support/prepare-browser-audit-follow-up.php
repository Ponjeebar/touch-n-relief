<?php

use App\Models\BookingRefund;
use App\Models\SpaBooking;
use App\Models\Therapist;
use App\Models\User;
use App\Services\GatewayPaymentReconciliationService;
use App\Services\PaymentLedgerService;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';
putenv('APP_ENV=e2e');
$_ENV['APP_ENV'] = 'e2e';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$expected = realpath(base_path('database/browser-tests.sqlite'));
$actual = realpath((string) config('database.connections.sqlite.database'));
if (! $app->environment('e2e') || config('database.default') !== 'sqlite' || ! $expected || $actual !== $expected) {
    throw new RuntimeException('Audit browser fixtures require the isolated browser database.');
}
if (Therapist::where('therapist_code', 'AUDIT-LINKED')->exists()) {
    exit(0);
}
Carbon::setTestNow('2024-10-19 13:00:00');
$client = User::factory()->create(['role' => User::ROLE_USER, 'name' => 'Audit Browser Client', 'email' => 'audit-follow-up@browser.test']);
$therapist = Therapist::create(['therapist_code' => 'AUDIT-LINKED', 'name' => 'Audit Linked Therapist', 'is_active' => true, 'status' => 'available', 'work_on_off_day' => true, 'specializations' => ['Swedish Massage']]);
$booking = SpaBooking::create(['user_id' => $client->id, 'client_name' => $client->name, 'service_name' => 'Audit Refund Service', 'therapist_name' => $therapist->name, 'booking_date' => now()->toDateString(), 'time_slot' => '1:00 PM', 'duration_minutes' => 60, 'amount' => 100, 'payment_amount' => 100, 'payment_method' => 'paymongo', 'payment_status' => 'paid', 'payment_transaction_id' => 'pay_audit_browser_original', 'session_status' => 'cancelled', 'cancelled_at' => now()]);
$ledger = app(PaymentLedgerService::class);
$ledger->recordInitialPayment($booking, now()->subMonth());
$refund = BookingRefund::create(['spa_booking_id' => $booking->id, 'payment_component' => 'initial_payment', 'processing_channel' => 'manual', 'payment_method' => 'paymongo', 'amount' => 100, 'status' => 'processed', 'reference' => 'AUDIT-BROWSER-REFUND', 'requested_at' => now(), 'processed_at' => now()]);
$ledger->recordRefund($refund);
app(GatewayPaymentReconciliationService::class)->record($booking, 'pay_audit_browser_extra', ['attributes' => ['amount' => 10000, 'currency' => 'PHP']]);
