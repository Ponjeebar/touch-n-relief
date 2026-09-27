<?php

namespace App\Services;

use App\Models\StaffNotification;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class NotificationFeedService
{
    public function recentBookingNotifications(int $limit = 6, ?User $user = null): array
    {
        $user ??= auth()->user();
        if (! $user || ! Schema::hasTable('staff_notifications')) {
            return [];
        }

        return StaffNotification::query()
            ->where('staff_user_id', $user->id)
            ->latest('occurred_at')
            ->latest('id')
            ->limit(max($limit * 10, 60))
            ->get()
            ->groupBy(fn (StaffNotification $note): string => $this->semanticKey($note->event_key))
            ->take($limit)
            ->map(function (Collection $duplicates): array {
                /** @var StaffNotification $note */
                $note = $duplicates->first();

                return [
                    'id' => $note->id, 'type' => $note->type, 'title' => $note->title, 'message' => $note->message,
                    'time' => $note->occurred_at?->diffForHumans() ?? 'Just now',
                    'notification_at' => $note->occurred_at?->toIso8601String(), 'notification_key' => $this->semanticKey($note->event_key),
                    'url' => $note->url ?: route('appointments.index'), 'is_read' => $duplicates->every(fn (StaffNotification $duplicate): bool => $duplicate->read_at !== null),
                ];
            })
            ->values()
            ->all();
    }

    public function unreadCount(?User $user = null): int
    {
        $user ??= auth()->user();
        if (! $user || ! Schema::hasTable('staff_notifications')) {
            return 0;
        }

        return StaffNotification::query()
            ->where('staff_user_id', $user->id)
            ->whereNull('read_at')
            ->pluck('event_key')
            ->map(fn (string $eventKey): string => $this->semanticKey($eventKey))
            ->unique()
            ->count();
    }

    public function markAllRead(User $user): void
    {
        StaffNotification::query()->where('staff_user_id', $user->id)->whereNull('read_at')->update(['read_at' => now()]);
    }

    public function markRead(User $user, StaffNotification $notification): void
    {
        abort_unless($notification->staff_user_id === $user->id, 404);

        $matchingIds = StaffNotification::query()
            ->where('staff_user_id', $user->id)
            ->when(
                $notification->spa_booking_id !== null,
                fn ($query) => $query->where('spa_booking_id', $notification->spa_booking_id),
                fn ($query) => $query->whereKey($notification->id),
            )
            ->whereNull('read_at')
            ->get(['id', 'event_key'])
            ->filter(fn (StaffNotification $candidate): bool => $this->semanticKey($candidate->event_key) === $this->semanticKey($notification->event_key))
            ->pluck('id');

        StaffNotification::query()->whereKey($matchingIds)->update(['read_at' => now()]);
    }

    private function semanticKey(string $eventKey): string
    {
        return preg_replace(
            '/^(booking:\d+:(?:created|payment-paid|cancelled|balance-collected|session-[^:]+)):\d{17}$/',
            '$1',
            $eventKey,
        ) ?? $eventKey;
    }
}
