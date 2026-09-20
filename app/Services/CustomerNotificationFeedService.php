<?php

namespace App\Services;

use App\Models\CustomerNotification;
use App\Models\User;

class CustomerNotificationFeedService
{
    /**
     * @return list<array{
     *     id: int,
     *     type: string,
     *     title: string,
     *     message: string,
     *     time: string,
     *     notification_at: string,
     *     notification_key: string,
     *     is_read: bool,
     *     details: array<string, mixed>
     * }>
     */
    public function recentForUser(User $user, int $limit = 10): array
    {
        return CustomerNotification::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (CustomerNotification $notification): array => $this->mapNotification($notification))
            ->values()
            ->all();
    }

    public function unreadCountForUser(User $user): int
    {
        return CustomerNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * @return array{
     *     id: int,
     *     type: string,
     *     title: string,
     *     message: string,
     *     time: string,
     *     notification_at: string,
     *     notification_key: string,
     *     is_read: bool,
     *     details: array<string, mixed>
     * }
     */
    public function mapNotification(CustomerNotification $notification): array
    {
        return [
            'id' => (int) $notification->id,
            'type' => (string) $notification->type,
            'title' => (string) $notification->title,
            'message' => (string) $notification->message,
            'time' => $notification->created_at?->diffForHumans() ?? 'Just now',
            'notification_at' => $notification->created_at?->toIso8601String() ?? now()->toIso8601String(),
            'notification_key' => (string) ($notification->dedup_key ?? ('notification:'.$notification->id)),
            'is_read' => $notification->isRead(),
            'details' => is_array($notification->details) ? $notification->details : [],
        ];
    }
}
