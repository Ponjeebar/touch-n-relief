<?php

namespace App\Services;

use App\Models\SpaBooking;
use App\Support\PaymentMethodCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingRefundService
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_NOT_APPLICABLE = 'not_applicable';

    /** Payment source types that PayMongo does not refund via API. */
    private const NON_API_REFUNDABLE_SOURCES = [
        'qrph',
        'ubp',
        'unionbank',
    ];

    public function __construct(
        private readonly PaymongoService $paymongo,
    ) {}

    public function labelFor(?string $status): string
    {
        return match ($status) {
            self::STATUS_PROCESSED => 'Refunded',
            self::STATUS_PENDING => 'Refund pending',
            self::STATUS_FAILED => 'Refund failed',
            self::STATUS_NOT_APPLICABLE => 'No refund',
            default => '—',
        };
    }

    public function customerMessage(SpaBooking $booking): string
    {
        return match ($booking->refund_status) {
            self::STATUS_PROCESSED => 'A refund of ₱'
                .number_format((float) $booking->refund_amount, 2)
                .' has been issued'
                .(filled($booking->refund_reference) ? ' (Ref: '.$booking->refund_reference.')' : '')
                .'.',
            self::STATUS_PENDING => filled($booking->refund_note)
                ? (string) $booking->refund_note
                : 'Your refund of ₱'
                    .number_format((float) $booking->refund_amount, 2)
                    .' is queued for processing. Please visit the spa or contact reception.',
            self::STATUS_FAILED => 'We could not process your refund automatically. Please contact the spa with booking reference RCP-'
                .str_pad((string) $booking->id, 5, '0', STR_PAD_LEFT)
                .'.',
            default => '',
        };
    }

    public function shouldRefund(SpaBooking $booking): bool
    {
        if ($booking->refund_status === self::STATUS_PROCESSED) {
            return false;
        }

        if ((float) ($booking->payment_amount ?? 0) <= 0) {
            return false;
        }

        if ($booking->payment_status === PaymentMethodCatalog::STATUS_PAID) {
            return true;
        }

        if ($booking->payment_status === PaymentMethodCatalog::STATUS_REFUNDED) {
            return false;
        }

        return filled($booking->payment_transaction_id)
            || filled($booking->paymongo_checkout_session_id);
    }

    public function canCompleteManualRefund(SpaBooking $booking): bool
    {
        return $booking->cancelled_at !== null
            && $booking->refund_status === self::STATUS_PENDING
            && (float) ($booking->refund_amount ?? 0) > 0;
    }

    public function processRefund(SpaBooking $booking): SpaBooking
    {
        return DB::transaction(function () use ($booking): SpaBooking {
            $locked = SpaBooking::query()->lockForUpdate()->findOrFail($booking->id);

            if (! $this->shouldRefund($locked)) {
                if ($locked->payment_status !== PaymentMethodCatalog::STATUS_PAID
                    && (float) ($locked->payment_amount ?? 0) <= 0) {
                    $locked->forceFill([
                        'refund_status' => self::STATUS_NOT_APPLICABLE,
                    ])->save();
                }

                return $locked->fresh();
            }

            $amount = round((float) $locked->payment_amount, 2);
            $method = (string) ($locked->payment_method ?? '');

            if (PaymentMethodCatalog::isPaymongoOnlineBooking(
                $method,
                $locked->paymongo_checkout_session_id,
                $locked->payment_transaction_id,
            )) {
                return $this->processPaymongoRefund($locked, $amount);
            }

            return $this->queueManualRefund(
                $locked,
                $amount,
                str_replace('{amount}', number_format($amount, 2), $this->manualRefundNoteFor($method)),
            );
        });
    }

    public function completeManualRefund(SpaBooking $booking, ?string $staffNote = null): SpaBooking
    {
        return DB::transaction(function () use ($booking, $staffNote): SpaBooking {
            $locked = SpaBooking::query()->lockForUpdate()->findOrFail($booking->id);

            if (! $this->canCompleteManualRefund($locked)) {
                throw ValidationException::withMessages([
                    'refund' => 'This booking does not have a pending refund to complete.',
                ]);
            }

            $note = trim((string) $staffNote);
            $reference = 'RF-MAN-'.now()->format('YmdHis');

            $locked->forceFill([
                'payment_status' => PaymentMethodCatalog::STATUS_REFUNDED,
                'refund_status' => self::STATUS_PROCESSED,
                'refunded_at' => now(),
                'refund_reference' => $reference,
                'refund_note' => $note !== ''
                    ? $note
                    : 'Refund issued manually by staff.',
            ])->save();

            return $locked->fresh();
        });
    }

    private function processPaymongoRefund(SpaBooking $booking, float $amount): SpaBooking
    {
        if (! $this->paymongo->isConfigured()) {
            return $this->queueManualRefund(
                $booking,
                $amount,
                'PayMongo is not configured. Issue the refund manually at the counter or via the client\'s payment channel.',
            );
        }

        $paymentId = $this->paymongo->resolvePaymentIdForBooking($booking);

        if ($paymentId === '') {
            return $this->queueManualRefund(
                $booking,
                $amount,
                'Payment reference is missing. Issue the refund manually and confirm it in the appointment record.',
            );
        }

        if (str_starts_with($paymentId, 'pay_') && ! str_starts_with((string) ($booking->payment_transaction_id ?? ''), 'pay_')) {
            $booking->forceFill([
                'payment_transaction_id' => $paymentId,
            ])->save();
        }

        if (! $this->paymongo->paymentSupportsApiRefund($paymentId)) {
            return $this->queueManualRefund(
                $booking,
                $amount,
                'This PayMongo payment cannot be refunded automatically (e.g. QR Ph). Return ₱'
                    .number_format($amount, 2)
                    .' to the client at the counter or via bank transfer, then mark the refund complete.',
            );
        }

        try {
            $refund = $this->paymongo->createRefund(
                $paymentId,
                (int) round($amount * 100),
                'requested_by_customer',
                'Booking #'.$booking->id.' cancelled',
            );
            $refundId = (string) ($refund['id'] ?? '');
            $refundAttributes = is_array($refund['attributes'] ?? null) ? $refund['attributes'] : [];
            $refundStatus = (string) ($refundAttributes['status'] ?? 'succeeded');

            if (in_array($refundStatus, ['pending', 'processing'], true)) {
                $booking->forceFill([
                    'refund_status' => self::STATUS_PENDING,
                    'refund_amount' => $amount,
                    'refund_reference' => $refundId !== '' ? $refundId : 'RF-'.Str::upper(Str::random(8)),
                    'refund_note' => 'PayMongo refund is processing. It will return to the client\'s payment method once complete.',
                ])->save();

                return $booking->fresh();
            }

            $booking->forceFill([
                'payment_status' => PaymentMethodCatalog::STATUS_REFUNDED,
                'refund_status' => self::STATUS_PROCESSED,
                'refund_amount' => $amount,
                'refunded_at' => now(),
                'refund_reference' => $refundId !== '' ? $refundId : 'RF-'.Str::upper(Str::random(8)),
                'refund_note' => 'Refund sent back to the client\'s PayMongo payment method.',
            ])->save();

            return $booking->fresh();
        } catch (\Throwable $exception) {
            Log::warning('PayMongo refund failed.', [
                'booking_id' => $booking->id,
                'payment_id' => $paymentId,
                'message' => $exception->getMessage(),
            ]);

            if ($this->isNonApiRefundableError($exception->getMessage())) {
                return $this->queueManualRefund(
                    $booking,
                    $amount,
                    'This PayMongo payment cannot be refunded automatically (e.g. QR Ph). Return ₱'
                        .number_format($amount, 2)
                        .' to the client at the counter or via bank transfer, then mark the refund complete.',
                );
            }

            return $this->queueManualRefund(
                $booking,
                $amount,
                'Automatic refund failed. Return ₱'
                    .number_format($amount, 2)
                    .' to the client manually, then mark the refund complete in Appointments.',
            );
        }
    }

    private function queueManualRefund(SpaBooking $booking, float $amount, string $note): SpaBooking
    {
        $booking->forceFill([
            'refund_status' => self::STATUS_PENDING,
            'refund_amount' => $amount,
            'refund_reference' => 'RF-PND-'.Str::upper(Str::random(6)),
            'refund_note' => $note,
        ])->save();

        return $booking->fresh();
    }

    private function manualRefundNoteFor(string $method): string
    {
        return match ($method) {
            PaymentMethodCatalog::METHOD_CASH_COUNTER => 'Return ₱{amount} in cash to the client at the front desk, then mark the refund complete.',
            'gcash' => 'Send ₱{amount} back to the client\'s GCash account, then mark the refund complete.',
            default => 'Return ₱{amount} to the client using the original payment channel, then mark the refund complete.',
        };
    }

    private function isNonApiRefundableError(string $message): bool
    {
        $lower = strtolower($message);

        foreach (self::NON_API_REFUNDABLE_SOURCES as $source) {
            if (str_contains($lower, $source)) {
                return true;
            }
        }

        return str_contains($lower, 'refunds are not allowed');
    }
}
