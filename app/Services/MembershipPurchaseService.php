<?php

namespace App\Services;

use App\Models\MembershipPurchase;
use App\Models\User;
use App\Support\PaymentMethodCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MembershipPurchaseService
{
    public function confirm(
        MembershipPurchase $purchase,
        string $paymentId,
        ?string $paymentChannel = null,
        ?string $checkoutSessionId = null,
    ): MembershipPurchase {
        return DB::transaction(function () use ($purchase, $paymentId, $paymentChannel, $checkoutSessionId): MembershipPurchase {
            User::query()->whereKey($purchase->user_id)->lockForUpdate()->firstOrFail();
            $locked = MembershipPurchase::query()->lockForUpdate()->findOrFail($purchase->id);

            if ($locked->payment_status === PaymentMethodCatalog::STATUS_PAID) {
                return $locked;
            }

            $previousExpiry = MembershipPurchase::query()
                ->where('user_id', $locked->user_id)
                ->where('payment_status', PaymentMethodCatalog::STATUS_PAID)
                ->where('expires_at', '>', now())
                ->whereKeyNot($locked->id)
                ->max('expires_at');
            $startsAt = $previousExpiry !== null
                ? Carbon::parse($previousExpiry)
                : now();
            $startsAt = $startsAt->gt(now()) ? $startsAt : now();
            $expiresAt = $startsAt->copy()->addDays(max((int) $locked->validity_days, 1));

            $locked->forceFill([
                'payment_status' => PaymentMethodCatalog::STATUS_PAID,
                'status' => $startsAt->gt(now()) ? MembershipPurchase::STATUS_SCHEDULED : MembershipPurchase::STATUS_ACTIVE,
                'payment_method' => PaymentMethodCatalog::normalizePaymongoChannel($paymentChannel ?: $locked->payment_method),
                'payment_transaction_id' => $paymentId !== '' ? $paymentId : $locked->payment_transaction_id,
                'paymongo_checkout_session_id' => $checkoutSessionId ?: $locked->paymongo_checkout_session_id,
                'paid_at' => now(),
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
            ])->save();

            return $locked;
        });
    }

    /** @param array<string, mixed> $resource */
    public function evidenceMatches(MembershipPurchase $purchase, array $resource): bool
    {
        $attributes = is_array($resource['attributes'] ?? null) ? $resource['attributes'] : [];
        $amount = isset($attributes['amount']) && is_numeric($attributes['amount'])
            ? (int) $attributes['amount']
            : null;
        $currency = strtoupper(trim((string) ($attributes['currency'] ?? '')));

        foreach ((array) ($attributes['payments'] ?? []) as $payment) {
            if (! is_array($payment)) {
                continue;
            }
            $paymentAttributes = is_array($payment['attributes'] ?? null) ? $payment['attributes'] : [];
            if (($paymentAttributes['status'] ?? '') !== 'paid') {
                continue;
            }
            $amount ??= isset($paymentAttributes['amount']) && is_numeric($paymentAttributes['amount'])
                ? (int) $paymentAttributes['amount']
                : null;
            $currency = $currency !== '' ? $currency : strtoupper(trim((string) ($paymentAttributes['currency'] ?? '')));
        }

        $expected = (int) round((float) $purchase->amount * 100);
        if (($amount !== null && $amount !== $expected) || ($currency !== '' && $currency !== 'PHP')) {
            Log::warning('Rejected membership payment with mismatched evidence.', [
                'membership_purchase_id' => $purchase->id,
                'expected_amount' => $expected,
                'received_amount' => $amount,
                'currency' => $currency,
            ]);

            return false;
        }

        return true;
    }
}
