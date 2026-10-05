<?php

namespace App\Http\Controllers;

use App\Models\MembershipPurchase;
use App\Models\SpaBooking;
use App\Models\Therapist;
use App\Services\BookingRefundService;
use App\Services\BookingSlotService;
use App\Services\MembershipPurchaseService;
use App\Services\PaymentLedgerService;
use App\Services\PaymongoService;
use App\Support\PaymentMethodCatalog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymongoController extends Controller
{
    public function __construct(
        private readonly PaymongoService $paymongo,
        private readonly BookingSlotService $slots,
        private readonly BookingRefundService $refunds,
        private readonly PaymentLedgerService $paymentLedger,
        private readonly MembershipPurchaseService $memberships,
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
            : ($spaBooking->cancelled_at !== null
                ? 'Your payment arrived after the '.SpaBooking::paymentHoldMinutes().'-minute hold expired and the appointment could no longer be served. The appointment was cancelled and the refund is being processed.'
                : 'Your booking is reserved. Complete payment on PayMongo to confirm your appointment.');

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
            if ($this->paymongo->checkoutSessionHasUsablePaymentMethods($session)
                && $this->paymongo->isCheckoutUrl($checkoutUrl)) {
                return redirect()->away($checkoutUrl);
            }

            return redirect()->away($this->paymongo->startStaffBookingCheckout($spaBooking));
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

        if ($spaBooking->isPaymentHoldExpired()) {
            if (filled($spaBooking->paymongo_checkout_session_id)) {
                try {
                    $session = $this->paymongo->retrieveCheckoutSession($spaBooking->paymongo_checkout_session_id);
                    if ($this->paymongo->isCheckoutSessionPaid($session)) {
                        $this->markBookingPaid($spaBooking, $session);
                        $spaBooking->refresh();

                        return $spaBooking->payment_status === PaymentMethodCatalog::STATUS_PAID
                            ? back()->with('status', 'Your payment is confirmed.')
                            : back()->withErrors(['payment' => 'The '.SpaBooking::paymentHoldMinutes().'-minute payment hold expired and the selected time was taken. Your refund is being processed.']);
                    }
                } catch (\Throwable $exception) {
                    Log::info('Expired customer payment hold could not be verified.', [
                        'booking_id' => $spaBooking->id,
                        'message' => $exception->getMessage(),
                    ]);
                }
            }

            return back()->withErrors([
                'payment' => 'The '.SpaBooking::paymentHoldMinutes().'-minute payment hold has expired. Please choose an available appointment time again.',
            ]);
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
                if ($this->paymongo->checkoutSessionHasUsablePaymentMethods($session)
                    && $this->paymongo->isCheckoutUrl($checkoutUrl)
                    && ! in_array($sessionStatus, ['expired', 'cancelled'], true)) {
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
        $membershipPurchaseId = (int) ($metadata['membership_purchase_id'] ?? 0);

        if ($membershipPurchaseId > 0) {
            $purchase = MembershipPurchase::query()->find($membershipPurchaseId);
            if ($purchase instanceof MembershipPurchase) {
                $this->markMembershipPaid($purchase, $session);
            }

            return;
        }

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

        $purchase = $this->findMembershipForPayment($payment);
        if ($purchase instanceof MembershipPurchase) {
            if ($this->memberships->evidenceMatches($purchase, $payment)) {
                $this->memberships->confirm(
                    $purchase,
                    $paymentId,
                    $this->paymongo->resolvePaymentChannelForPaymentId($paymentId),
                );
            }

            return;
        }

        $booking = $this->findBookingForPayment($payment);

        if (! $booking instanceof SpaBooking) {
            Log::warning('PayMongo payment.paid webhook could not match booking.', [
                'payment_id' => $paymentId,
            ]);

            return;
        }

        $this->markBookingPaidFromPayment($booking, $paymentId, null, payment: $payment);
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
        $status = BookingRefundService::statusForGateway($gatewayStatus);

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

        $this->refunds->applyPaymongoUpdate(
            $refundId,
            $paymentId,
            $amountCentavos > 0 ? round($amountCentavos / 100, 2) : 0.0,
            $status,
        );
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

    /**
     * @param  array<string, mixed>  $payment
     */
    private function findMembershipForPayment(array $payment): ?MembershipPurchase
    {
        $attributes = is_array($payment['attributes'] ?? null) ? $payment['attributes'] : [];
        $metadata = is_array($attributes['metadata'] ?? null) ? $attributes['metadata'] : [];
        $purchaseId = (int) ($metadata['membership_purchase_id'] ?? 0);

        if ($purchaseId > 0) {
            return MembershipPurchase::query()->find($purchaseId);
        }

        $description = (string) ($attributes['description'] ?? '');
        if (preg_match('/membership #(\d+)/i', $description, $matches) === 1) {
            return MembershipPurchase::query()->find((int) $matches[1]);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function markMembershipPaid(MembershipPurchase $purchase, array $session): void
    {
        if (! $this->memberships->evidenceMatches($purchase, $session)) {
            return;
        }

        $this->memberships->confirm(
            $purchase,
            $this->paymongo->extractPaidPaymentIdFromSession($session),
            $this->paymongo->extractPaymentChannelFromSession($session),
            (string) ($session['id'] ?? $purchase->paymongo_checkout_session_id),
        );
    }

    private function markBookingPaid(SpaBooking $booking, array $session): void
    {
        if (! $this->paymentEvidenceMatchesBooking($booking, $session)) {
            return;
        }

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

    private function markBookingPaidFromPayment(
        SpaBooking $booking,
        string $paymentId,
        ?string $sessionId,
        ?string $channel = null,
        ?array $payment = null,
    ): void {
        $booking->refresh();

        if ($booking->payment_status === PaymentMethodCatalog::STATUS_REFUNDED
            || $booking->refund_status === BookingRefundService::STATUS_PROCESSED) {
            Log::info('Ignored PayMongo paid event for an already refunded booking.', [
                'booking_id' => $booking->id,
                'payment_id' => $paymentId,
            ]);

            return;
        }

        if ($payment !== null && ! $this->paymentEvidenceMatchesBooking($booking, $payment)) {
            return;
        }

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

            $paidBooking = $booking->fresh();
            $this->paymentLedger->recordInitialPayment($paidBooking);

            if ($paidBooking->cancelled_at !== null
                && ! in_array($paidBooking->refund_status, [
                    BookingRefundService::STATUS_PENDING,
                    BookingRefundService::STATUS_PROCESSED,
                ], true)) {
                $this->refunds->processRefund($paidBooking);
            }

            return;
        }

        $paidValues = [
            'payment_status' => PaymentMethodCatalog::STATUS_PAID,
            'payment_method' => $paymentMethod,
            'payment_transaction_id' => $resolvedPaymentId !== '' ? $resolvedPaymentId : $booking->payment_transaction_id,
            'paymongo_checkout_session_id' => $sessionId ?? $booking->paymongo_checkout_session_id,
        ];

        $mustRefund = DB::transaction(function () use ($booking, $paidValues): bool {
            // Use the same therapist-first lock order as booking creation before the final decision.
            $this->slots->lockTherapistBookingsForUpdate(
                (string) $booking->therapist_name,
                $booking->booking_date->format('Y-m-d'),
            );

            $locked = SpaBooking::query()->lockForUpdate()->findOrFail($booking->id);

            if ($locked->payment_status === PaymentMethodCatalog::STATUS_PAID) {
                return false;
            }

            $holdExpired = $locked->isPaymentHoldExpired();
            $terminal = $this->bookingIsTerminal($locked);
            $unserviceable = $terminal
                || ($holdExpired && ! $this->latePaymentCanBeAccepted($locked));
            if ($unserviceable && ! $terminal) {
                $paidValues['cancelled_at'] = now();
                $paidValues['session_status'] = SpaBooking::STATUS_CANCELLED;
                $paidValues['cancellation_reason'] = 'Automatically cancelled because the verified payment arrived after the appointment could no longer be served.';
            }

            $locked->forceFill($paidValues)->save();

            return $unserviceable;
        });

        $this->paymentLedger->recordInitialPayment($booking->fresh());

        if ($mustRefund) {
            $refunded = $this->refunds->processRefund($booking->fresh());

            Log::warning('Late PayMongo payment could not be applied to a serviceable appointment.', [
                'booking_id' => $booking->id,
                'refund_status' => $refunded->refund_status,
            ]);
        }
    }

    private function latePaymentCanBeAccepted(SpaBooking $booking): bool
    {
        if ($this->bookingIsTerminal($booking)) {
            return false;
        }

        $bookingDate = $booking->booking_date->format('Y-m-d');
        if ($this->slots->isPastSlot($bookingDate, (string) $booking->time_slot)
            || $this->slots->bookingHasConflict($booking)) {
            return false;
        }

        return Therapist::query()
            ->where('name', $booking->therapist_name)
            ->where('is_active', true)
            ->exists();
    }

    private function bookingIsTerminal(SpaBooking $booking): bool
    {
        return $booking->cancelled_at !== null
            || $booking->completed_at !== null
            || $booking->session_started_at !== null
            || ! in_array($booking->session_status, [null, SpaBooking::STATUS_CONFIRMED], true);
    }

    /**
     * Reject a paid event when PayMongo supplies an amount or currency that does not match
     * the amount reserved for this booking. Some checkout lookup responses omit these fields;
     * an explicit mismatch is never accepted.
     *
     * @param  array<string, mixed>  $resource
     */
    private function paymentEvidenceMatchesBooking(SpaBooking $booking, array $resource): bool
    {
        $attributes = is_array($resource['attributes'] ?? null) ? $resource['attributes'] : [];
        $amount = isset($attributes['amount']) && is_numeric($attributes['amount'])
            ? (int) $attributes['amount']
            : null;
        $currency = strtoupper(trim((string) ($attributes['currency'] ?? '')));

        $payments = is_array($attributes['payments'] ?? null) ? $attributes['payments'] : [];
        foreach ($payments as $payment) {
            if (! is_array($payment)) {
                continue;
            }

            $paymentAttributes = is_array($payment['attributes'] ?? null) ? $payment['attributes'] : [];
            if (($paymentAttributes['status'] ?? '') !== 'paid') {
                continue;
            }

            if (isset($paymentAttributes['amount']) && is_numeric($paymentAttributes['amount'])) {
                $amount = (int) $paymentAttributes['amount'];
            }
            if (filled($paymentAttributes['currency'] ?? null)) {
                $currency = strtoupper(trim((string) $paymentAttributes['currency']));
            }
            break;
        }

        if ($amount === null) {
            $lineItems = is_array($attributes['line_items'] ?? null) ? $attributes['line_items'] : [];
            $lineItemTotal = 0;
            $hasLineItemAmount = false;
            foreach ($lineItems as $lineItem) {
                if (! is_array($lineItem)) {
                    continue;
                }
                $lineItemAttributes = is_array($lineItem['attributes'] ?? null) ? $lineItem['attributes'] : $lineItem;
                if (! isset($lineItemAttributes['amount']) || ! is_numeric($lineItemAttributes['amount'])) {
                    continue;
                }
                $hasLineItemAmount = true;
                $lineItemTotal += (int) $lineItemAttributes['amount'] * max((int) ($lineItemAttributes['quantity'] ?? 1), 1);
                if ($currency === '' && filled($lineItemAttributes['currency'] ?? null)) {
                    $currency = strtoupper(trim((string) $lineItemAttributes['currency']));
                }
            }
            if ($hasLineItemAmount) {
                $amount = $lineItemTotal;
            }
        }

        $expectedAmount = (int) round((float) $booking->payment_amount * 100);
        $amountMatches = $amount === null || hash_equals((string) $expectedAmount, (string) $amount);
        $currencyMatches = $currency === '' || $currency === 'PHP';

        if (! $amountMatches || ! $currencyMatches) {
            Log::warning('Rejected PayMongo paid event with mismatched payment details.', [
                'booking_id' => $booking->id,
                'expected_amount' => $expectedAmount,
                'received_amount' => $amount,
                'received_currency' => $currency,
            ]);

            return false;
        }

        return true;
    }
}
