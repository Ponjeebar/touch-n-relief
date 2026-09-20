<?php

namespace App\Http\Controllers;

use App\Models\CustomerNotification;
use App\Models\User;
use App\Services\CustomerNotificationFeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerNotificationController extends Controller
{
    public function poll(CustomerNotificationFeedService $feed): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        if (! $user->isUser()) {
            return response()->json([
                'notifications' => [],
                'unread_count' => 0,
            ]);
        }

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'notifications' => $feed->recentForUser($user, 10),
            'unread_count' => $feed->unreadCountForUser($user),
        ]);
    }

    public function markRead(Request $request, CustomerNotification $customerNotification): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        if ((int) $customerNotification->user_id !== (int) $user->id) {
            abort(403);
        }

        if ($customerNotification->read_at === null) {
            $customerNotification->read_at = now();
            $customerNotification->save();
        }

        return response()->json([
            'ok' => true,
            'unread_count' => app(CustomerNotificationFeedService::class)->unreadCountForUser($user),
        ]);
    }

    public function markAllRead(CustomerNotificationFeedService $feed): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        CustomerNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'ok' => true,
            'unread_count' => $feed->unreadCountForUser($user),
        ]);
    }
}
