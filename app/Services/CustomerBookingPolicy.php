<?php

namespace App\Services;

use App\Models\SpaBooking;
use App\Models\User;
use App\Support\PaymentMethodCatalog;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class CustomerBookingPolicy
{
    public function minimumLeadMinutes(): int
    {
        return max((int) config('touchnrelief.booking.customer_minimum_lead_minutes', 30), SpaBooking::PAYMENT_HOLD_MINUTES + 1);
    }

    public function assertMayCreatePaymentHold(User $user): void
    {
        if ($this->activePaymentHold($user) instanceof SpaBooking) {
            throw ValidationException::withMessages([
                'payment' => 'You already have an unpaid reservation. Continue its payment or cancel it before booking another appointment.',
            ]);
        }

        $cooldownUntil = $this->cooldownUntil($user);
        if ($cooldownUntil !== null && now()->lt($cooldownUntil)) {
            throw ValidationException::withMessages([
                'payment' => 'Online booking is temporarily paused because several payment holds expired. Try again after '.$cooldownUntil->format('g:i A').'.',
            ]);
        }
    }

    public function activePaymentHold(User $user): ?SpaBooking
    {
        return $this->unpaidOnlineBookings($user)
            ->where('payment_status', PaymentMethodCatalog::STATUS_PENDING)
            ->where('created_at', '>', now()->subMinutes(SpaBooking::PAYMENT_HOLD_MINUTES))
            ->latest('created_at')
            ->first();
    }

    public function cooldownUntil(User $user): ?CarbonInterface
    {
        $lookbackHours = max((int) config('touchnrelief.booking.expired_hold_lookback_hours', 24), 1);
        $expiredHoldLimit = max((int) config('touchnrelief.booking.expired_hold_limit', 3), 1);
        $cooldownMinutes = max((int) config('touchnrelief.booking.expired_hold_cooldown_minutes', 60), 1);
        $expiredCutoff = now()->subMinutes(SpaBooking::PAYMENT_HOLD_MINUTES);

        $expiredHolds = $this->unpaidOnlineBookings($user)
            ->where('payment_status', PaymentMethodCatalog::STATUS_PENDING)
            ->whereBetween('created_at', [now()->subHours($lookbackHours), $expiredCutoff])
            ->latest('created_at')
            ->limit($expiredHoldLimit)
            ->get(['created_at']);

        if ($expiredHolds->count() < $expiredHoldLimit) {
            return null;
        }

        return $expiredHolds->first()->created_at
            ->copy()
            ->addMinutes(SpaBooking::PAYMENT_HOLD_MINUTES + $cooldownMinutes);
    }

    private function unpaidOnlineBookings(User $user)
    {
        return SpaBooking::query()
            ->where('user_id', $user->id)
            ->where('booking_source', SpaBooking::SOURCE_ONLINE)
            ->where('payment_method', PaymentMethodCatalog::METHOD_PAYMONGO)
            ->whereNull('cancelled_at')
            ->whereNull('completed_at')
            ->where(function ($query): void {
                $query->whereNull('session_status')
                    ->orWhere('session_status', SpaBooking::STATUS_CONFIRMED);
            });
    }
}
