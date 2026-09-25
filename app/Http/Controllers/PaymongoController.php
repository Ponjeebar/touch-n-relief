<?php

namespace App\Http\Controllers;

use App\Models\SpaBooking;
use App\Services\BookingRefundService;
use App\Services\PaymongoService;
use App\Support\PaymentMethodCatalog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymongoController extends Controller
{
    public function __construct(
        private readonly PaymongoService $paymongo,
    ) {}

    public function success(Request $request, SpaBooking $spaBooking): RedirectResponse
    {
        $user = $request->user();

        if (! $user || $spaBooking->user_id !== $user->id) {
            abort(403);
        }

        if ($spaBooking->payment_status !== PaymentMethodCatalog::STATUS_PAID && filled($spaBooking->paymongo_checkout_session_id)) {
            $verified = false;

            for ($attempt = 0; $attempt < 3; $attempt++) {
                try {
                    $session = $this->paymongo->retrieveCheckoutSession($spaBooking->paymongo_checkout_session_id);

                    if ($this->paymongo->isCheckoutSessionPaid($session)) {
                        $this->markBookingPaid($spaBooking, $session);
                        $verified = true;
                        break;
                    }
                } catch (\Throwable $exception) {
                    if ($attempt === 2) {
                        Log::warning('PayMongo success callback could not verify session.', [
                            'booking_id' => $spaBooking->id,
                            'message' => $exception->getMessage(),
                        ]);
                    }
                }

                if ($attempt < 2) {
                    usleep(500000);
                }
            }

            if (! $verified && $spaBooking->payment_status !== PaymentMethodCatalog::STATUS_PAID) {
                Log::info('PayMongo success callback returned before payment was confirmed.', [
                    'booking_id' => $spaBooking->id,
                    'session_id' => $spaBooking->paymongo_checkout_session_id,
                ]);
            }
        }

        $spaBooking->refresh();

        $dateFormatted = $spaBooking->booking_date?->format('M j, Y') ?? '';
        $paymentAmount = (float) ($spaBooking->payment_amount ?? 0);
        $typeLabel = PaymentMethodCatalog::typeLabelFor($spaBooking->payment_type);
        $isPaid = $spaBooking->payment_status === PaymentMethodCatalog::STATUS_PAID;

        $statusMessage = $isPaid
            ? 'Your booking is confirmed with '.$spaBooking->therapist_name.' for '.$spaBooking->service_name.' on '.$dateFormatted.' at '.$spaBooking->time_slot.'. Payment: PayMongo · '.$typeLabel.' · ₱'.number_format($paymentAmount, 2).'.'
            : 'Your booking is reserved. Complete payment on PayMongo to confirm your appointment.';

        if ($isPaid) {
            $receipt = [
                'receipt_no' => 'RCP-'.str_pad((string) $spaBooking->id, 5, '0', STR_PAD_LEFT),
                'client_name' => (string) ($user->name ?? $spaBooking->client_name ?? ''),
                'therapist' => $spaBooking->therapist_name,
                'service' => $spaBooking->service_name,
                'date' => $dateFormatted,
                'time' => $spaBooking->time_slot,
                'payment_method' => PaymentMethodCatalog::labelFor($spaBooking->payment_method),
                'payment_type' => $typeLabel,
                'payment_amount' => number_format($paymentAmount, 2),
                'payment_status' => 'Paid',
                'reference' => (string) ($spaBooking->payment_transaction_id ?? ''),
                'issued_at' => now()->format('M j, Y g:i A'),
            ];

            return redirect()
                ->route('landing')
                ->with('booking_confirmed', true)
                ->with('payment_receipt', $receipt)
                ->with('booking_summary', $receipt);
        }

        return redirect()
            ->route('booking.index', [
                'service' => $spaBooking->service_name,
                'therapist' => $spaBooking->therapist_name,
            ])
            ->with('status', $statusMessage);
    }

    public function cancel(Request $request, SpaBooking $spaBooking): RedirectResponse
    {
        $user = $request->user();

        if (! $user || $spaBooking->user_id !== $user->id) {
            abort(403);
        }

        if ($spaBooking->payment_status === PaymentMethodCatalog::STATUS_PENDING
            && filled($spaBooking->paymongo_checkout_session_id)) {
            try {
                $session = $this->paymongo->retrieveCheckoutSession($spaBooking->paymongo_checkout_session_id);
                if ($this->paymongo->isCheckoutSessionPaid($session)) {
                    $this->markBookingPaid($spaBooking, $session);
                }
            } catch (\Throwable $exception) {
                Log::warning('PayMongo cancellation callback could not verify session.', [
                    'booking_id' => $spaBooking->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        // Keep the pending booking so a delayed paid webhook can still reconcile it.
        $spaBooking->refresh();

        if ($spaBooking->payment_status === PaymentMethodCatalog::STATUS_PAID) {
            return redirect()->route('paymongo.success', ['spaBooking' => $spaBooking->id]);
        }

        return redirect()
            ->route('booking.index')
            ->with(
                'status',
                'Payment is not confirmed yet. If your payment completes, your booking will be updated automatically.'
            );
    }

    public function staffSuccess(SpaBooking $spaBooking): RedirectResponse
    {
        $this->verifyPendingCheckout($spaBooking);
        $spaBooking->refresh();

        return redirect()->route('appointments.index', ['date' => $spaBooking->booking_date?->format('Y-m-d')])
            ->with('status', $spaBooking->payment_status === PaymentMethodCatalog::STATUS_PAID
                ? 'PayMongo payment confirmed for booking #'.$spaBooking->id.'.'
                : 'Booking #'.$spaBooking->id.' was created. PayMongo payment is still pending confirmation.');
    }

    public function staffCancel(SpaBooking $spaBooking): RedirectResponse
    {
        $this->verifyPendingCheckout($spaBooking);
        $spaBooking->refresh();

        return redirect()->route('appointments.index', ['date' => $spaBooking->booking_date?->format('Y-m-d')])
            ->with('status', $spaBooking->payment_status === PaymentMethodCatalog::STATUS_PAID
                ? 'PayMongo payment confirmed for booking #'.$spaBooking->id.'.'
                : 'PayMongo checkout was not completed. Booking #'.$spaBooking->id.' remains pending payment.');
    }

    public function staffRetry(SpaBooking $spaBooking): RedirectResponse
    {
        if ($spaBooking->booking_source !== SpaBooking::SOURCE_WALK_IN
            || $spaBooking->payment_method !== PaymentMethodCatalog::METHOD_PAYMONGO
            || $spaBooking->payment_status !== PaymentMethodCatalog::STATUS_PENDING) {
            abort(404);
        }

        try {
            if (blank($spaBooking->paymongo_checkout_session_id)) {
                return redirect()->away($this->paymongo->startStaffBookingCheckout($spaBooking));
            }

            $session = $this->paymongo->retrieveCheckoutSession($spaBooking->paymongo_checkout_session_id);
            if ($this->paymongo->isCheckoutSessionPaid($session)) {
                $this->markBookingPaid($spaBooking, $session);

                return redirect()->route('appointments.index', ['date' => $spaBooking->booking_date?->format('Y-m-d')])
                    ->with('status', 'PayMongo payment confirmed for booking #'.$spaBooking->id.'.');
            }

            $checkoutUrl = (string) ($session['attributes']['checkout_url'] ?? '');
            if ($this->paymongo->isCheckoutUrl($checkoutUrl)) {
                return redirect()->away($checkoutUrl);
            }
        } catch (\Throwable $exception) {
            Log::warning('Staff PayMongo checkout could not be resumed.', [
                'booking_id' => $spaBooking->id,
                'message' => $exception->getMessage(),
            ]);
        }

        return redirect()->route('appointments.index', ['date' => $spaBooking->booking_date?->format('Y-m-d')])
            ->with('status', 'PayMongo checkout could not be resumed. Please contact an administrator.');
    }

    public function customerRetry(Request $request, SpaBooking $spaBooking): RedirectResponse
    {
        $user = $request->user();
        if (! $user || (int) $spaBooking->user_id !== (int) $user->id) {
            abort(403);
        }

        if ($spaBooking->booking_source !== SpaBooking::SOURCE_ONLINE
            || $spaBooking->payment_method !== PaymentMethodCatalog::METHOD_PAYMONGO
            || $spaBooking->payment_status !== PaymentMethodCatalog::STATUS_PENDING
            || $spaBooking->cancelled_at !== null
            || $spaBooking->completed_at !== null) {
            abort(404);
        }

        $appointmentAt = Carbon::parse($spaBooking->booking_date->format('Y-m-d').' '.$spaBooking->time_slot);
        if ($appointmentAt->lte(now())) {
            return back()->withErrors(['payment' => 'Payment can no longer be continued because the appointment time has passed.']);
        }

        if (filled($spaBooking->paymongo_checkout_session_id)) {
            try {
                $session = $this->paymongo->retrieveCheckoutSession($spaBooking->paymongo_checkout_session_id);
                if ($this->paymongo->isCheckoutSessionPaid($session)) {
                    $this->markBookingPaid($spaBooking, $session);

                    return back()->with('status', 'Your payment is confirmed.');
                }

                $checkoutUrl = (string) ($session['attributes']['checkout_url'] ?? '');
                $sessionStatus = strtolower((string) ($session['attributes']['status'] ?? ''));
                if ($this->paymongo->isCheckoutUrl($checkoutUrl) && ! in_array($sessionStatus, ['expired', 'cancelled'], true)) {
                    return redirect()->away($checkoutUrl);
                }
            } catch (\Throwable $exception) {
                Log::info('Customer PayMongo checkout will be replaced.', [
                    'booking_id' => $spaBooking->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        try {
            return redirect()->away($this->paymongo->startCustomerBookingCheckout($spaBooking));
        } catch (\Throwable $exception) {
            Log::warning('Customer PayMongo checkout could not be resumed.', [
                'booking_id' => $spaBooking->id,
                'message' => $exception->getMessage(),
            ]);

            return back()->withErrors(['payment' => 'PayMongo checkout could not be opened right now. Please try again later.']);
        }
    }

    private function verifyPendingCheckout(SpaBooking $spaBooking): void
    {
        if ($spaBooking->payment_status === PaymentMethodCatalog::STATUS_PAID
            || blank($spaBooking->paymongo_checkout_session_id)) {
            return;
        }

        try {
            $session = $this->paymongo->retrieveCheckoutSession($spaBooking->paymongo_checkout_session_id);
            if ($this->paymongo->isCheckoutSessionPaid($session)) {
                $this->markBookingPaid($spaBooking, $session);
            }
        } catch (\Throwable $exception) {
            Log::warning('Staff PayMongo return could not verify session.', [
                'booking_id' => $spaBooking->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Paymongo-Signature');

        if (! $this->paymongo->verifyWebhookSignature($payload, $signature)) {
            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            return response()->json(['message' => 'Invalid payload.'], 400);
        }

        $eventType = (string) ($event['data']['attributes']['type'] ?? '');
        $resource = $event['data']['attributes']['data'] ?? null;

        if (! is_array($resource)) {
            return response()->json(['received' => true]);
        }

        match ($eventType) {
            'checkout_session.payment.paid' => $this->handleCheckoutPaid($resource),
            'payment.paid' => $this->handlePaymentPaid($resource),
            'payment.refunded' => $this->handlePaymentRefunded($resource),
            'payment.refund.updated' => $this->handleRefundUpdated($resource),
            default => null,
        };

        return response()->json(['received' => true]);
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function handleCheckoutPaid(array $session): void
    {
        $sessionId = (string) ($session['id'] ?? '');
        $attributes = is_array($session['attributes'] ?? null) ? $session['attributes'] : [];
        $metadata = is_array($attributes['metadata'] ?? null) ? $attributes['metadata'] : [];
        $bookingId = (int) ($metadata['booking_id'] ?? $attributes['reference_number'] ?? 0);

        $booking = null;

        if ($bookingId > 0) {
            $booking = SpaBooking::query()->find($bookingId);
        }

        if (! $booking instanceof SpaBooking && $sessionId !== '') {
            $booking = SpaBooking::query()
                ->where('paymongo_checkout_session_id', $sessionId)
                ->first();
        }

        if (! $booking instanceof SpaBooking) {
            Log::warning('PayMongo webhook could not match booking.', [
                'session_id' => $sessionId,
                'booking_id' => $bookingId,
            ]);

            return;
        }

        $this->markBookingPaid($booking, $session);
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    private function handlePaymentPaid(array $payment): void
    {
        $paymentId = (string) ($payment['id'] ?? '');
        $attributes = is_array($payment['attributes'] ?? null) ? $payment['attributes'] : [];

        if ($paymentId === '' || ! str_starts_with($paymentId, 'pay_') || ($attributes['status'] ?? '') !== 'paid') {
            return;
        }

        $booking = $this->findBookingForPayment($payment);

        if (! $booking instanceof SpaBooking) {
            Log::warning('PayMongo payment.paid webhook could not match booking.', [
                'payment_id' => $paymentId,
            ]);

            return;
        }

        $this->markBookingPaidFromPayment($booking, $paymentId, null);
    }

    /**
     * @param  array<string, mixed>  $refund
     */
    private function handlePaymentRefunded(array $payment): void
    {
        $paymentId = (string) ($payment['id'] ?? '');
        $attributes = is_array($payment['attributes'] ?? null) ? $payment['attributes'] : [];
        $refunds = is_array($attributes['refunds'] ?? null) ? $attributes['refunds'] : [];

        if ($paymentId === '' && ($payment['type'] ?? '') === 'refund') {
            $this->applyRefundUpdate($payment, BookingRefundService::STATUS_PROCESSED);

            return;
        }

        if ($refunds !== []) {
            foreach ($refunds as $refund) {
                if (! is_array($refund)) {
                    continue;
                }

                $refundAttributes = is_array($refund['attributes'] ?? null) ? $refund['attributes'] : [];
                $refund['attributes'] = array_merge($refundAttributes, ['payment_id' => $paymentId]);
                $this->applyRefundUpdate($refund, BookingRefundService::STATUS_PROCESSED);
            }

            return;
        }

        $this->applyRefundUpdate([
            'attributes' => [
                'payment_id' => $paymentId,
            ],
        ], BookingRefundService::STATUS_PROCESSED);
    }

    /**
     * @param  array<string, mixed>  $refund
     */
    private function handleRefundUpdated(array $refund): void
    {
        $attributes = is_array($refund['attributes'] ?? null) ? $refund['attributes'] : [];
        $gatewayStatus = strtolower((string) ($attributes['status'] ?? ''));
        $status = match ($gatewayStatus) {
            'succeeded', 'success', 'refunded' => BookingRefundService::STATUS_PROCESSED,
            'failed', 'cancelled', 'canceled' => BookingRefundService::STATUS_FAILED,
            default => BookingRefundService::STATUS_PENDING,
        };

        $this->applyRefundUpdate($refund, $status);
    }

    /**
     * @param  array<string, mixed>  $refund
     */
    private function applyRefundUpdate(array $refund, string $status): void
    {
        $refundId = (string) ($refund['id'] ?? '');
        $attributes = is_array($refund['attributes'] ?? null) ? $refund['attributes'] : [];
        $paymentId = (string) ($attributes['payment_id'] ?? '');
        $amountCentavos = (int) ($attributes['amount'] ?? 0);

        $booking = null;

        if ($refundId !== '') {
            $booking = SpaBooking::query()
                ->where('refund_reference', $refundId)
                ->first();
        }

        if (! $booking instanceof SpaBooking && $paymentId !== '') {
            $booking = SpaBooking::query()
                ->where('payment_transaction_id', $paymentId)
                ->whereIn('refund_status', [
                    BookingRefundService::STATUS_PENDING,
                    BookingRefundService::STATUS_FAILED,
                    BookingRefundService::STATUS_PROCESSED,
                ])
                ->latest('id')
                ->first();
        }

        if (! $booking instanceof SpaBooking) {
            return;
        }

        // PayMongo retries webhook deliveries. Never let an older update regress a completed refund.
        if ($booking->refund_status === BookingRefundService::STATUS_PROCESSED
            && $status !== BookingRefundService::STATUS_PROCESSED) {
            return;
        }

        $refundAmount = $amountCentavos > 0
            ? round($amountCentavos / 100, 2)
            : (float) ($booking->refund_amount ?? $booking->payment_amount ?? 0);

        $values = [
            'refund_status' => $status,
            'refund_amount' => $refundAmount,
            'refund_reference' => $refundId !== '' ? $refundId : $booking->refund_reference,
        ];

        if ($status === BookingRefundService::STATUS_PROCESSED) {
            $values['payment_status'] = PaymentMethodCatalog::STATUS_REFUNDED;
            $values['refunded_at'] = $booking->refunded_at ?? now();
            $values['refund_note'] = 'Refund sent back to the client\'s PayMongo payment method.';
        } elseif ($status === BookingRefundService::STATUS_FAILED) {
            $values['refund_note'] = 'PayMongo could not complete the refund. Please contact reception for assistance.';
        } else {
            $values['refund_note'] = 'PayMongo refund is processing. It will return to the client\'s payment method once complete.';
        }

        $booking->forceFill($values)->save();
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    private function findBookingForPayment(array $payment): ?SpaBooking
    {
        $attributes = is_array($payment['attributes'] ?? null) ? $payment['attributes'] : [];
        $metadata = is_array($attributes['metadata'] ?? null) ? $attributes['metadata'] : [];
        $bookingId = (int) ($metadata['booking_id'] ?? 0);

        if ($bookingId > 0) {
            $booking = SpaBooking::query()->find($bookingId);

            if ($booking instanceof SpaBooking) {
                return $booking;
            }
        }

        $description = (string) ($attributes['description'] ?? '');

        if (preg_match('/booking #(\d+)/i', $description, $matches) === 1) {
            $booking = SpaBooking::query()->find((int) $matches[1]);

            if ($booking instanceof SpaBooking) {
                return $booking;
            }
        }

        $paymentIntentId = (string) ($attributes['payment_intent_id'] ?? '');

        if ($paymentIntentId !== '') {
            $booking = SpaBooking::query()
                ->where('payment_transaction_id', $paymentIntentId)
                ->first();

            if ($booking instanceof SpaBooking) {
                return $booking;
            }
        }

        $sessionId = trim((string) ($metadata['checkout_session_id'] ?? ''));

        if ($sessionId !== '') {
            $booking = SpaBooking::query()
                ->where('paymongo_checkout_session_id', $sessionId)
                ->first();

            if ($booking instanceof SpaBooking) {
                return $booking;
            }
        }

        return null;
    }

    private function markBookingPaid(SpaBooking $booking, array $session): void
    {
        $paymentId = $this->paymongo->extractPaidPaymentIdFromSession($session);
        $sessionId = (string) ($session['id'] ?? $booking->paymongo_checkout_session_id);
        $channel = $this->paymongo->extractPaymentChannelFromSession($session);

        $this->markBookingPaidFromPayment(
            $booking,
            $paymentId,
            $sessionId !== '' ? $sessionId : null,
            $channel !== '' ? $channel : null,
        );
    }

    private function markBookingPaidFromPayment(SpaBooking $booking, string $paymentId, ?string $sessionId, ?string $channel = null): void
    {
        $storedPaymentId = trim((string) ($booking->payment_transaction_id ?? ''));
        $resolvedPaymentId = str_starts_with($paymentId, 'pay_') ? $paymentId : '';

        if ($channel === null || $channel === '') {
            $channel = $resolvedPaymentId !== ''
                ? $this->paymongo->resolvePaymentChannelForPaymentId($resolvedPaymentId)
                : '';
        }

        $paymentMethod = PaymentMethodCatalog::normalizePaymongoChannel($channel !== '' ? $channel : $booking->payment_method);

        if ($booking->payment_status === PaymentMethodCatalog::STATUS_PAID) {
            $updates = [];

            if ($resolvedPaymentId !== '' && ! str_starts_with($storedPaymentId, 'pay_')) {
                $updates['payment_transaction_id'] = $resolvedPaymentId;
            }

            if ($channel !== '' && $booking->payment_method === PaymentMethodCatalog::METHOD_PAYMONGO) {
                $updates['payment_method'] = $paymentMethod;
            }

            if ($updates !== []) {
                $booking->forceFill($updates)->save();
            }

            return;
        }

        $booking->forceFill([
            'payment_status' => PaymentMethodCatalog::STATUS_PAID,
            'payment_method' => $paymentMethod,
            'payment_transaction_id' => $resolvedPaymentId !== '' ? $resolvedPaymentId : $booking->payment_transaction_id,
            'paymongo_checkout_session_id' => $sessionId ?? $booking->paymongo_checkout_session_id,
        ])->save();
    }
}
