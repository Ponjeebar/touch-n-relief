<?php

namespace App\Services;

use App\Models\SpaBooking;
use App\Models\User;
use App\Support\PaymentMethodCatalog;
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
        if ($booking->cancelled_at !== null || in_array($booking->session_status, [SpaBooking::STATUS_CANCELLED, SpaBooking::STATUS_NO_SHOW], true)) {
            return false;
        }

        if ($booking->completed_at !== null || $booking->session_status === SpaBooking::STATUS_COMPLETED) {
            return false;
        }

        if ($booking->session_started_at !== null || $booking->session_status === SpaBooking::STATUS_IN_SESSION) {
            return false;
        }

        if ($booking->booking_source === SpaBooking::SOURCE_ONLINE
            && $booking->payment_method === PaymentMethodCatalog::METHOD_PAYMONGO
            && $booking->payment_status === PaymentMethodCatalog::STATUS_PENDING) {
            return $booking->hasActivePaymentHold();
        }

        $appointment = $this->appointmentAt($booking);

        if ($appointment === null || $appointment->lte(now())) {
            return false;
        }

        return now()->lt($this->cancellationDeadline($booking));
    }

    public function cancellationDeadline(SpaBooking $booking): Carbon
    {
        $hours = app(SiteSettingsService::class)->cancellationCutoffHours();
        $deadline = $this->appointmentAt($booking)->subHours($hours);
        if ($booking->cancellation_deadline_at !== null) {
            $deadline = $deadline->min($booking->cancellation_deadline_at);
        }
        if ($booking->rescheduled_from_date !== null && filled($booking->rescheduled_from_time_slot)) {
            $previous = Carbon::parse($booking->rescheduled_from_date->format('Y-m-d').' '.$booking->rescheduled_from_time_slot)->subHours($hours);
            $deadline = $deadline->min($previous);
        }

        return $deadline;
    }

    public function preview(SpaBooking $booking): array
    {
        $allowed = $this->canCancel($booking);
        $refundAmount = $allowed ? $this->refunds->refundableAmount($booking) : 0.0;

        return [
            'can_cancel' => $allowed,
            'current_appointment' => $booking->service_name.' · '.$booking->therapist_name.' · '.$booking->booking_date->format('M d, Y').' at '.$booking->time_slot,
            'was_rescheduled' => $booking->rescheduled_at !== null,
            'previous_appointment' => $booking->rescheduled_from_date !== null
                ? trim(($booking->rescheduled_from_therapist_name ?? '').' · '.$booking->rescheduled_from_date->format('M d, Y').' at '.$booking->rescheduled_from_time_slot, ' ·')
                : null,
            'paid_amount' => '₱'.number_format($booking->totalPaidAmount(), 2),
            'refund_amount' => '₱'.number_format($refundAmount, 2),
            'message' => ! $allowed
                ? 'This appointment is no longer eligible for customer cancellation. Contact the spa for assistance.'
                : ($refundAmount > 0
                    ? 'Eligible refund: ₱'.number_format($refundAmount, 2).'. Online payments follow the existing gateway refund process; counter payments require staff refund handling.'
                    : 'No refundable payment remains for this appointment.'),
        ];
    }

    /**
     * Staff may cancel any confirmed booking that has not started, including walk-ins.
     */
    public function canStaffCancel(SpaBooking $booking): bool
    {
        if ($booking->cancelled_at !== null || in_array($booking->session_status, [SpaBooking::STATUS_CANCELLED, SpaBooking::STATUS_NO_SHOW], true)) {
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
            User::query()->whereKey($booking->user_id)->lockForUpdate()->first();
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
                'booking' => 'This booking can no longer be cancelled because it is inside the cancellation cutoff period.',
            ]);
        }

        $reason = $this->resolveReasonText($reasonCode, $otherText);

        return DB::transaction(function () use ($booking, $reason): SpaBooking {
            User::query()->whereKey($booking->user_id)->lockForUpdate()->first();
            $locked = SpaBooking::query()->lockForUpdate()->findOrFail($booking->id);

            if (! $this->canCancel($locked)) {
                throw ValidationException::withMessages([
                    'booking' => 'This booking was updated and can no longer be cancelled.',
                ]);
            }

            $locked->cancelled_at = now();
            $locked->cancellation_reason = $reason;
            $locked->session_status = SpaBooking::STATUS_CANCELLED;
            $locked->save();

            if ($locked->booking_source === SpaBooking::SOURCE_ONLINE
                && $locked->payment_method === PaymentMethodCatalog::METHOD_PAYMONGO
                && $locked->payment_status === PaymentMethodCatalog::STATUS_PENDING) {
                $locked->forceFill(['refund_status' => BookingRefundService::STATUS_NOT_APPLICABLE])->save();

                return $locked->fresh();
            }

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
