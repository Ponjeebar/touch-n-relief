<?php

namespace App\Services;

use App\Models\SpaBooking;
use App\Support\PaymentMethodCatalog;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaymongoService
{
    private const API_BASE = 'https://api.paymongo.com';

    public function isConfigured(): bool
    {
        $key = $this->secretKey();

        return $key !== ''
            && (str_starts_with($key, 'sk_test_') || str_starts_with($key, 'sk_live_'));
    }

    public function secretKey(): string
    {
        return (string) config('services.paymongo.secret_key');
    }

    public function webhookSecret(): string
    {
        return (string) config('services.paymongo.webhook_secret');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function createCheckoutSession(array $attributes): array
    {
        $response = $this->request()
            ->post(self::API_BASE.'/v2/checkout_sessions', [
                'data' => [
                    'attributes' => $attributes,
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('PayMongo checkout session failed: '.$response->body());
        }

        $data = $response->json('data');

        if (! is_array($data)) {
            throw new RuntimeException('PayMongo returned an invalid checkout session response.');
        }

        return $data;
    }

    public function startStaffBookingCheckout(SpaBooking $booking): string
    {
        $session = $this->createCheckoutSession([
            'billing' => [
                'name' => (string) ($booking->client_name ?: $booking->user?->name),
                'email' => (string) $booking->user?->email,
            ],
            'line_items' => [[
                'name' => $booking->service_name.' - '.PaymentMethodCatalog::typeLabelFor($booking->payment_type),
                'amount' => (int) round((float) $booking->payment_amount * 100),
                'currency' => 'PHP',
                'quantity' => 1,
            ]],
            'payment_method_types' => $this->paymentMethodTypes(),
            'success_url' => route('appointments.paymongo.success', $booking),
            'cancel_url' => route('appointments.paymongo.cancel', $booking),
            'reference_number' => (string) $booking->id,
            'metadata' => [
                'booking_id' => (string) $booking->id,
                'user_id' => (string) $booking->user_id,
                'payment_type' => (string) $booking->payment_type,
            ],
            'description' => 'Touch N Relief booking #'.$booking->id,
        ]);

        $checkoutUrl = (string) ($session['attributes']['checkout_url'] ?? '');
        $sessionId = (string) ($session['id'] ?? '');
        if ($checkoutUrl === '' || $sessionId === '') {
            throw new RuntimeException('PayMongo did not return a checkout link and session ID.');
        }

        $booking->forceFill(['paymongo_checkout_session_id' => $sessionId])->save();

        return $checkoutUrl;
    }

    public function startCustomerBookingCheckout(SpaBooking $booking): string
    {
        $session = $this->createCheckoutSession([
            'billing' => [
                'name' => (string) ($booking->user?->name ?: $booking->client_name),
                'email' => (string) $booking->user?->email,
            ],
            'line_items' => [[
                'name' => $booking->service_name.' - '.PaymentMethodCatalog::typeLabelFor($booking->payment_type),
                'amount' => (int) round((float) $booking->payment_amount * 100),
                'currency' => 'PHP',
                'quantity' => 1,
            ]],
            'payment_method_types' => $this->paymentMethodTypes(),
            'success_url' => route('paymongo.success', $booking),
            'cancel_url' => route('paymongo.cancel', $booking),
            'reference_number' => (string) $booking->id,
            'metadata' => [
                'booking_id' => (string) $booking->id,
                'user_id' => (string) $booking->user_id,
                'service_name' => (string) $booking->service_name,
                'payment_type' => (string) $booking->payment_type,
            ],
            'description' => 'Touch N Relief booking #'.$booking->id,
        ]);

        $checkoutUrl = (string) ($session['attributes']['checkout_url'] ?? '');
        $sessionId = (string) ($session['id'] ?? '');
        if ($checkoutUrl === '' || $sessionId === '') {
            throw new RuntimeException('PayMongo did not return a checkout link and session ID.');
        }

        $booking->forceFill(['paymongo_checkout_session_id' => $sessionId])->save();

        return $checkoutUrl;
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieveCheckoutSession(string $sessionId): array
    {
        $sessionId = trim($sessionId);

        if ($sessionId === '') {
            throw new RuntimeException('PayMongo session lookup failed: missing checkout session ID.');
        }

        $lastBody = '';

        foreach (['/v1/checkout_sessions/', '/v2/checkout_sessions/'] as $prefix) {
            $response = $this->request()
                ->get(self::API_BASE.$prefix.$sessionId);

            if (! $response->successful()) {
                $lastBody = $response->body();

                continue;
            }

            $data = $response->json('data');

            if (is_array($data)) {
                return $data;
            }

            $lastBody = $response->body();
        }

        throw new RuntimeException('PayMongo session lookup failed: '.$lastBody);
    }

    /**
     * @return array<string, mixed>
     */
    public function retrievePaymentIntent(string $paymentIntentId): array
    {
        $response = $this->request()
            ->get(self::API_BASE.'/v1/payment_intents/'.$paymentIntentId);

        if (! $response->successful()) {
            throw new RuntimeException('PayMongo payment intent lookup failed: '.$response->body());
        }

        $data = $response->json('data');

        if (! is_array($data)) {
            throw new RuntimeException('PayMongo returned an invalid payment intent response.');
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $session
     */
    public function isCheckoutSessionPaid(array $session): bool
    {
        $attributes = is_array($session['attributes'] ?? null) ? $session['attributes'] : [];

        $payments = is_array($attributes['payments'] ?? null) ? $attributes['payments'] : [];

        foreach ($payments as $payment) {
            if (! is_array($payment)) {
                continue;
            }

            $paymentAttributes = is_array($payment['attributes'] ?? null) ? $payment['attributes'] : [];

            if (($paymentAttributes['status'] ?? '') === 'paid') {
                return true;
            }
        }

        $paymentIntent = is_array($attributes['payment_intent'] ?? null) ? $attributes['payment_intent'] : [];
        $paymentIntentAttributes = is_array($paymentIntent['attributes'] ?? null) ? $paymentIntent['attributes'] : [];

        return ($paymentIntentAttributes['status'] ?? '') === 'succeeded';
    }

    public function verifyWebhookSignature(string $payload, ?string $signatureHeader): bool
    {
        $secret = $this->webhookSecret();

        if ($secret === '' || $signatureHeader === null || $signatureHeader === '') {
            return false;
        }

        $parts = [];

        foreach (explode(',', $signatureHeader) as $element) {
            $pair = explode('=', trim($element), 2);

            if (count($pair) === 2) {
                $parts[$pair[0]] = $pair[1];
            }
        }

        $timestamp = $parts['t'] ?? '';
        $signature = $parts['te'] ?? ($parts['li'] ?? '');

        if ($timestamp === '' || $signature === '') {
            return false;
        }

        $computed = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return hash_equals($computed, $signature);
    }

    /**
     * @return array<int, string>
     */
    public function paymentMethodTypes(): array
    {
        $configured = config('services.paymongo.payment_method_types');

        if (is_array($configured) && $configured !== []) {
            return array_values(array_filter(array_map('strval', $configured)));
        }

        return ['gcash', 'qrph'];
    }

    /**
     * @return array<string, mixed>
     */
    public function retrievePayment(string $paymentId): array
    {
        $response = $this->request()
            ->get(self::API_BASE.'/v1/payments/'.$paymentId);

        if (! $response->successful()) {
            throw new RuntimeException('PayMongo payment lookup failed: '.$response->body());
        }

        $data = $response->json('data');

        if (! is_array($data)) {
            throw new RuntimeException('PayMongo returned an invalid payment response.');
        }

        return $data;
    }

    public function resolvePaymentIdForBooking(SpaBooking $booking): string
    {
        $stored = trim((string) ($booking->payment_transaction_id ?? ''));

        if (str_starts_with($stored, 'pay_')) {
            return $stored;
        }

        if (str_starts_with($stored, 'pi_')) {
            $paymentId = $this->extractPaidPaymentIdFromPaymentsContainer(
                $this->retrievePaymentIntent($stored),
            );

            if ($paymentId !== '') {
                return $paymentId;
            }
        }

        $sessionId = trim((string) ($booking->paymongo_checkout_session_id ?? ''));

        if ($sessionId !== '') {
            try {
                $paymentId = $this->extractPaidPaymentIdFromSession(
                    $this->retrieveCheckoutSession($sessionId),
                );

                if ($paymentId !== '') {
                    return $paymentId;
                }
            } catch (\Throwable) {
                // Fall back to stored reference below.
            }
        }

        return str_starts_with($stored, 'pay_') ? $stored : '';
    }

    /**
     * @param  array<string, mixed>  $session
     */
    public function extractPaidPaymentIdFromSession(array $session): string
    {
        $attributes = is_array($session['attributes'] ?? null) ? $session['attributes'] : [];
        $paymentId = $this->extractPaidPaymentIdFromPaymentsList(
            is_array($attributes['payments'] ?? null) ? $attributes['payments'] : [],
        );

        if ($paymentId !== '') {
            return $paymentId;
        }

        $paymentIntent = is_array($attributes['payment_intent'] ?? null) ? $attributes['payment_intent'] : [];

        return $this->extractPaidPaymentIdFromPaymentsContainer($paymentIntent);
    }

    /**
     * @param  array<string, mixed>  $session
     */
    public function extractPaymentChannelFromSession(array $session): string
    {
        $attributes = is_array($session['attributes'] ?? null) ? $session['attributes'] : [];
        $payments = is_array($attributes['payments'] ?? null) ? $attributes['payments'] : [];

        foreach (array_reverse($payments) as $payment) {
            if (! is_array($payment)) {
                continue;
            }

            $paymentAttributes = is_array($payment['attributes'] ?? null) ? $payment['attributes'] : [];

            if (($paymentAttributes['status'] ?? '') !== 'paid') {
                continue;
            }

            $source = is_array($paymentAttributes['source'] ?? null) ? $paymentAttributes['source'] : [];
            $sourceType = strtolower((string) ($source['type'] ?? ''));

            if ($sourceType !== '') {
                return $sourceType;
            }
        }

        $paymentIntent = is_array($attributes['payment_intent'] ?? null) ? $attributes['payment_intent'] : [];
        $intentAttributes = is_array($paymentIntent['attributes'] ?? null) ? $paymentIntent['attributes'] : [];
        $intentPayments = is_array($intentAttributes['payments'] ?? null) ? $intentAttributes['payments'] : [];

        foreach (array_reverse($intentPayments) as $payment) {
            if (! is_array($payment)) {
                continue;
            }

            $paymentAttributes = is_array($payment['attributes'] ?? null) ? $payment['attributes'] : [];

            if (($paymentAttributes['status'] ?? '') !== 'paid') {
                continue;
            }

            $source = is_array($paymentAttributes['source'] ?? null) ? $paymentAttributes['source'] : [];
            $sourceType = strtolower((string) ($source['type'] ?? ''));

            if ($sourceType !== '') {
                return $sourceType;
            }
        }

        return '';
    }

    public function resolvePaymentChannelForPaymentId(string $paymentId): string
    {
        if ($paymentId === '' || ! str_starts_with($paymentId, 'pay_')) {
            return '';
        }

        try {
            $payment = $this->retrievePayment($paymentId);
            $attributes = is_array($payment['attributes'] ?? null) ? $payment['attributes'] : [];
            $source = is_array($attributes['source'] ?? null) ? $attributes['source'] : [];

            return strtolower((string) ($source['type'] ?? ''));
        } catch (\Throwable) {
            return '';
        }
    }

    public function resolvePaymentChannelForBooking(SpaBooking $booking): string
    {
        $paymentId = $this->resolvePaymentIdForBooking($booking);

        if ($paymentId !== '') {
            $channel = $this->resolvePaymentChannelForPaymentId($paymentId);

            if ($channel !== '') {
                return $channel;
            }
        }

        $sessionId = trim((string) ($booking->paymongo_checkout_session_id ?? ''));

        if ($sessionId !== '') {
            try {
                $channel = $this->extractPaymentChannelFromSession(
                    $this->retrieveCheckoutSession($sessionId),
                );

                if ($channel !== '') {
                    return $channel;
                }
            } catch (\Throwable) {
                // Fall through.
            }
        }

        return '';
    }

    public function paymentSupportsApiRefund(string $paymentId): bool
    {
        if ($paymentId === '' || ! str_starts_with($paymentId, 'pay_')) {
            return false;
        }

        try {
            $payment = $this->retrievePayment($paymentId);
            $attributes = is_array($payment['attributes'] ?? null) ? $payment['attributes'] : [];
            $source = is_array($attributes['source'] ?? null) ? $attributes['source'] : [];
            $sourceType = strtolower((string) ($source['type'] ?? ''));

            if ($sourceType === '') {
                return false;
            }

            return ! in_array($sourceType, self::NON_API_REFUNDABLE_SOURCE_TYPES, true);
        } catch (\Throwable) {
            return false;
        }
    }

    /** @var array<int, string> */
    private const NON_API_REFUNDABLE_SOURCE_TYPES = [
        'qrph',
        'ubp',
        'unionbank',
    ];

    /**
     * @return array<string, mixed>
     */
    public function createRefund(string $paymentId, int $amountCentavos, string $reason = 'requested_by_customer', ?string $notes = null): array
    {
        if (! str_starts_with($paymentId, 'pay_')) {
            throw new RuntimeException('PayMongo refund requires a payment ID (pay_xxx).');
        }

        if ($amountCentavos < 100) {
            throw new RuntimeException('PayMongo refund amount must be at least 100 centavos (₱1.00).');
        }

        $attributes = [
            'amount' => $amountCentavos,
            'payment_id' => $paymentId,
            'reason' => $reason,
        ];

        if ($notes !== null && trim($notes) !== '') {
            $attributes['notes'] = mb_substr(trim($notes), 0, 255);
        }

        $response = $this->request()
            ->post(self::API_BASE.'/v1/refunds', [
                'data' => [
                    'attributes' => $attributes,
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('PayMongo refund failed: '.$response->body());
        }

        $data = $response->json('data');

        if (! is_array($data)) {
            throw new RuntimeException('PayMongo returned an invalid refund response.');
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $container
     */
    private function extractPaidPaymentIdFromPaymentsContainer(array $container): string
    {
        $attributes = is_array($container['attributes'] ?? null) ? $container['attributes'] : [];

        return $this->extractPaidPaymentIdFromPaymentsList(
            is_array($attributes['payments'] ?? null) ? $attributes['payments'] : [],
        );
    }

    /**
     * @param  array<int, mixed>  $payments
     */
    private function extractPaidPaymentIdFromPaymentsList(array $payments): string
    {
        foreach (array_reverse($payments) as $payment) {
            if (! is_array($payment)) {
                continue;
            }

            $paymentId = (string) ($payment['id'] ?? '');
            $paymentAttributes = is_array($payment['attributes'] ?? null) ? $payment['attributes'] : [];

            if ($paymentId !== ''
                && str_starts_with($paymentId, 'pay_')
                && ($paymentAttributes['status'] ?? '') === 'paid') {
                return $paymentId;
            }
        }

        return '';
    }

    private function request(): PendingRequest
    {
        return Http::withBasicAuth($this->secretKey(), '')
            ->acceptJson()
            ->asJson();
    }
}
