<?php

namespace App\Services;

use App\Models\CustomerNotification;
use App\Models\SpaBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CustomerNotificationService
{
    public function __construct(
        private readonly BookingCancellationService $cancellations,
    ) {}

    public function notifyStaffCancelled(SpaBooking $booking): void
    {
        $booking->loadMissing('user');

        if (! $this->shouldNotify($booking->user)) {
            return;
        }

        $when = $this->formatSchedule($booking);
        $staffLabel = $this->staffActionLabel();

        $this->createNotification(
            userId: (int) $booking->user_id,
            booking: $booking,
            type: CustomerNotification::TYPE_CANCELLED,
            title: 'Appointment cancelled',
            message: sprintf(
                'Your %s appointment on %s was cancelled by our %s.',
                (string) $booking->service_name,
                $when,
                $staffLabel,
            ),
            details: [
                'service_name' => (string) $booking->service_name,
                'therapist_name' => trim((string) ($booking->therapist_name ?? '')),
                'booking_date' => $booking->booking_date?->format('Y-m-d'),
                'booking_date_display' => $booking->booking_date?->format('M d, Y'),
                'time_slot' => (string) $booking->time_slot,
                'duration_minutes' => (int) ($booking->duration_minutes ?? 0),
                'amount' => (string) $booking->amount,
                'cancellation_reason' => trim((string) ($booking->cancellation_reason ?? '')),
                'action_by' => $staffLabel,
            ],
            dedupKey: 'booking:'.$booking->id.':cancelled:'.($booking->cancelled_at?->timestamp ?? now()->timestamp),
        );
    }

    public function notifyStaffRescheduled(SpaBooking $booking): void
    {
        $booking->loadMissing('user');

        if (! $this->shouldNotify($booking->user)) {
            return;
        }

        $newWhen = $this->formatSchedule($booking);
        $fromWhen = $this->formatPreviousSchedule($booking);
        $staffLabel = $this->staffActionLabel();

        $message = $fromWhen !== ''
            ? sprintf(
                'Your %s appointment was moved from %s to %s by our %s.',
                (string) $booking->service_name,
                $fromWhen,
                $newWhen,
                $staffLabel,
            )
            : sprintf(
                'Your %s appointment was rescheduled to %s by our %s.',
                (string) $booking->service_name,
                $newWhen,
                $staffLabel,
            );

        $this->createNotification(
            userId: (int) $booking->user_id,
            booking: $booking,
            type: CustomerNotification::TYPE_RESCHEDULED,
            title: 'Appointment rescheduled',
            message: $message,
            details: [
                'service_name' => (string) $booking->service_name,
                'therapist_name' => trim((string) ($booking->therapist_name ?? '')),
                'booking_date' => $booking->booking_date?->format('Y-m-d'),
                'booking_date_display' => $booking->booking_date?->format('M d, Y'),
                'time_slot' => (string) $booking->time_slot,
                'previous_date' => $booking->rescheduled_from_date?->format('Y-m-d'),
                'previous_date_display' => $booking->rescheduled_from_date?->format('M d, Y'),
                'previous_time_slot' => trim((string) ($booking->rescheduled_from_time_slot ?? '')),
                'duration_minutes' => (int) ($booking->duration_minutes ?? 0),
                'amount' => (string) $booking->amount,
                'action_by' => $staffLabel,
            ],
            dedupKey: 'booking:'.$booking->id.':rescheduled:'.($booking->rescheduled_at?->timestamp ?? now()->timestamp),
        );
    }

    public function notifyUpcomingReminder(SpaBooking $booking, string $window): void
    {
        $booking->loadMissing('user');

        if (! $this->shouldNotify($booking->user)) {
            return;
        }

        if ($booking->cancelled_at !== null || $booking->session_status === SpaBooking::STATUS_CANCELLED) {
            return;
        }

        $appointmentAt = $this->cancellations->appointmentAt($booking);
        if ($appointmentAt === null || $appointmentAt->lte(now())) {
            return;
        }

        $when = $this->formatSchedule($booking);
        $windowLabel = $window === '2h' ? 'in about 2 hours' : 'tomorrow';

        $this->createNotification(
            userId: (int) $booking->user_id,
            booking: $booking,
            type: CustomerNotification::TYPE_REMINDER,
            title: 'Appointment reminder',
            message: sprintf(
                'Reminder: your %s session is %s on %s. Please arrive 10 minutes early.',
                (string) $booking->service_name,
                $windowLabel,
                $when,
            ),
            details: [
                'service_name' => (string) $booking->service_name,
                'therapist_name' => trim((string) ($booking->therapist_name ?? '')),
                'booking_date' => $booking->booking_date?->format('Y-m-d'),
                'booking_date_display' => $booking->booking_date?->format('M d, Y'),
                'time_slot' => (string) $booking->time_slot,
                'duration_minutes' => (int) ($booking->duration_minutes ?? 0),
                'amount' => (string) $booking->amount,
                'reminder_window' => $window,
            ],
            dedupKey: 'booking:'.$booking->id.':reminder:'.$window,
        );
    }

    /**
     * @return list<CustomerNotification>
     */
    public function sendDueReminders(): array
    {
        $sent = [];

        SpaBooking::query()
            ->with('user')
            ->whereNull('cancelled_at')
            ->where('session_status', SpaBooking::STATUS_CONFIRMED)
            ->whereDate('booking_date', '>=', now()->toDateString())
            ->whereDate('booking_date', '<=', now()->addDay()->toDateString())
            ->orderBy('booking_date')
            ->orderBy('time_slot')
            ->chunkById(100, function ($bookings) use (&$sent): void {
                foreach ($bookings as $booking) {
                    if (! $booking instanceof SpaBooking) {
                        continue;
                    }

                    $appointmentAt = $this->cancellations->appointmentAt($booking);
                    if ($appointmentAt === null || $appointmentAt->lte(now())) {
                        continue;
                    }

                    $minutesUntil = now()->diffInMinutes($appointmentAt, false);
                    if ($minutesUntil < 0) {
                        continue;
                    }

                    if ($minutesUntil <= 150 && $minutesUntil >= 90) {
                        $notification = $this->notifyUpcomingReminder($booking, '2h');
                        if ($notification !== null) {
                            $sent[] = $notification;
                        }
                    } elseif ($minutesUntil <= 1470 && $minutesUntil >= 1410) {
                        $notification = $this->notifyUpcomingReminder($booking, '24h');
                        if ($notification !== null) {
                            $sent[] = $notification;
                        }
                    }
                }
            });

        return $sent;
    }

    private function shouldNotify(?User $user): bool
    {
        return $user !== null
            && ! $user->isWalkIn()
            && $user->isUser();
    }

    private function staffActionLabel(): string
    {
        $staff = auth()->user();

        if ($staff instanceof User && $staff->isReceptionist()) {
            return 'receptionist';
        }

        return 'spa team';
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function createNotification(
        int $userId,
        SpaBooking $booking,
        string $type,
        string $title,
        string $message,
        array $details,
        string $dedupKey,
    ): ?CustomerNotification {
        try {
            return DB::transaction(function () use ($userId, $booking, $type, $title, $message, $details, $dedupKey): CustomerNotification {
                $existing = CustomerNotification::query()
                    ->where('user_id', $userId)
                    ->where('dedup_key', $dedupKey)
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }

                return CustomerNotification::query()->create([
                    'user_id' => $userId,
                    'spa_booking_id' => $booking->id,
                    'type' => $type,
                    'title' => $title,
                    'message' => $message,
                    'details' => $details,
                    'dedup_key' => $dedupKey,
                ]);
            });
        } catch (\Throwable) {
            return CustomerNotification::query()
                ->where('user_id', $userId)
                ->where('dedup_key', $dedupKey)
                ->first();
        }
    }

    private function formatSchedule(SpaBooking $booking): string
    {
        $dateText = $booking->booking_date?->format('M d, Y') ?? '';
        $slot = trim((string) $booking->time_slot);

        return trim($dateText.' '.$slot);
    }

    private function formatPreviousSchedule(SpaBooking $booking): string
    {
        if ($booking->rescheduled_from_date === null) {
            return '';
        }

        $fromDate = $booking->rescheduled_from_date->format('M d, Y');
        $fromSlot = trim((string) ($booking->rescheduled_from_time_slot ?? ''));

        return $fromSlot !== '' ? trim($fromDate.' '.$fromSlot) : $fromDate;
    }
}
