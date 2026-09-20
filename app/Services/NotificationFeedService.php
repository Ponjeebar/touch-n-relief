<?php

namespace App\Services;

use App\Models\SpaBooking;
use App\Support\PaymentMethodCatalog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class NotificationFeedService
{
    /**
     * @return list<array{type: string, title: string, message: string, time: string, notification_at: string, notification_key: string, url: string}>
     */
    public function recentBookingNotifications(int $limit = 6): array
    {
        $bookings = SpaBooking::query()
            ->with('user:id,name,email')
            ->orderByDesc(DB::raw('COALESCE(cancelled_at, rescheduled_at, created_at)'))
            ->limit($limit)
            ->get();

        return $bookings
            ->map(fn (SpaBooking $booking): array => $this->mapBookingNotification($booking))
            ->values()
            ->all();
    }

    /**
     * @return array{type: string, title: string, message: string, time: string, notification_at: string, notification_key: string, url: string}
     */
    private function mapBookingNotification(SpaBooking $booking): array
    {
        $customerName = trim((string) ($booking->client_name ?? ''))
            ?: trim((string) ($booking->user?->name ?? ''))
            ?: (string) ($booking->user?->email ?? 'A customer');
        $dateText = $booking->booking_date?->format('M d, Y') ?? '';
        $therapistText = trim((string) ($booking->therapist_name ?? ''));
        $when = trim($dateText.' '.(string) $booking->time_slot);
        $service = (string) $booking->service_name;

        $type = 'system';
        $title = 'Booking update';
        $message = $customerName.' updated a booking.';
        $notificationAt = $booking->created_at;
        $notificationKey = (string) $booking->id.':created';

        if ($booking->cancelled_at !== null) {
            $type = 'cancelled';
            $title = 'Appointment cancelled';
            $message = sprintf('%s cancelled %s (%s).', $customerName, $service, $when);
            $notificationAt = $booking->cancelled_at;
            $notificationKey = $booking->id.':cancelled:'.$booking->cancelled_at->timestamp;
        } elseif ($booking->rescheduled_at !== null) {
            $type = 'rescheduled';
            $title = 'Appointment rescheduled';
            $fromWhen = $this->formatPreviousSchedule($booking);
            $message = $fromWhen !== ''
                ? sprintf('%s rescheduled %s from %s to %s.', $customerName, $service, $fromWhen, $when)
                : sprintf('%s rescheduled %s to %s.', $customerName, $service, $when);
            $notificationAt = $booking->rescheduled_at;
            $notificationKey = $booking->id.':rescheduled:'.$booking->rescheduled_at->timestamp;
        } elseif ($booking->payment_type === PaymentMethodCatalog::TYPE_DOWNPAYMENT) {
            $type = 'pending';
            $title = 'Down payment received';
            $message = sprintf(
                '%s booked %s for %s with down payment only%s.',
                $customerName,
                $service,
                $when,
                $therapistText !== '' ? ' with '.$therapistText : ''
            );
            $notificationAt = $booking->created_at;
            $notificationKey = $booking->id.':pending:'.$booking->created_at?->timestamp;
        } else {
            $type = 'confirmed';
            $title = 'Appointment confirmed';
            $message = sprintf('%s confirmed %s for %s.', $customerName, $service, $when);
            $notificationAt = $booking->created_at;
            $notificationKey = $booking->id.':confirmed:'.$booking->created_at?->timestamp;
        }

        $url = route('appointments.index', array_filter([
            'date' => $booking->booking_date?->format('Y-m-d'),
            'notif_key' => (string) $booking->id,
            'from_notification' => '1',
        ]));

        return [
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'time' => $notificationAt?->diffForHumans() ?? 'Just now',
            'notification_at' => $notificationAt?->toIso8601String() ?? now()->toIso8601String(),
            'notification_key' => $notificationKey,
            'url' => $url,
        ];
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
