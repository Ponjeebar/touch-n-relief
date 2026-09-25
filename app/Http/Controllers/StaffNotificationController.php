<?php

namespace App\Http\Controllers;

use App\Models\StaffNotification;
use App\Services\NotificationFeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffNotificationController extends Controller
{
    public function markAllRead(Request $request, NotificationFeedService $feed): JsonResponse
    {
        $feed->markAllRead($request->user());
        return response()->json(['unread_count' => 0]);
    }

    public function markRead(Request $request, StaffNotification $staffNotification, NotificationFeedService $feed): JsonResponse
    {
        $feed->markRead($request->user(), $staffNotification);
        return response()->json(['unread_count' => $feed->unreadCount($request->user())]);
    }
}
