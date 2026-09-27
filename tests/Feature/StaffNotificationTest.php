<?php

namespace Tests\Feature;

use App\Models\SpaBooking;
use App\Models\StaffNotification;
use App\Models\User;
use App\Services\NotificationFeedService;
use App\Support\PaymentMethodCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_event_creates_actual_notifications_for_each_staff_account(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $receptionist = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);
        $customer = User::factory()->create(['role' => User::ROLE_USER]);

        SpaBooking::create([
            'user_id' => $customer->id,
            'client_name' => $customer->name,
            'service_name' => 'Swedish Massage',
            'therapist_name' => 'Liza Reyes',
            'booking_date' => now()->addDay()->toDateString(),
            'time_slot' => '10:00 AM',
            'payment_status' => PaymentMethodCatalog::STATUS_PENDING,
            'payment_type' => PaymentMethodCatalog::TYPE_FULL,
        ]);

        $this->assertDatabaseCount('staff_notifications', 2);
        $this->assertDatabaseHas('staff_notifications', ['staff_user_id' => $admin->id, 'title' => 'Booking awaiting payment']);
        $this->assertDatabaseHas('staff_notifications', ['staff_user_id' => $receptionist->id, 'title' => 'Booking awaiting payment']);
    }

    public function test_online_checkout_notifies_staff_only_after_payment_is_verified(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = SpaBooking::create([
            'user_id' => $customer->id,
            'client_name' => $customer->name,
            'booking_source' => SpaBooking::SOURCE_ONLINE,
            'service_name' => 'Swedish Massage',
            'therapist_name' => 'Liza Reyes',
            'booking_date' => now()->addDay()->toDateString(),
            'time_slot' => '10:00 AM',
            'payment_method' => PaymentMethodCatalog::METHOD_PAYMONGO,
            'payment_status' => PaymentMethodCatalog::STATUS_PENDING,
            'payment_type' => PaymentMethodCatalog::TYPE_FULL,
        ]);

        $this->assertDatabaseCount('staff_notifications', 0);

        $booking->update([
            'payment_method' => PaymentMethodCatalog::CHANNEL_QRPH,
            'payment_status' => PaymentMethodCatalog::STATUS_PAID,
        ]);

        $this->assertDatabaseHas('staff_notifications', [
            'staff_user_id' => $admin->id,
            'spa_booking_id' => $booking->id,
            'title' => 'Payment verified',
        ]);
    }

    public function test_repeated_payment_updates_create_one_notification_per_staff_account(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = SpaBooking::create([
            'user_id' => $customer->id,
            'client_name' => $customer->name,
            'booking_source' => SpaBooking::SOURCE_ONLINE,
            'service_name' => 'Swedish Massage',
            'therapist_name' => 'Liza Reyes',
            'booking_date' => now()->addDay()->toDateString(),
            'time_slot' => '10:00 AM',
            'payment_method' => PaymentMethodCatalog::METHOD_PAYMONGO,
            'payment_status' => PaymentMethodCatalog::STATUS_PENDING,
            'payment_type' => PaymentMethodCatalog::TYPE_FULL,
        ]);

        $booking->update(['payment_status' => PaymentMethodCatalog::STATUS_PAID]);
        $booking->update(['payment_status' => PaymentMethodCatalog::STATUS_PENDING]);
        $booking->update(['payment_status' => PaymentMethodCatalog::STATUS_PAID]);

        $this->assertSame(1, StaffNotification::query()
            ->where('staff_user_id', $admin->id)
            ->where('spa_booking_id', $booking->id)
            ->where('title', 'Payment verified')
            ->count());
    }

    public function test_legacy_duplicate_payment_notifications_are_collapsed_and_read_together(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = SpaBooking::create([
            'user_id' => $customer->id,
            'client_name' => $customer->name,
            'service_name' => 'Swedish Massage',
            'therapist_name' => 'Liza Reyes',
            'booking_date' => now()->addDay()->toDateString(),
            'time_slot' => '10:00 AM',
            'payment_status' => PaymentMethodCatalog::STATUS_PENDING,
            'payment_type' => PaymentMethodCatalog::TYPE_FULL,
        ]);
        StaffNotification::query()->where('staff_user_id', $admin->id)->delete();

        $first = StaffNotification::create([
            ...$this->notificationData($admin),
            'spa_booking_id' => $booking->id,
            'event_key' => 'booking:'.$booking->id.':payment-paid:20260927153000123',
            'title' => 'Payment verified',
        ]);
        StaffNotification::create([
            ...$this->notificationData($admin),
            'spa_booking_id' => $booking->id,
            'event_key' => 'booking:'.$booking->id.':payment-paid:20260927153000456',
            'title' => 'Payment verified',
        ]);

        $feed = app(NotificationFeedService::class);

        $this->assertCount(1, $feed->recentBookingNotifications(6, $admin));
        $this->assertSame(1, $feed->unreadCount($admin));

        $feed->markRead($admin, $first);

        $this->assertSame(0, $feed->unreadCount($admin));
        $this->assertSame(0, StaffNotification::query()
            ->where('staff_user_id', $admin->id)
            ->whereNull('read_at')
            ->count());
    }

    public function test_staff_read_state_is_persistent_and_account_specific(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $receptionist = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);
        $adminNote = StaffNotification::create($this->notificationData($admin));
        StaffNotification::create($this->notificationData($receptionist));

        $this->actingAs($admin)->postJson(route('staff-notifications.read', $adminNote))->assertOk()->assertJsonPath('unread_count', 0);

        $this->assertNotNull($adminNote->fresh()->read_at);
        $this->assertSame(1, app(NotificationFeedService::class)->unreadCount($receptionist));
    }

    public function test_staff_cannot_mark_another_accounts_notification_read(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $receptionist = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);
        $note = StaffNotification::create($this->notificationData($receptionist));

        $this->actingAs($admin)->postJson(route('staff-notifications.read', $note))->assertNotFound();
        $this->assertNull($note->fresh()->read_at);
    }

    private function notificationData(User $user): array
    {
        return [
            'staff_user_id' => $user->id,
            'event_key' => 'test:'.$user->id.':'.uniqid(),
            'type' => 'confirmed',
            'title' => 'Appointment confirmed',
            'message' => 'Actual booking event',
            'url' => '/appointments',
            'occurred_at' => now(),
        ];
    }
}
