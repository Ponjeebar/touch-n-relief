<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\BookingRefund;
use App\Models\PaymentLedgerEntry;
use App\Models\SpaBooking;
use App\Models\User;
use App\Services\BookingRefundService;
use App\Services\PaymentLedgerService;
use App\Services\PaymongoService;
use App\Support\PaymentMethodCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BookingRefundAccountingTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_and_balance_collections_are_refunded_as_separate_components(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);
        $booking = $this->fullyCollectedBooking(PaymentMethodCatalog::METHOD_CASH_COUNTER);
        $ledger = app(PaymentLedgerService::class);
        $ledger->recordInitialPayment($booking);
        $ledger->recordBalancePayment($booking);

        $service = app(BookingRefundService::class);
        $service->processRefund($booking);

        $this->assertDatabaseCount('booking_refunds', 2);
        $this->assertEqualsCanonicalizing(
            [BookingRefund::COMPONENT_INITIAL, BookingRefund::COMPONENT_BALANCE],
            $booking->refunds()->pluck('payment_component')->all(),
        );
        $this->assertSame(0, PaymentLedgerEntry::query()->where('entry_type', PaymentLedgerEntry::TYPE_REFUND)->count());

        $service->completeManualRefund($booking->fresh(), 'Returned at the counter.', $staff->id, $this->confirmation(100));
        $booking->refresh();

        $this->assertSame(PaymentMethodCatalog::STATUS_REFUNDED, $booking->payment_status);
        $this->assertSame(100.0, (float) $booking->refund_amount);
        $this->assertSame(100.0, $booking->totalPaidAmount());
        $this->assertSame(0.0, $booking->remainingBalance());
        $this->assertSame(2, PaymentLedgerEntry::query()->where('entry_type', PaymentLedgerEntry::TYPE_REFUND)->count());
        $this->assertSame(100.0, (float) PaymentLedgerEntry::query()->where('entry_type', PaymentLedgerEntry::TYPE_REFUND)->sum('amount'));
        $this->assertSame(2, PaymentLedgerEntry::query()->where('entry_type', PaymentLedgerEntry::TYPE_REFUND)
            ->where('recorded_by', $staff->id)->count());

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->getJson(route('reporting.data', [
            'period' => 'daily',
            'period_value' => now()->toDateString(),
        ]))->assertOk()
            ->assertJsonPath('grossCollections', 100)
            ->assertJsonPath('refundTotal', 100)
            ->assertJsonPath('primaryAmount', 0)
            ->assertJsonCount(4, 'ledgerRows');

        $this->expectException(ValidationException::class);
        $service->completeManualRefund($booking->fresh(), null, $staff->id);
    }

    public function test_partial_paymongo_refunds_are_cumulative_idempotent_and_do_not_create_a_false_balance(): void
    {
        $booking = $this->fullyCollectedBooking(PaymentMethodCatalog::METHOD_PAYMONGO);
        $service = app(BookingRefundService::class);

        $service->applyPaymongoUpdate('ref_partial_1', 'pay_test_refund', 25, BookingRefundService::STATUS_PROCESSED);
        $service->applyPaymongoUpdate('ref_partial_1', 'pay_test_refund', 25, BookingRefundService::STATUS_PROCESSED);
        $booking->refresh();

        $this->assertSame(PaymentMethodCatalog::STATUS_PAID, $booking->payment_status);
        $this->assertSame(25.0, (float) $booking->refund_amount);
        $this->assertSame('Partially refunded', $service->labelFor($booking->refund_status, $booking));
        $this->assertSame(100.0, $booking->totalPaidAmount());
        $this->assertSame(0.0, $booking->remainingBalance());
        $this->assertSame(1, BookingRefund::query()->where('reference', 'ref_partial_1')->count());
        $this->assertSame(1, PaymentLedgerEntry::query()->where('entry_type', PaymentLedgerEntry::TYPE_REFUND)->count());

        $service->applyPaymongoUpdate('ref_partial_2', 'pay_test_refund', 25, BookingRefundService::STATUS_PROCESSED);
        $booking->refresh();

        $this->assertSame(PaymentMethodCatalog::STATUS_PAID, $booking->payment_status);
        $this->assertSame(50.0, (float) $booking->refund_amount);
        $this->assertSame(2, BookingRefund::query()->where('spa_booking_id', $booking->id)->count());
        $this->assertSame(2, PaymentLedgerEntry::query()->where('entry_type', PaymentLedgerEntry::TYPE_REFUND)->count());

        $service->processRefund($booking);
        $this->assertSame(1, BookingRefund::query()
            ->where('payment_component', BookingRefund::COMPONENT_BALANCE)
            ->where('processing_channel', BookingRefund::CHANNEL_MANUAL)
            ->where('amount', 50)
            ->count());
    }

    public function test_pending_and_failed_refunds_do_not_reduce_sales_and_excess_refunds_are_rejected(): void
    {
        $booking = $this->fullyCollectedBooking(PaymentMethodCatalog::METHOD_PAYMONGO);
        $service = app(BookingRefundService::class);

        $service->applyPaymongoUpdate('ref_pending', 'pay_test_refund', 25, BookingRefundService::STATUS_PENDING);
        $this->assertSame(0, PaymentLedgerEntry::query()->where('entry_type', PaymentLedgerEntry::TYPE_REFUND)->count());

        $service->applyPaymongoUpdate('ref_pending', 'pay_test_refund', 25, BookingRefundService::STATUS_FAILED);
        $this->assertSame(0, PaymentLedgerEntry::query()->where('entry_type', PaymentLedgerEntry::TYPE_REFUND)->count());

        $service->applyPaymongoUpdate('ref_excess', 'pay_test_refund', 60, BookingRefundService::STATUS_PROCESSED);
        $this->assertDatabaseMissing('booking_refunds', ['reference' => 'ref_excess']);

        $service->applyPaymongoUpdate('ref_pending', 'pay_test_refund', 25, BookingRefundService::STATUS_PROCESSED);
        $this->assertSame(25.0, (float) PaymentLedgerEntry::query()->where('entry_type', PaymentLedgerEntry::TYPE_REFUND)->sum('amount'));
    }

    public function test_counter_refunds_never_call_paymongo(): void
    {
        $booking = $this->fullyCollectedBooking(PaymentMethodCatalog::METHOD_CASH_COUNTER);
        $this->mock(PaymongoService::class, function ($mock): void {
            $mock->shouldNotReceive('createRefund');
            $mock->shouldNotReceive('resolvePaymentIdForBooking');
        });

        app(BookingRefundService::class)->processRefund($booking);

        $this->assertSame(2, BookingRefund::query()->where('processing_channel', BookingRefund::CHANNEL_MANUAL)->count());
    }

    public function test_staff_confirmation_logs_only_the_manual_component_completed_at_the_counter(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);
        $booking = $this->fullyCollectedBooking(PaymentMethodCatalog::METHOD_PAYMONGO);
        $service = app(BookingRefundService::class);
        $service->applyPaymongoUpdate('ref_initial_complete', 'pay_test_refund', 50, BookingRefundService::STATUS_PROCESSED);
        $service->processRefund($booking->fresh());

        $this->actingAs($staff)->patchJson(route('appointments.refund.complete', $booking), [
            'refund_note' => 'Balance returned in cash.',
            ...$this->confirmation(50),
        ])->assertOk()
            ->assertJsonPath('message', 'Refund of ₱50.00 marked complete for '.$booking->client_name.'.');

        $log = ActivityLog::query()->where('action', 'refund.completed')->where('subject_id', $booking->id)->firstOrFail();
        $this->assertSame(50.0, (float) $log->properties['refund_amount']);
        $this->assertSame(100.0, (float) $booking->fresh()->refund_amount);
    }

    private function confirmation(float $amount): array
    {
        return ['method' => 'cash', 'recipient' => 'Refund test customer', 'confirmed' => true, 'expected_amount' => $amount, 'evidence' => UploadedFile::fake()->createWithContent('acknowledgment.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF")];
    }

    private function fullyCollectedBooking(string $initialMethod): SpaBooking
    {
        $customer = User::factory()->create(['role' => User::ROLE_USER]);

        return SpaBooking::query()->create([
            'user_id' => $customer->id,
            'client_name' => $customer->name,
            'service_name' => 'Swedish Massage',
            'booking_date' => now()->addDay()->toDateString(),
            'time_slot' => '10:00 AM',
            'amount' => 100,
            'payment_method' => $initialMethod,
            'payment_type' => PaymentMethodCatalog::TYPE_DOWNPAYMENT,
            'payment_amount' => 50,
            'payment_status' => PaymentMethodCatalog::STATUS_PAID,
            'payment_transaction_id' => $initialMethod === PaymentMethodCatalog::METHOD_PAYMONGO ? 'pay_test_refund' : 'OR-INITIAL',
            'balance_amount' => 50,
            'balance_payment_method' => PaymentMethodCatalog::METHOD_CASH_COUNTER,
            'balance_payment_reference' => 'OR-BALANCE',
            'balance_paid_at' => now(),
            'cancelled_at' => now(),
            'session_status' => SpaBooking::STATUS_CANCELLED,
        ]);
    }
}
