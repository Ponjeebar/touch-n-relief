<?php

namespace Tests\Feature;

use App\Models\CustomerNotification;
use App\Models\SpaBooking;
use App\Models\User;
use App\Services\NoShowService;
use App\Support\PaymentMethodCatalog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomaticNoShowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_overdue_appointment_is_recorded_once_after_the_five_minute_review_window(): void
    {
        Carbon::setTestNow('2026-10-04 10:14:59');
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = $this->booking($customer, '2026-10-04', '10:00 AM');

        $this->artisan('appointments:process-no-shows')
            ->expectsOutput('Processed 0 automatic no-show appointment(s).')
            ->assertSuccessful();
        $this->assertSame(SpaBooking::STATUS_CONFIRMED, $booking->fresh()->session_status);

        Carbon::setTestNow('2026-10-04 10:15:00');
        $this->artisan('appointments:process-no-shows')
            ->expectsOutput('Processed 1 automatic no-show appointment(s).')
            ->assertSuccessful();
        $this->artisan('appointments:process-no-shows')
            ->expectsOutput('Processed 0 automatic no-show appointment(s).')
            ->assertSuccessful();

        $this->assertSame(SpaBooking::STATUS_NO_SHOW, $booking->fresh()->session_status);
        $this->assertDatabaseCount('customer_notifications', 1);
        $this->assertDatabaseHas('customer_notifications', [
            'user_id' => $customer->id,
            'spa_booking_id' => $booking->id,
            'type' => CustomerNotification::TYPE_NO_SHOW,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => null,
            'user_role' => 'system',
            'action' => 'appointment.no_show',
            'subject_id' => $booking->id,
        ]);
        $this->assertDatabaseCount('activity_logs', 1);
    }

    public function test_automatic_no_show_uses_the_existing_three_strike_account_rule(): void
    {
        Carbon::setTestNow('2026-10-04 10:15:00');
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $this->booking($customer, '2026-10-01', '10:00 AM')->update(['session_status' => SpaBooking::STATUS_NO_SHOW]);
        $this->booking($customer, '2026-10-02', '10:00 AM')->update(['session_status' => SpaBooking::STATUS_NO_SHOW]);
        $overdue = $this->booking($customer, '2026-10-04', '10:00 AM');

        $this->artisan('appointments:process-no-shows')->assertSuccessful();

        $this->assertSame(SpaBooking::STATUS_NO_SHOW, $overdue->fresh()->session_status);
        $this->assertNotNull($customer->fresh()->banned_at);
        $this->assertDatabaseHas('customer_notifications', [
            'spa_booking_id' => $overdue->id,
            'type' => CustomerNotification::TYPE_NO_SHOW,
        ]);
    }

    public function test_automatic_processing_cannot_duplicate_a_manually_recorded_no_show(): void
    {
        Carbon::setTestNow('2026-10-04 10:10:00');
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = $this->booking($customer, '2026-10-04', '10:00 AM');

        $this->assertNotNull(app(NoShowService::class)->record($booking));
        Carbon::setTestNow('2026-10-04 10:15:00');
        $this->artisan('appointments:process-no-shows')
            ->expectsOutput('Processed 0 automatic no-show appointment(s).')
            ->assertSuccessful();

        $this->assertSame(SpaBooking::STATUS_NO_SHOW, $booking->fresh()->session_status);
        $this->assertDatabaseCount('customer_notifications', 1);
    }

    public function test_started_completed_cancelled_and_not_yet_due_appointments_are_not_changed(): void
    {
        Carbon::setTestNow('2026-10-04 10:15:00');
        $customer = User::factory()->create(['role' => User::ROLE_USER]);

        $notYetDue = $this->booking($customer, '2026-10-04', '10:01 AM');
        $started = $this->booking($customer, '2026-10-04', '09:00 AM');
        $started->update(['session_status' => SpaBooking::STATUS_IN_SESSION, 'session_started_at' => now()->subHour()]);
        $completed = $this->booking($customer, '2026-10-04', '08:00 AM');
        $completed->update(['session_status' => SpaBooking::STATUS_COMPLETED, 'completed_at' => now()->subHour()]);
        $cancelled = $this->booking($customer, '2026-10-04', '07:00 AM');
        $cancelled->update(['session_status' => SpaBooking::STATUS_CANCELLED, 'cancelled_at' => now()->subHour()]);

        $this->artisan('appointments:process-no-shows')
            ->expectsOutput('Processed 0 automatic no-show appointment(s).')
            ->assertSuccessful();

        $this->assertSame(SpaBooking::STATUS_CONFIRMED, $notYetDue->fresh()->session_status);
        $this->assertSame(SpaBooking::STATUS_IN_SESSION, $started->fresh()->session_status);
        $this->assertSame(SpaBooking::STATUS_COMPLETED, $completed->fresh()->session_status);
        $this->assertSame(SpaBooking::STATUS_CANCELLED, $cancelled->fresh()->session_status);
        $this->assertDatabaseCount('customer_notifications', 0);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_expired_unpaid_checkout_is_not_counted_as_a_customer_no_show(): void
    {
        Carbon::setTestNow('2026-10-04 10:15:00');
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = $this->booking($customer, '2026-10-04', '10:00 AM');
        $booking->update([
            'payment_status' => PaymentMethodCatalog::STATUS_PENDING,
            'payment_amount' => 0,
            'created_at' => now()->subHour(),
        ]);

        $this->artisan('appointments:process-no-shows')
            ->expectsOutput('Processed 0 automatic no-show appointment(s).')
            ->assertSuccessful();

        $this->assertSame(SpaBooking::STATUS_CONFIRMED, $booking->fresh()->session_status);
        $this->assertNull($customer->fresh()->banned_at);
        $this->assertDatabaseCount('customer_notifications', 0);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_paid_automatic_no_show_is_displayed_counted_and_filtered_for_both_staff_roles(): void
    {
        Carbon::setTestNow('2026-10-04 10:15:00');
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = $this->booking($customer, '2026-10-04', '10:00 AM');
        $this->artisan('appointments:process-no-shows')->assertSuccessful();

        foreach ([User::ROLE_ADMIN, User::ROLE_RECEPTIONIST] as $role) {
            $staff = User::factory()->create(['role' => $role]);
            $this->actingAs($staff);
            $this->get(route('appointments.index', ['date' => '2026-10-04']))
                ->assertOk()
                ->assertViewHas('stats', fn (array $stats): bool => $stats['no_show'] === 1 && $stats['confirmed'] === 0)
                ->assertViewHas('appointments', fn ($rows): bool => $rows->count() === 1 && $rows->first()['status'] === 'No Show');
            $this->get(route('appointments.index', ['date' => '2026-10-04', 'status_filter' => 'no-show']))
                ->assertOk()
                ->assertViewHas('appointments', fn ($rows): bool => $rows->count() === 1);
            $this->get(route('appointments.index', ['date' => '2026-10-04', 'status_filter' => 'confirmed']))
                ->assertOk()
                ->assertViewHas('appointments', fn ($rows): bool => $rows->count() === 0);
        }

        $this->assertSame(SpaBooking::STATUS_NO_SHOW, $booking->fresh()->session_status);
        $this->assertSame(PaymentMethodCatalog::STATUS_PAID, $booking->fresh()->payment_status);
        $this->assertEquals(500, $booking->fresh()->payment_amount);
        $this->assertDatabaseCount('customer_notifications', 1);
    }

    private function booking(User $customer, string $date, string $time): SpaBooking
    {
        return SpaBooking::query()->create([
            'user_id' => $customer->id,
            'client_name' => $customer->name,
            'booking_source' => SpaBooking::SOURCE_ONLINE,
            'service_name' => 'Swedish Massage',
            'therapist_name' => 'Liza Reyes',
            'booking_date' => $date,
            'time_slot' => $time,
            'duration_minutes' => 60,
            'amount' => 500,
            'payment_method' => PaymentMethodCatalog::METHOD_PAYMONGO,
            'payment_status' => PaymentMethodCatalog::STATUS_PAID,
            'payment_amount' => 500,
            'session_status' => SpaBooking::STATUS_CONFIRMED,
        ]);
    }
}
