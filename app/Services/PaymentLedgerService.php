<?php

namespace App\Services;

use App\Models\PaymentLedgerEntry;
use App\Models\SpaBooking;
use App\Support\PaymentMethodCatalog;
use Carbon\CarbonInterface;

class PaymentLedgerService
{
    public function recordInitialPayment(SpaBooking $booking, ?CarbonInterface $occurredAt = null, bool $estimated = false): void
    {
        $amount = round((float) ($booking->payment_amount ?? 0), 2);
        if (! in_array($booking->payment_status, [PaymentMethodCatalog::STATUS_PAID, PaymentMethodCatalog::STATUS_REFUNDED], true) || $amount <= 0) {
            return;
        }

        PaymentLedgerEntry::query()->firstOrCreate(
            ['spa_booking_id' => $booking->id, 'entry_type' => PaymentLedgerEntry::TYPE_INITIAL_PAYMENT],
            [
                'amount' => $amount,
                'payment_method' => $booking->payment_method,
                'reference' => $booking->payment_transaction_id,
                'occurred_at' => $occurredAt ?? now(),
                'is_estimated' => $estimated,
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
            ['spa_booking_id' => $booking->id, 'entry_type' => PaymentLedgerEntry::TYPE_BALANCE_PAYMENT],
            [
                'amount' => $amount,
                'payment_method' => $booking->balance_payment_method,
                'reference' => $booking->balance_payment_reference,
                'occurred_at' => $booking->balance_paid_at,
                'is_estimated' => false,
                'recorded_by' => $booking->balance_collected_by,
            ],
        );
    }

    public function recordRefund(SpaBooking $booking): void
    {
        $amount = round((float) ($booking->refund_amount ?? 0), 2);
        if ($booking->refund_status !== BookingRefundService::STATUS_PROCESSED || $booking->refunded_at === null || $amount <= 0) {
            return;
        }

        PaymentLedgerEntry::query()->firstOrCreate(
            ['spa_booking_id' => $booking->id, 'entry_type' => PaymentLedgerEntry::TYPE_REFUND],
            [
                'amount' => $amount,
                'payment_method' => $booking->payment_method,
                'reference' => $booking->refund_reference,
                'occurred_at' => $booking->refunded_at,
                'is_estimated' => false,
            ],
        );
    }
}
