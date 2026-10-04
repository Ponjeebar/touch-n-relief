<?php

namespace App\Services;

use App\Models\BookingRefund;
use App\Models\PaymentLedgerEntry;
use App\Models\SpaBooking;
use App\Support\PaymentMethodCatalog;
use Carbon\CarbonInterface;

class PaymentLedgerService
{
    public function recordInitialPayment(
        SpaBooking $booking,
        ?CarbonInterface $occurredAt = null,
        bool $estimated = false,
        ?int $recordedBy = null,
    ): void {
        $amount = round((float) ($booking->payment_amount ?? 0), 2);
        if (! in_array($booking->payment_status, [PaymentMethodCatalog::STATUS_PAID, PaymentMethodCatalog::STATUS_REFUNDED], true) || $amount <= 0) {
            return;
        }

        PaymentLedgerEntry::query()->firstOrCreate(
            ['idempotency_key' => 'booking:'.$booking->id.':'.PaymentLedgerEntry::TYPE_INITIAL_PAYMENT],
            [
                'spa_booking_id' => $booking->id,
                'amount' => $amount,
                'entry_type' => PaymentLedgerEntry::TYPE_INITIAL_PAYMENT,
                'payment_method' => $booking->payment_method,
                'reference' => $booking->payment_transaction_id,
                'occurred_at' => $occurredAt ?? now(),
                'is_estimated' => $estimated,
                'recorded_by' => $recordedBy,
            ],
        );
    }

    public function recordBalancePayment(SpaBooking $booking): void
    {
        $amount = round((float) ($booking->balance_amount ?? 0), 2);
        if ($booking->balance_paid_at === null || $amount <= 0) {
            return;
        }

        PaymentLedgerEntry::query()->firstOrCreate(
            ['idempotency_key' => 'booking:'.$booking->id.':'.PaymentLedgerEntry::TYPE_BALANCE_PAYMENT],
            [
                'spa_booking_id' => $booking->id,
                'amount' => $amount,
                'entry_type' => PaymentLedgerEntry::TYPE_BALANCE_PAYMENT,
                'payment_method' => $booking->balance_payment_method,
                'reference' => $booking->balance_payment_reference,
                'occurred_at' => $booking->balance_paid_at,
                'is_estimated' => false,
                'recorded_by' => $booking->balance_collected_by,
            ],
        );
    }

    public function recordRefund(BookingRefund $refund): void
    {
        $amount = round((float) $refund->amount, 2);
        if ($refund->status !== BookingRefundService::STATUS_PROCESSED || $refund->processed_at === null || $amount <= 0) {
            return;
        }

        PaymentLedgerEntry::query()->firstOrCreate(
            ['idempotency_key' => 'booking:'.$refund->spa_booking_id.':refund:'.$refund->id],
            [
                'spa_booking_id' => $refund->spa_booking_id,
                'booking_refund_id' => $refund->id,
                'entry_type' => PaymentLedgerEntry::TYPE_REFUND,
                'amount' => $amount,
                'payment_method' => $refund->payment_method,
                'reference' => $refund->reference,
                'occurred_at' => $refund->processed_at,
                'is_estimated' => false,
                'recorded_by' => $refund->processed_by,
            ],
        );
    }
}
