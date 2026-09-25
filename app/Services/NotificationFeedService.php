<?php

namespace App\Services;

use App\Models\StaffNotification;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class NotificationFeedService
{
    public function recentBookingNotifications(int $limit = 6, ?User $user = null): array
    {
        $user ??= auth()->user();
        if (! $user || ! Schema::hasTable('staff_notifications')) return [];

        return StaffNotification::query()->where('staff_user_id', $user->id)->latest('occurred_at')->limit($limit)->get()
            ->map(fn (StaffNotification $note): array => [
                'id' => $note->id, 'type' => $note->type, 'title' => $note->title, 'message' => $note->message,
                'time' => $note->occurred_at?->diffForHumans() ?? 'Just now',
                'notification_at' => $note->occurred_at?->toIso8601String(), 'notification_key' => $note->event_key,
                'url' => $note->url ?: route('appointments.index'), 'is_read' => $note->read_at !== null,
            ])->all();
    }

    public function unreadCount(?User $user = null): int
    {
        $user ??= auth()->user();
        return $user && Schema::hasTable('staff_notifications')
            ? StaffNotification::query()->where('staff_user_id', $user->id)->whereNull('read_at')->count() : 0;
    }

    public function markAllRead(User $user): void
    {
        StaffNotification::query()->where('staff_user_id', $user->id)->whereNull('read_at')->update(['read_at' => now()]);
    }

    public function markRead(User $user, StaffNotification $notification): void
    {
        abort_unless($notification->staff_user_id === $user->id, 404);
        if ($notification->read_at === null) $notification->forceFill(['read_at' => now()])->save();
    }
}
