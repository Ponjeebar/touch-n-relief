<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\GatewayPaymentReconciliation;
use App\Models\MembershipPurchase;
use App\Models\SpaBooking;
use App\Models\StaffNotification;
use App\Models\User;
use App\Support\PaymentMethodCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GatewayPaymentReconciliationService
{
    public function record(SpaBooking|MembershipPurchase $subject, string $paymentId, ?array $evidence = null, string $reason = 'distinct_paid_reference'): void
    {
        if (! str_starts_with($paymentId, 'pay_')) {
            return;
        }
        if ($subject->payment_transaction_id === $paymentId
            && in_array($subject->payment_status, [PaymentMethodCatalog::STATUS_PAID, PaymentMethodCatalog::STATUS_REFUNDED], true)) {
            Log::warning('Conflicting evidence for an allocated payment; original allocation retained.', ['gateway_payment_id' => $paymentId]);

            return;
        }
        DB::transaction(function () use ($subject, $paymentId, $evidence, $reason): void {
            [$amount, $currency] = $this->amountAndCurrency($evidence, $paymentId);
            $record = GatewayPaymentReconciliation::firstOrCreate(['gateway_payment_id' => $paymentId], [
                'spa_booking_id' => $subject instanceof SpaBooking ? $subject->id : null,
                'membership_purchase_id' => $subject instanceof MembershipPurchase ? $subject->id : null,
                'allocated_payment_id' => $subject->payment_transaction_id,
                'amount' => $amount, 'currency' => $currency, 'payment_method' => $subject->payment_method,
                'status' => 'open', 'reason' => $reason, 'received_at' => now(),
            ]);
            if (! $record->wasRecentlyCreated) {
                if (($amount !== null && (float) $record->amount !== $amount) || $record->currency !== $currency
                    || $record->spa_booking_id !== ($subject instanceof SpaBooking ? $subject->id : null)
                    || $record->membership_purchase_id !== ($subject instanceof MembershipPurchase ? $subject->id : null)) {
                    Log::warning('Contradictory gateway reconciliation evidence; existing record retained.', ['gateway_payment_id' => $paymentId]);
                }

                return;
            }
            ActivityLog::create([
                'user_role' => 'system', 'user_name' => 'System', 'action' => 'payment.reconciliation_required',
                'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->id,
                'description' => 'Gateway payment '.$paymentId.' requires reconciliation; allocated payment and sales were not changed.',
                'properties' => ['gateway_payment_id' => $paymentId, 'allocated_payment_id' => $subject->payment_transaction_id, 'amount' => $amount, 'currency' => $currency, 'reason' => $reason], 'created_at' => now(),
            ]);
            foreach (User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_RECEPTIONIST])->get(['id', 'role']) as $staff) {
                StaffNotification::firstOrCreate(['staff_user_id' => $staff->id, 'event_key' => 'gateway-review:'.$paymentId], [
                    'spa_booking_id' => $subject instanceof SpaBooking ? $subject->id : null,
                    'type' => 'system', 'title' => $reason === 'distinct_paid_reference' ? 'Extra payment requires review' : 'Payment details require review',
                    'message' => $paymentId.' was retained for reconciliation. Do not issue another payout without verifying the gateway record.',
                    'url' => $staff->isAdmin() ? route('reporting.index').'#gateway-reconciliation' : ($subject instanceof SpaBooking ? route('appointments.index', ['date' => $subject->booking_date->toDateString()]) : route('receptionist.dashboard')),
                    'occurred_at' => now(),
                ]);
            }
        });
    }

    /** Missing payment amounts remain unknown; they are not inferred from a booking price. */
    private function amountAndCurrency(?array $resource, string $paymentId): array
    {
        $attributes = $resource['attributes'] ?? [];
        if (isset($attributes['payments'])) {
            $payments = $attributes['payments'];
            $attributes = [];
            foreach ($payments as $payment) {
                if (($payment['id'] ?? '') === $paymentId && ($payment['attributes']['status'] ?? '') === 'paid') {
                    $attributes = $payment['attributes'];
                    break;
                }
            }
        }
        $amount = isset($attributes['amount']) && is_numeric($attributes['amount']) && $attributes['amount'] > 0 ? round((float) $attributes['amount'] / 100, 2) : null;
        $currency = strtoupper((string) ($attributes['currency'] ?? ''));

        return [$amount, preg_match('/^[A-Z]{3}$/', $currency) ? $currency : 'UNK'];
    }
}
