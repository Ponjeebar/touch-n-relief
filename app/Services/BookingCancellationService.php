<?php

namespace App\Services;

use App\Models\SpaBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingCancellationService
{
    public function __construct(
        private readonly BookingRefundService $refunds,
    ) {}
    /** @var array<string, string|null> */
    public const REASON_OPTIONS = [
        'schedule_conflict' => 'My schedule changed / I\'m no longer available.',
        'price_cheaper' => 'Found a cheaper alternative.',
        'price_expensive' => 'Too expensive.',
        'service_different' => 'Decided to use a different service.',
        'platform_confusing' => 'The booking process was confusing.',
        'other_therapist' => 'I decided to choose other therapist.',
        'other' => null,
    ];

    public function canCancel(SpaBooking $booking): bool
    {
        if ($booking->cancelled_at !== null || $booking->session_status === SpaBooking::STATUS_CANCELLED) {
            return false;
        }

        if ($booking->completed_at !== null || $booking->session_status === SpaBooking::STATUS_COMPLETED) {
            return false;
        }

        if ($booking->session_started_at !== null || $booking->session_status === SpaBooking::STATUS_IN_SESSION) {
            return false;
        }

        $appointment = $this->appointmentAt($booking);

        if ($appointment === null || $appointment->lte(now())) {
            return false;
        }

        return now()->lt($appointment);
    }

    /**
     * Staff may cancel any confirmed booking that has not started, including walk-ins.
     */
    public function canStaffCancel(SpaBooking $booking): bool
    {
        if ($booking->cancelled_at !== null || $booking->session_status === SpaBooking::STATUS_CANCELLED) {
            return false;
        }

        if ($booking->completed_at !== null || $booking->session_status === SpaBooking::STATUS_COMPLETED) {
            return false;
        }

        if ($booking->session_started_at !== null || $booking->session_status === SpaBooking::STATUS_IN_SESSION) {
            return false;
        }

        return (int) $booking->user_id > 0
            && User::query()->whereKey($booking->user_id)->exists();
    }

    public function staffCancel(SpaBooking $booking, ?string $note = null): void
    {
        DB::transaction(function () use ($booking, $note): void {
            $locked = SpaBooking::query()->lockForUpdate()->findOrFail($booking->id);

            if (! $this->canStaffCancel($locked)) {
                throw ValidationException::withMessages([
                    'booking' => 'This appointment can no longer be cancelled.',
                ]);
            }

            $reason = trim((string) $note);

            $locked->cancelled_at = now();
            $locked->cancellation_reason = $reason !== '' ? $reason : 'Cancelled by staff.';
            $locked->session_status = SpaBooking::STATUS_CANCELLED;
            $locked->save();

            $this->refunds->processRefund($locked->fresh());
        });

        $booking->refresh();

        app(CustomerNotificationService::class)->notifyStaffCancelled($booking);
    }

    public function cancel(SpaBooking $booking, string $reasonCode, ?string $otherText = null): SpaBooking
    {
        if (! $this->canCancel($booking)) {
            throw ValidationException::withMessages([
                'booking' => 'This booking can no longer be cancelled because your session has already started.',
            ]);
        }

        $reason = $this->resolveReasonText($reasonCode, $otherText);

        return DB::transaction(function () use ($booking, $reason): SpaBooking {
            $locked = SpaBooking::query()->lockForUpdate()->findOrFail($booking->id);

            $locked->cancelled_at = now();
            $locked->cancellation_reason = $reason;
            $locked->session_status = SpaBooking::STATUS_CANCELLED;
            $locked->save();

            return $this->refunds->processRefund($locked->fresh());
        });
    }

    public function resolveReasonText(string $reasonCode, ?string $otherText = null): string
    {
        if ($reasonCode === 'other') {
            $text = trim((string) $otherText);
            if ($text === '') {
                throw ValidationException::withMessages([
                    'cancellation_other' => 'Please tell us why you are cancelling.',
                ]);
            }

            return $text;
        }

        if (! array_key_exists($reasonCode, self::REASON_OPTIONS)) {
            throw ValidationException::withMessages([
                'cancellation_reason' => 'Please choose a valid cancellation reason.',
            ]);
        }

        return (string) self::REASON_OPTIONS[$reasonCode];
    }

    public function appointmentAt(SpaBooking $booking): ?Carbon
    {
        try {
            return Carbon::parse($booking->booking_date->format('Y-m-d').' '.$booking->time_slot);
        } catch (\Throwable) {
            return null;
        }
    }
}
