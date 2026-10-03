<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\SpaBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class NoShowService
{
    public const REVIEW_WINDOW_MINUTES = 5;

    public const AUTOMATIC_NO_SHOW_MINUTES = SpaSessionService::START_GRACE_MINUTES + self::REVIEW_WINDOW_MINUTES;

    public function __construct(
        private readonly BookingCancellationService $cancellations,
        private readonly CustomerNotificationService $customerNotifications,
    ) {}

    public function canRecord(SpaBooking $booking, int $minutesLate = SpaSessionService::START_GRACE_MINUTES, ?Carbon $now = null): bool
    {
        $appointmentAt = $this->cancellations->appointmentAt($booking);
        $now ??= now();

        return $booking->cancelled_at === null
            && $booking->completed_at === null
            && $booking->session_started_at === null
            && in_array($booking->session_status, [null, SpaBooking::STATUS_CONFIRMED], true)
            && $appointmentAt !== null
            && $now->gte($appointmentAt->copy()->addMinutes($minutesLate));
    }

    /**
     * @return array{count: int, banned: bool, booking: SpaBooking}|null
     */
    public function record(SpaBooking $booking, int $minutesLate = SpaSessionService::START_GRACE_MINUTES): ?array
    {
        $result = DB::transaction(function () use ($booking, $minutesLate): ?array {
            $customer = User::query()->lockForUpdate()->find($booking->user_id);
            $lockedBooking = SpaBooking::query()->with('user')->lockForUpdate()->find($booking->id);

            if (! $customer instanceof User
                || ! $lockedBooking instanceof SpaBooking
                || ! $this->canRecord($lockedBooking, $minutesLate)) {
                return null;
            }

            $lockedBooking->forceFill(['session_status' => SpaBooking::STATUS_NO_SHOW])->save();

            $noShowCount = SpaBooking::query()
                ->where('user_id', $lockedBooking->user_id)
                ->where('session_status', SpaBooking::STATUS_NO_SHOW)
                ->count();

            $banned = false;
            if ($noShowCount >= 3 && $customer->isUser() && ! $customer->isWalkIn()) {
                $customer->forceFill(['banned_at' => $customer->banned_at ?? now()])->save();
                $banned = true;
            }

            return [
                'count' => $noShowCount,
                'banned' => $banned,
                'booking' => $lockedBooking->fresh(['user']),
            ];
        });

        if ($result !== null) {
            $this->customerNotifications->notifyNoShow($result['booking'], $result['count'], $result['banned']);
        }

        return $result;
    }

    public function processOverdue(): int
    {
        $processed = 0;

        SpaBooking::query()
            ->visibleToStaff()
            ->with('user')
            ->whereNull('cancelled_at')
            ->whereNull('completed_at')
            ->whereNull('session_started_at')
            ->where(function ($query): void {
                $query->whereNull('session_status')
                    ->orWhere('session_status', SpaBooking::STATUS_CONFIRMED);
            })
            ->whereDate('booking_date', '<=', now()->toDateString())
            ->orderBy('id')
            ->chunkById(100, function ($bookings) use (&$processed): void {
                foreach ($bookings as $booking) {
                    if (! $booking instanceof SpaBooking || ! $this->canRecord($booking, self::AUTOMATIC_NO_SHOW_MINUTES)) {
                        continue;
                    }

                    $result = $this->record($booking, self::AUTOMATIC_NO_SHOW_MINUTES);
                    if ($result === null) {
                        continue;
                    }

                    ActivityLog::query()->create([
                        'user_id' => null,
                        'user_role' => 'system',
                        'user_name' => 'System',
                        'action' => 'appointment.no_show',
                        'subject_type' => $result['booking']->getMorphClass(),
                        'subject_id' => $result['booking']->getKey(),
                        'description' => sprintf('Automatically marked booking #%d as no-show after the attendance review window expired.', $result['booking']->id),
                        'properties' => [
                            'booking_id' => $result['booking']->id,
                            'no_show_count' => $result['count'],
                            'customer_banned' => $result['banned'],
                            'automatic' => true,
                            'minutes_late' => self::AUTOMATIC_NO_SHOW_MINUTES,
                        ],
                        'created_at' => now(),
                    ]);
                    $processed++;
                }
            });

        return $processed;
    }
}
