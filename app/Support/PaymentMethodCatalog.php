<?php

namespace App\Support;

use Carbon\Carbon;

class PaymentMethodCatalog
{
    public const FULL_PAYMENT_CUTOFF_MINUTES = 60;

    public const TYPE_DOWNPAYMENT = 'downpayment';

    public const TYPE_FULL = 'full';

    public const METHOD_PAYMONGO = 'paymongo';

    public const METHOD_CASH_COUNTER = 'cash_counter';

    public const CHANNEL_GCASH = 'gcash';

    public const CHANNEL_QRPH = 'qrph';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    /**
     * @return array<string, array{label: string, qr: string, brand: string, initials: string, featured?: bool}>
     */
    public static function methods(): array
    {
        return [
            self::METHOD_PAYMONGO => [
                'label' => 'PayMongo',
                'qr' => '',
                'brand' => '#00a3e0',
                'initials' => 'PM',
                'featured' => true,
            ],
            'gcash' => [
                'label' => 'GCash',
                'qr' => asset('images/payment-qr/gcash.svg'),
                'brand' => '#007cff',
                'initials' => 'GC',
                'featured' => true,
            ],
            self::CHANNEL_QRPH => [
                'label' => 'QR Ph',
                'qr' => '',
                'brand' => '#17313d',
                'initials' => 'QR',
                'online_only' => true,
            ],
            'unionbank' => [
                'label' => 'UnionBank',
                'qr' => asset('images/payment-qr/unionbank.svg'),
                'brand' => '#f7941d',
                'initials' => 'UB',
            ],
            'maribank' => [
                'label' => 'MariBank',
                'qr' => asset('images/payment-qr/maribank.svg'),
                'brand' => '#e31937',
                'initials' => 'MB',
            ],
            'gotyme' => [
                'label' => 'GoTyme',
                'qr' => asset('images/payment-qr/gotyme.svg'),
                'brand' => '#6b2d8b',
                'initials' => 'GT',
            ],
            'landbank' => [
                'label' => 'Landbank',
                'qr' => asset('images/payment-qr/landbank.svg'),
                'brand' => '#007a33',
                'initials' => 'LB',
            ],
            self::METHOD_CASH_COUNTER => [
                'label' => 'Pay at counter (cash)',
                'qr' => '',
                'brand' => '#2d6a4f',
                'initials' => '₱',
                'walk_in_only' => true,
            ],
        ];
    }

    /**
     * Payment methods available to staff when recording walk-in / counter payments.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function staffMethods(): array
    {
        return array_intersect_key(self::methods(), array_flip([
            self::METHOD_PAYMONGO,
            self::METHOD_CASH_COUNTER,
        ]));
    }

    /**
     * PayMongo hosted-checkout channels shown to customers online.
     *
     * @return array<int, array{key: string, label: string, brand: string, initials: string, refund_note: string}>
     */
    public static function paymongoChannelOptions(array $channelKeys): array
    {
        $notes = [
            self::CHANNEL_GCASH => 'Choose GCash on PayMongo. Cancellations refund automatically back to your GCash wallet.',
            self::CHANNEL_QRPH => 'Choose QR Ph on PayMongo and scan with any bank or e-wallet app. Cancellations are refunded manually by the spa.',
        ];

        $options = [];

        foreach ($channelKeys as $key) {
            $key = strtolower(trim((string) $key));
            $method = self::methods()[$key] ?? null;

            if (! is_array($method)) {
                $options[] = [
                    'key' => $key,
                    'label' => ucfirst(str_replace('_', ' ', $key)),
                    'brand' => '#00a3e0',
                    'initials' => strtoupper(substr($key, 0, 2)),
                    'refund_note' => $notes[$key] ?? 'Paid via PayMongo.',
                ];

                continue;
            }

            $options[] = [
                'key' => $key,
                'label' => (string) $method['label'],
                'brand' => (string) ($method['brand'] ?? '#00a3e0'),
                'initials' => (string) ($method['initials'] ?? strtoupper(substr($key, 0, 2))),
                'refund_note' => $notes[$key] ?? 'Paid via PayMongo.',
            ];
        }

        return $options;
    }

    public static function isPaymongoOnlineBooking(?string $paymentMethod, ?string $checkoutSessionId, ?string $paymentTransactionId): bool
    {
        if (filled($checkoutSessionId)) {
            return true;
        }

        if (str_starts_with(trim((string) $paymentTransactionId), 'pay_')) {
            return true;
        }

        return in_array($paymentMethod, [self::METHOD_PAYMONGO, self::CHANNEL_GCASH, self::CHANNEL_QRPH], true);
    }

    public static function normalizePaymongoChannel(?string $channel): string
    {
        $channel = strtolower(trim((string) $channel));

        return match ($channel) {
            self::CHANNEL_GCASH => self::CHANNEL_GCASH,
            self::CHANNEL_QRPH => self::CHANNEL_QRPH,
            default => self::METHOD_PAYMONGO,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function staffMethodKeys(): array
    {
        return array_keys(self::staffMethods());
    }

    public static function isCashCounter(?string $method): bool
    {
        return $method === self::METHOD_CASH_COUNTER;
    }

    public static function labelFor(?string $method): string
    {
        if ($method === null || $method === '') {
            return '—';
        }

        return self::methods()[$method]['label'] ?? ucfirst($method);
    }

    public static function typeLabelFor(?string $type): string
    {
        return match ($type) {
            self::TYPE_DOWNPAYMENT => 'Downpayment (50%)',
            self::TYPE_FULL => 'Full payment',
            default => '—',
        };
    }

    public static function calculateAmount(float $serviceAmount, string $type): float
    {
        if ($type === self::TYPE_DOWNPAYMENT) {
            return round($serviceAmount * 0.5, 2);
        }

        return round($serviceAmount, 2);
    }

    public static function requiresFullPayment(string $bookingDate, string $timeSlot, ?Carbon $now = null): bool
    {
        $now ??= now();

        try {
            $appointment = Carbon::createFromFormat(
                'Y-m-d g:i A',
                trim($bookingDate).' '.trim($timeSlot),
                $now->getTimezone(),
            );
        } catch (\Throwable) {
            return false;
        }

        return $appointment->greaterThan($now)
            && $appointment->lessThan($now->copy()->addMinutes(self::FULL_PAYMENT_CUTOFF_MINUTES));
    }

    /**
     * @return array<int, string>
     */
    public static function methodKeys(): array
    {
        return array_keys(self::methods());
    }

    /**
     * @return array<int, string>
     */
    public static function customerMethodKeys(): array
    {
        return [self::METHOD_PAYMONGO];
    }

    public static function statusLabelFor(?string $status): string
    {
        return match ($status) {
            self::STATUS_PAID => 'Paid',
            self::STATUS_REFUNDED => 'Refunded',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_PENDING => 'Pending',
            default => '—',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function typeKeys(): array
    {
        return [self::TYPE_DOWNPAYMENT, self::TYPE_FULL];
    }
}
