<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\SpaBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class NoShowService
{
    public const ACCOUNT_RESTRICTION_THRESHOLD = 3;

    public const REVIEW_WINDOW_MINUTES = 5;

    public const AUTOMATIC_NO_SHOW_MINUTES = SpaSessionService::START_GRACE_MINUTES + self::REVIEW_WINDOW_MINUTES;

    public function __construct(
        private readonly BookingCancellationService $cancellations,
        private readonly CustomerNotificationService $customerNotifications,
        private readonly SiteSettingsService $settings,
    ) {}

    public function automaticNoShowMinutes(): int
    {
        return $this->settings->lateGraceMinutes() + $this->settings->noShowReviewMinutes();
    }

    public function canRecord(SpaBooking $booking, ?int $minutesLate = null, ?Carbon $now = null): bool
    {
        $appointmentAt = $this->cancellations->appointmentAt($booking);
        $now ??= now();
        $minutesLate ??= $this->settings->lateGraceMinutes();

        return $booking->cancelled_at === null
            && $booking->completed_at === null
            && $booking->session_started_at === null
            && $booking->no_show_reversed_at === null
            && in_array($booking->session_status, [null, SpaBooking::STATUS_CONFIRMED], true)
            && $appointmentAt !== null
            && $now->gte($appointmentAt->copy()->addMinutes($minutesLate));
    }

    /**
     * @return array{count: int, banned: bool, booking: SpaBooking}|null
     */
    public function record(SpaBooking $booking, ?int $minutesLate = null): ?array
    {
        $minutesLate ??= $this->settings->lateGraceMinutes();
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
            if ($noShowCount >= $this->settings->noShowRestrictionThreshold() && $customer->isUser() && ! $customer->isWalkIn()) {
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
        $automaticNoShowMinutes = $this->automaticNoShowMinutes();

        SpaBooking::query()
            ->visibleToStaff()
            ->with('user')
            ->whereNull('cancelled_at')
            ->whereNull('completed_at')
            ->whereNull('session_started_at')
            ->whereNull('no_show_reversed_at')
            ->where(function ($query): void {
                $query->whereNull('session_status')
                    ->orWhere('session_status', SpaBooking::STATUS_CONFIRMED);
            })
            ->whereDate('booking_date', '<=', now()->toDateString())
            ->orderBy('id')
            ->chunkById(100, function ($bookings) use (&$processed, $automaticNoShowMinutes): void {
                foreach ($bookings as $booking) {
                    if (! $booking instanceof SpaBooking || ! $this->canRecord($booking, $automaticNoShowMinutes)) {
                        continue;
                    }

                    $result = $this->record($booking, $automaticNoShowMinutes);
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
                            'minutes_late' => $automaticNoShowMinutes,
                        ],
                        'created_at' => now(),
                    ]);
                    $processed++;
                }
            });

        return $processed;
    }

    /**
     * Apply the configured threshold to existing customer No Show records.
     * The banned_at field is owned exclusively by this workflow.
     *
     * @return array{restricted: list<int>, restored: list<int>}
     */
    public function reconcileCustomerRestrictions(int $threshold): array
    {
        $threshold = max(1, $threshold);

        return DB::transaction(function () use ($threshold): array {
            $customerIdsWithNoShows = SpaBooking::query()
                ->select('user_id')
                ->where('session_status', SpaBooking::STATUS_NO_SHOW)
                ->whereNotNull('user_id');

            $customers = User::query()
                ->where('role', User::ROLE_USER)
                ->where(function ($query) use ($customerIdsWithNoShows): void {
                    $query->whereNotNull('banned_at')
                        ->orWhereIn('id', $customerIdsWithNoShows);
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $restricted = [];
            $restored = [];

            foreach ($customers as $customer) {
                $noShowCount = SpaBooking::query()
                    ->where('user_id', $customer->id)
                    ->where('session_status', SpaBooking::STATUS_NO_SHOW)
                    ->count();
                $shouldBeRestricted = $noShowCount >= $threshold;

                if ($shouldBeRestricted && $customer->banned_at === null) {
                    $customer->forceFill(['banned_at' => now()])->save();
                    $restricted[] = (int) $customer->id;
                } elseif (! $shouldBeRestricted && $customer->banned_at !== null) {
                    $customer->forceFill(['banned_at' => null])->save();
                    $restored[] = (int) $customer->id;
                }
            }

            return ['restricted' => $restricted, 'restored' => $restored];
        });
    }
}
