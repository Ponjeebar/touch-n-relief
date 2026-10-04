<?php

namespace App\Services;

use App\Models\BookingRefund;
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

    private const NON_API_REFUNDABLE_SOURCES = ['qrph', 'ubp', 'unionbank'];

    public function __construct(
        private readonly PaymongoService $paymongo,
        private readonly PaymentLedgerService $paymentLedger,
    ) {}

    public function labelFor(?string $status, ?SpaBooking $booking = null): string
    {
        return match ($status) {
            self::STATUS_PROCESSED => $booking instanceof SpaBooking
                && $booking->payment_status !== PaymentMethodCatalog::STATUS_REFUNDED
                    ? 'Partially refunded'
                    : 'Refunded',
            self::STATUS_PENDING => 'Refund pending',
            self::STATUS_FAILED => 'Refund failed',
            self::STATUS_NOT_APPLICABLE => 'No refund',
            default => '—',
        };
    }

    public function customerMessage(SpaBooking $booking): string
    {
        return match ($booking->refund_status) {
            self::STATUS_PROCESSED => 'A refund of ₱'.number_format((float) $booking->refund_amount, 2).' has been issued'
                .(filled($booking->refund_reference) ? ' (Ref: '.$booking->refund_reference.')' : '').'.',
            self::STATUS_PENDING => filled($booking->refund_note)
                ? (string) $booking->refund_note
                : 'Your refund of ₱'.number_format((float) $booking->refund_amount, 2).' is queued for processing. Please visit the spa or contact reception.',
            self::STATUS_FAILED => 'We could not process your refund automatically. Please contact the spa with booking reference RCP-'
                .str_pad((string) $booking->id, 5, '0', STR_PAD_LEFT).'.',
            default => '',
        };
    }

    public function shouldRefund(SpaBooking $booking): bool
    {
        return $this->availableForComponent($booking, BookingRefund::COMPONENT_INITIAL) >= 0.01
            || $this->availableForComponent($booking, BookingRefund::COMPONENT_BALANCE) >= 0.01;
    }

    public function canCompleteManualRefund(SpaBooking $booking): bool
    {
        if ($booking->cancelled_at === null) {
            return false;
        }

        if ($booking->refunds()->where('status', self::STATUS_PENDING)
            ->where('processing_channel', BookingRefund::CHANNEL_MANUAL)->exists()) {
            return true;
        }

        return $booking->refund_status === self::STATUS_PENDING
            && str_starts_with((string) $booking->refund_reference, 'RF-PND-')
            && (float) ($booking->refund_amount ?? 0) > 0;
    }

    public function processRefund(SpaBooking $booking): SpaBooking
    {
        return DB::transaction(function () use ($booking): SpaBooking {
            $locked = SpaBooking::query()->lockForUpdate()->findOrFail($booking->id);
            $this->importLegacyRefund($locked);

            $initialAmount = $this->availableForComponent($locked, BookingRefund::COMPONENT_INITIAL);
            if ($initialAmount >= 0.01) {
                if (PaymentMethodCatalog::isPaymongoOnlineBooking(
                    (string) $locked->payment_method,
                    $locked->paymongo_checkout_session_id,
                    $locked->payment_transaction_id,
                )) {
                    $this->processPaymongoRefund($locked, $initialAmount);
                } else {
                    $this->queueManualRefund(
                        $locked,
                        BookingRefund::COMPONENT_INITIAL,
                        $initialAmount,
                        (string) $locked->payment_method,
                        str_replace('{amount}', number_format($initialAmount, 2), $this->manualRefundNoteFor((string) $locked->payment_method)),
                    );
                }
            }

            $balanceAmount = $this->availableForComponent($locked, BookingRefund::COMPONENT_BALANCE);
            if ($balanceAmount >= 0.01) {
                $this->queueManualRefund(
                    $locked,
                    BookingRefund::COMPONENT_BALANCE,
                    $balanceAmount,
                    (string) $locked->balance_payment_method,
                    str_replace('{amount}', number_format($balanceAmount, 2), $this->manualRefundNoteFor((string) $locked->balance_payment_method)),
                );
            }

            if ($locked->totalPaidAmount() < 0.01) {
                $locked->forceFill(['refund_status' => self::STATUS_NOT_APPLICABLE])->save();

                return $locked->fresh();
            }

            return $this->syncBookingSummary($locked);
        });
    }

    public function completeManualRefund(SpaBooking $booking, ?string $staffNote = null, ?int $staffId = null): SpaBooking
    {
        return DB::transaction(function () use ($booking, $staffNote, $staffId): SpaBooking {
            $locked = SpaBooking::query()->lockForUpdate()->findOrFail($booking->id);
            $this->importLegacyRefund($locked);
            $pending = BookingRefund::query()->where('spa_booking_id', $locked->id)
                ->where('status', self::STATUS_PENDING)
                ->where('processing_channel', BookingRefund::CHANNEL_MANUAL)
                ->lockForUpdate()->get();

            if ($pending->isEmpty()) {
                throw ValidationException::withMessages(['refund' => 'This booking does not have a pending manual refund to complete.']);
            }

            $note = trim((string) $staffNote);
            foreach ($pending as $refund) {
                $refund->forceFill([
                    'status' => self::STATUS_PROCESSED,
                    'processed_at' => now(),
                    'processed_by' => $staffId,
                    'reference' => $this->uniqueReference('RF-MAN-'.now()->format('YmdHis').'-'.$refund->id, $refund),
                    'note' => $note !== '' ? $note : 'Refund issued manually by staff.',
                ])->save();
                $this->paymentLedger->recordRefund($refund->fresh());
            }

            return $this->syncBookingSummary($locked);
        });
    }

    public function applyPaymongoUpdate(string $refundId, string $paymentId, float $amount, string $status): void
    {
        DB::transaction(function () use ($refundId, $paymentId, $amount, $status): void {
            $refund = $refundId !== ''
                ? BookingRefund::query()->where('reference', $refundId)->lockForUpdate()->first()
                : null;
            $booking = $refund?->booking;
            if (! $booking instanceof SpaBooking && $paymentId !== '') {
                $booking = SpaBooking::query()->where('payment_transaction_id', $paymentId)
                    ->lockForUpdate()->latest('id')->first();
            }
            if (! $booking instanceof SpaBooking && $refundId !== '') {
                $booking = SpaBooking::query()->where('refund_reference', $refundId)->lockForUpdate()->first();
            }
            if (! $booking instanceof SpaBooking) {
                return;
            }

            $this->importLegacyRefund($booking);
            $refund ??= BookingRefund::query()->where('reference', $refundId)->lockForUpdate()->first();
            if (! $refund instanceof BookingRefund) {
                $refund = BookingRefund::query()->where('spa_booking_id', $booking->id)
                    ->where('payment_component', BookingRefund::COMPONENT_INITIAL)
                    ->where('processing_channel', BookingRefund::CHANNEL_PAYMONGO)
                    ->whereIn('status', [self::STATUS_PENDING, self::STATUS_FAILED])
                    ->lockForUpdate()->latest('id')->first();
            }

            $refundAmount = round($amount > 0 ? $amount : (float) ($refund?->amount ?? 0), 2);
            if ($refundAmount < 0.01) {
                return;
            }

            if (! $refund instanceof BookingRefund) {
                if ($refundAmount > $this->availableForComponent($booking, BookingRefund::COMPONENT_INITIAL) + 0.001) {
                    Log::warning('Ignored PayMongo refund that exceeds the verified initial collection.', compact('refundId', 'refundAmount'));

                    return;
                }
                $refund = BookingRefund::query()->create([
                    'spa_booking_id' => $booking->id,
                    'payment_component' => BookingRefund::COMPONENT_INITIAL,
                    'processing_channel' => BookingRefund::CHANNEL_PAYMONGO,
                    'payment_method' => $booking->payment_method,
                    'gateway_payment_id' => $paymentId ?: $booking->payment_transaction_id,
                    'amount' => $refundAmount,
                    'status' => self::STATUS_PENDING,
                    'reference' => $refundId !== '' ? $refundId : $this->uniqueReference('RF-WEBHOOK-'.Str::upper(Str::random(10))),
                    'note' => 'PayMongo refund update received.',
                    'requested_at' => now(),
                ]);
            }

            if ($refund->status === self::STATUS_PROCESSED && $status !== self::STATUS_PROCESSED) {
                return;
            }

            $otherReserved = (float) BookingRefund::query()->where('spa_booking_id', $booking->id)
                ->where('payment_component', BookingRefund::COMPONENT_INITIAL)
                ->whereKeyNot($refund->id)
                ->whereIn('status', [self::STATUS_PENDING, self::STATUS_PROCESSED])->sum('amount');
            if ($otherReserved + $refundAmount > $this->componentCollectedAmount($booking, BookingRefund::COMPONENT_INITIAL) + 0.001) {
                Log::warning('Ignored PayMongo refund update that exceeds the verified initial collection.', compact('refundId', 'refundAmount'));

                return;
            }

            $values = [
                'status' => $status,
                'amount' => $refundAmount,
                'gateway_payment_id' => $paymentId ?: $refund->gateway_payment_id,
                'note' => match ($status) {
                    self::STATUS_PROCESSED => 'Refund sent back to the client\'s PayMongo payment method.',
                    self::STATUS_FAILED => 'PayMongo could not complete the refund. Please contact reception for assistance.',
                    default => 'PayMongo refund is processing. It will return to the client\'s payment method once complete.',
                },
            ];
            if ($refundId !== '' && $refund->reference !== $refundId) {
                $values['reference'] = $this->uniqueReference($refundId, $refund);
            }
            if ($status === self::STATUS_PROCESSED) {
                $values['processed_at'] = $refund->processed_at ?? now();
            }

            $refund->forceFill($values)->save();
            $this->paymentLedger->recordRefund($refund->fresh());
            $this->syncBookingSummary($booking);
        });
    }

    private function processPaymongoRefund(SpaBooking $booking, float $amount): void
    {
        $paymentId = $this->paymongo->isConfigured() ? $this->paymongo->resolvePaymentIdForBooking($booking) : '';
        $refund = $this->createRefundRecord(
            $booking,
            BookingRefund::COMPONENT_INITIAL,
            BookingRefund::CHANNEL_PAYMONGO,
            $amount,
            (string) $booking->payment_method,
            $paymentId ?: (string) $booking->payment_transaction_id,
            'PayMongo refund is being prepared.',
        );

        if (! $this->paymongo->isConfigured()) {
            $this->convertToManual($refund, 'PayMongo is not configured. Issue the refund manually at the counter or via the client\'s payment channel.');

            return;
        }
        if ($paymentId === '') {
            $this->convertToManual($refund, 'Payment reference is missing. Issue the refund manually and confirm it in the appointment record.');

            return;
        }
        if (str_starts_with($paymentId, 'pay_') && ! str_starts_with((string) ($booking->payment_transaction_id ?? ''), 'pay_')) {
            $booking->forceFill(['payment_transaction_id' => $paymentId])->save();
        }
        if (! $this->paymongo->paymentSupportsApiRefund($paymentId)) {
            $this->convertToManual($refund, 'This PayMongo payment cannot be refunded automatically. Return ₱'.number_format($amount, 2).' manually, then mark the refund complete.');

            return;
        }

        try {
            $gatewayRefund = $this->paymongo->createRefund(
                $paymentId,
                (int) round($amount * 100),
                'requested_by_customer',
                'Booking #'.$booking->id.' cancelled',
            );
            $refundId = (string) ($gatewayRefund['id'] ?? '');
            $attributes = is_array($gatewayRefund['attributes'] ?? null) ? $gatewayRefund['attributes'] : [];
            $processed = ! in_array(strtolower((string) ($attributes['status'] ?? 'succeeded')), ['pending', 'processing'], true);
            $refund->forceFill([
                'gateway_payment_id' => $paymentId,
                'reference' => $refundId !== '' ? $this->uniqueReference($refundId, $refund) : $refund->reference,
                'status' => $processed ? self::STATUS_PROCESSED : self::STATUS_PENDING,
                'processed_at' => $processed ? now() : null,
                'note' => $processed
                    ? 'Refund sent back to the client\'s PayMongo payment method.'
                    : 'PayMongo refund is processing. It will return to the client\'s payment method once complete.',
            ])->save();
            $this->paymentLedger->recordRefund($refund->fresh());
        } catch (\Throwable $exception) {
            Log::warning('PayMongo refund failed.', [
                'booking_id' => $booking->id,
                'payment_id' => $paymentId,
                'message' => $exception->getMessage(),
            ]);
            $message = $this->isNonApiRefundableError($exception->getMessage())
                ? 'This PayMongo payment cannot be refunded automatically. Return ₱'.number_format($amount, 2).' manually, then mark the refund complete.'
                : 'Automatic refund failed. Return ₱'.number_format($amount, 2).' manually, then mark the refund complete.';
            $this->convertToManual($refund, $message);
        }
    }

    private function queueManualRefund(SpaBooking $booking, string $component, float $amount, string $method, string $note): BookingRefund
    {
        return $this->createRefundRecord($booking, $component, BookingRefund::CHANNEL_MANUAL, $amount, $method, null, $note);
    }

    private function createRefundRecord(
        SpaBooking $booking,
        string $component,
        string $channel,
        float $amount,
        string $method,
        ?string $gatewayPaymentId,
        string $note,
    ): BookingRefund {
        return BookingRefund::query()->create([
            'spa_booking_id' => $booking->id,
            'payment_component' => $component,
            'processing_channel' => $channel,
            'payment_method' => $method ?: null,
            'gateway_payment_id' => $gatewayPaymentId ?: null,
            'amount' => round($amount, 2),
            'status' => self::STATUS_PENDING,
            'reference' => $this->uniqueReference(($channel === BookingRefund::CHANNEL_MANUAL ? 'RF-PND-' : 'RF-REQ-').Str::upper(Str::random(10))),
            'note' => $note,
            'requested_at' => now(),
        ]);
    }

    private function convertToManual(BookingRefund $refund, string $note): void
    {
        $refund->forceFill([
            'processing_channel' => BookingRefund::CHANNEL_MANUAL,
            'reference' => $this->uniqueReference('RF-PND-'.Str::upper(Str::random(10)), $refund),
            'status' => self::STATUS_PENDING,
            'note' => $note,
        ])->save();
    }

    private function importLegacyRefund(SpaBooking $booking): void
    {
        if ($booking->refunds()->exists()
            || ! in_array($booking->refund_status, [self::STATUS_PENDING, self::STATUS_PROCESSED, self::STATUS_FAILED], true)
            || (float) ($booking->refund_amount ?? 0) < 0.01) {
            return;
        }

        $reference = trim((string) $booking->refund_reference);
        $channel = str_starts_with($reference, 'RF-PND-') || str_starts_with($reference, 'RF-MAN-')
            ? BookingRefund::CHANNEL_MANUAL
            : BookingRefund::CHANNEL_PAYMONGO;
        BookingRefund::query()->create([
            'spa_booking_id' => $booking->id,
            'payment_component' => BookingRefund::COMPONENT_INITIAL,
            'processing_channel' => $channel,
            'payment_method' => $booking->payment_method,
            'gateway_payment_id' => $booking->payment_transaction_id,
            'amount' => $booking->refund_amount,
            'status' => $booking->refund_status,
            'reference' => $this->uniqueReference($reference !== '' ? $reference : 'RF-LEGACY-'.$booking->id),
            'note' => $booking->refund_note,
            'requested_at' => $booking->created_at ?? now(),
            'processed_at' => $booking->refund_status === self::STATUS_PROCESSED ? ($booking->refunded_at ?? now()) : null,
        ]);
    }

    private function syncBookingSummary(SpaBooking $booking): SpaBooking
    {
        $refunds = BookingRefund::query()->where('spa_booking_id', $booking->id)->get();
        $processed = $refunds->where('status', self::STATUS_PROCESSED);
        $pending = $refunds->where('status', self::STATUS_PENDING);
        $failed = $refunds->where('status', self::STATUS_FAILED);
        $processedAmount = round((float) $processed->sum('amount'), 2);
        $pendingAmount = round((float) $pending->sum('amount'), 2);
        $totalCollected = $booking->totalPaidAmount();
        $latest = $pending->sortByDesc('id')->first()
            ?? $processed->sortByDesc('id')->first()
            ?? $failed->sortByDesc('id')->first();
        $status = $pending->isNotEmpty()
            ? self::STATUS_PENDING
            : ($processed->isNotEmpty() ? self::STATUS_PROCESSED : ($failed->isNotEmpty() ? self::STATUS_FAILED : self::STATUS_NOT_APPLICABLE));
        $fullyRefunded = $totalCollected >= 0.01 && $processedAmount >= $totalCollected - 0.001;

        $booking->forceFill([
            'payment_status' => $fullyRefunded
                ? PaymentMethodCatalog::STATUS_REFUNDED
                : ($booking->payment_status === PaymentMethodCatalog::STATUS_REFUNDED ? PaymentMethodCatalog::STATUS_PAID : $booking->payment_status),
            'refund_status' => $status,
            'refund_amount' => round($processedAmount + $pendingAmount, 2),
            'refunded_at' => $processed->max('processed_at'),
            'refund_reference' => $latest?->reference,
            'refund_note' => $latest?->note,
        ])->save();

        return $booking->fresh();
    }

    private function componentCollectedAmount(SpaBooking $booking, string $component): float
    {
        if ($component === BookingRefund::COMPONENT_BALANCE) {
            return $booking->balance_paid_at !== null ? round(max((float) ($booking->balance_amount ?? 0), 0), 2) : 0.0;
        }

        return in_array($booking->payment_status, [PaymentMethodCatalog::STATUS_PAID, PaymentMethodCatalog::STATUS_REFUNDED], true)
            ? round(max((float) ($booking->payment_amount ?? 0), 0), 2)
            : 0.0;
    }

    private function availableForComponent(SpaBooking $booking, string $component): float
    {
        $reserved = (float) $booking->refunds()->where('payment_component', $component)
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_PROCESSED])->sum('amount');

        return round(max($this->componentCollectedAmount($booking, $component) - $reserved, 0), 2);
    }

    private function uniqueReference(string $candidate, ?BookingRefund $except = null): string
    {
        $reference = mb_substr($candidate, 0, 100);
        $query = BookingRefund::query()->where('reference', $reference);
        if ($except instanceof BookingRefund) {
            $query->whereKeyNot($except->id);
        }

        return $query->exists() ? mb_substr($reference, 0, 87).'-'.Str::upper(Str::random(12)) : $reference;
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
