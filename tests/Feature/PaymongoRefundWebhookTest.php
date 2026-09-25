<?php

namespace Tests\Feature;

use App\Models\SpaBooking;
use App\Models\User;
use App\Services\BookingRefundService;
use App\Services\PaymongoService;
use App\Support\PaymentMethodCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymongoRefundWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_refund_update_marks_a_pending_refund_as_processed(): void
    {
        $booking = $this->pendingRefundBooking();

        $this->sendWebhook('payment.refund.updated', [
            'id' => 'ref_test_123',
            'type' => 'refund',
            'attributes' => [
                'payment_id' => 'pay_test_123',
                'amount' => 5000,
                'status' => 'succeeded',
            ],
        ])->assertOk();

        $booking->refresh();
        $this->assertSame(BookingRefundService::STATUS_PROCESSED, $booking->refund_status);
        $this->assertSame(PaymentMethodCatalog::STATUS_REFUNDED, $booking->payment_status);
        $this->assertSame(50.0, (float) $booking->refund_amount);
        $this->assertNotNull($booking->refunded_at);
    }

    public function test_failed_and_pending_updates_do_not_mark_payment_as_refunded(): void
    {
        foreach (['pending', 'failed'] as $gatewayStatus) {
            $booking = $this->pendingRefundBooking('pay_'.$gatewayStatus, 'ref_'.$gatewayStatus);

            $this->sendWebhook('payment.refund.updated', [
                'id' => 'ref_'.$gatewayStatus,
                'type' => 'refund',
                'attributes' => [
                    'payment_id' => 'pay_'.$gatewayStatus,
                    'amount' => 5000,
                    'status' => $gatewayStatus,
                ],
            ])->assertOk();

            $booking->refresh();
            $expected = $gatewayStatus === 'failed'
                ? BookingRefundService::STATUS_FAILED
                : BookingRefundService::STATUS_PENDING;
            $this->assertSame($expected, $booking->refund_status);
            $this->assertSame(PaymentMethodCatalog::STATUS_PAID, $booking->payment_status);
            $this->assertNull($booking->refunded_at);
        }
    }

    public function test_duplicate_and_out_of_order_updates_cannot_regress_a_completed_refund(): void
    {
        $booking = $this->pendingRefundBooking();
        $refund = [
            'id' => 'ref_test_123',
            'type' => 'refund',
            'attributes' => [
                'payment_id' => 'pay_test_123',
                'amount' => 5000,
                'status' => 'succeeded',
            ],
        ];

        $this->sendWebhook('payment.refund.updated', $refund)->assertOk();
        $refundedAt = $booking->fresh()->refunded_at;

        $this->sendWebhook('payment.refund.updated', $refund)->assertOk();
        $refund['attributes']['status'] = 'pending';
        $this->sendWebhook('payment.refund.updated', $refund)->assertOk();

        $booking->refresh();
        $this->assertSame(BookingRefundService::STATUS_PROCESSED, $booking->refund_status);
        $this->assertTrue($refundedAt->equalTo($booking->refunded_at));
    }

    public function test_payment_refunded_event_handles_the_payment_resource_shape(): void
    {
        $booking = $this->pendingRefundBooking();

        $this->sendWebhook('payment.refunded', [
            'id' => 'pay_test_123',
            'type' => 'payment',
            'attributes' => [
                'refunds' => [[
                    'id' => 'ref_test_123',
                    'type' => 'refund',
                    'attributes' => [
                        'amount' => 5000,
                        'status' => 'succeeded',
                    ],
                ]],
            ],
        ])->assertOk();

        $booking->refresh();
        $this->assertSame(BookingRefundService::STATUS_PROCESSED, $booking->refund_status);
        $this->assertSame(PaymentMethodCatalog::STATUS_REFUNDED, $booking->payment_status);
    }

    public function test_only_manual_pending_refunds_can_be_completed_by_staff(): void
    {
        $service = app(BookingRefundService::class);
        $automatic = $this->pendingRefundBooking();
        $manual = $this->pendingRefundBooking('pay_manual', 'RF-PND-ABC123');

        $this->assertFalse($service->canCompleteManualRefund($automatic));
        $this->assertTrue($service->canCompleteManualRefund($manual));
    }

    private function sendWebhook(string $eventType, array $resource)
    {
        $this->mock(PaymongoService::class, function ($mock): void {
            $mock->shouldReceive('verifyWebhookSignature')->zeroOrMoreTimes()->andReturnTrue();
        });

        return $this->postJson(route('paymongo.webhook'), [
            'data' => [
                'attributes' => [
                    'type' => $eventType,
                    'data' => $resource,
                ],
            ],
        ], ['Paymongo-Signature' => 't=1,te=valid']);
    }

    private function pendingRefundBooking(
        string $paymentId = 'pay_test_123',
        string $refundId = 'ref_test_123',
    ): SpaBooking {
        $client = User::factory()->create(['role' => User::ROLE_USER]);

        return SpaBooking::create([
            'user_id' => $client->id,
            'service_name' => 'Massage',
            'booking_date' => now()->addDay()->toDateString(),
            'time_slot' => '10:00 AM',
            'amount' => 100,
            'payment_method' => PaymentMethodCatalog::METHOD_PAYMONGO,
            'payment_type' => PaymentMethodCatalog::TYPE_DOWNPAYMENT,
            'payment_amount' => 50,
            'payment_status' => PaymentMethodCatalog::STATUS_PAID,
            'payment_transaction_id' => $paymentId,
            'refund_status' => BookingRefundService::STATUS_PENDING,
            'refund_amount' => 50,
            'refund_reference' => $refundId,
            'cancelled_at' => now(),
        ]);
    }
}
