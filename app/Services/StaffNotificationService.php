<?php

namespace App\Services;

use App\Models\SpaBooking;
use App\Models\StaffNotification;
use App\Models\User;
use App\Support\PaymentMethodCatalog;
use Illuminate\Support\Facades\Schema;

class StaffNotificationService
{
    public function bookingCreated(SpaBooking $booking): void
    {
        if ($booking->payment_status === PaymentMethodCatalog::STATUS_PAID) {
            $type = 'confirmed';
            $title = $booking->payment_type === PaymentMethodCatalog::TYPE_DOWNPAYMENT ? 'Down payment received' : 'Appointment confirmed';
        } else {
            $type = 'pending';
            $title = 'Booking awaiting payment';
        }

        $this->send($booking, 'created', $type, $title, $this->bookingMessage($booking));
    }

    public function bookingUpdated(SpaBooking $booking): void
    {
        if ($booking->wasChanged('cancelled_at') && $booking->cancelled_at !== null) {
            $this->send($booking, 'cancelled', 'cancelled', 'Appointment cancelled', $this->bookingMessage($booking));
        } elseif ($booking->wasChanged('rescheduled_at') && $booking->rescheduled_at !== null) {
            $this->send($booking, 'rescheduled', 'rescheduled', 'Appointment rescheduled', $this->bookingMessage($booking));
        } elseif ($booking->wasChanged('payment_status') && $booking->payment_status === PaymentMethodCatalog::STATUS_PAID) {
            $this->send($booking, 'payment-paid', 'confirmed', 'Payment verified', $this->bookingMessage($booking));
        } elseif ($booking->wasChanged('balance_paid_at') && $booking->balance_paid_at !== null) {
            $this->send($booking, 'balance-collected', 'confirmed', 'Remaining balance collected', $this->bookingMessage($booking));
        } elseif ($booking->wasChanged('session_status')) {
            $status = (string) $booking->session_status;
            $titles = [
                SpaBooking::STATUS_IN_SESSION => 'Session started',
                SpaBooking::STATUS_COMPLETED => 'Session completed',
                SpaBooking::STATUS_NO_SHOW => 'Customer marked no-show',
            ];
            if (isset($titles[$status])) {
                $this->send($booking, 'session-'.$status, 'system', $titles[$status], $this->bookingMessage($booking));
            }
        }
    }

    private function send(SpaBooking $booking, string $event, string $type, string $title, string $message): void
    {
        if (! Schema::hasTable('staff_notifications')) {
            return;
        }

        $occurredAt = now();
        $eventKey = 'booking:'.$booking->id.':'.$event.':'.$occurredAt->format('YmdHisv');
        $url = route('appointments.index', ['date' => $booking->booking_date?->format('Y-m-d'), 'notif_key' => $booking->id, 'from_notification' => 1]);

        User::query()->whereIn('role', [User::ROLE_ADMIN, User::ROLE_RECEPTIONIST])->pluck('id')->each(
            fn (int $staffId) => StaffNotification::query()->firstOrCreate(
                ['staff_user_id' => $staffId, 'event_key' => $eventKey],
                ['spa_booking_id' => $booking->id, 'type' => $type, 'title' => $title, 'message' => $message, 'url' => $url, 'occurred_at' => $occurredAt],
            )
        );
    }

    private function bookingMessage(SpaBooking $booking): string
    {
        $client = trim((string) $booking->client_name) ?: ($booking->user?->name ?: 'Customer');
        $date = $booking->booking_date?->format('M d, Y') ?? '';

        return trim($client.' | '.$booking->service_name.' | '.$date.' '.$booking->time_slot);
    }
}
