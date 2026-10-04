<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\SiteSetting;
use App\Models\SpaBooking;
use App\Models\User;
use App\Services\BookingCancellationService;
use App\Services\CustomerBookingPolicy;
use App\Services\NoShowService;
use App\Services\SiteSettingsService;
use App\Support\PaymentMethodCatalog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_only_administrators_can_access_system_settings(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $receptionist = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);
        $customer = User::factory()->create(['role' => User::ROLE_USER]);

        $this->actingAs($admin)
            ->get(route('system-settings.edit'))
            ->assertOk()
            ->assertSee('System Settings')
            ->assertSee('Overview')
            ->assertSee('Daily work')
            ->assertSee('Administration')
            ->assertSee('Content and settings');

        $this->actingAs($receptionist)
            ->get(route('system-settings.edit'))
            ->assertRedirect(route('receptionist.dashboard'));
        $this->actingAs($customer)->get(route('system-settings.edit'))->assertForbidden();
    }

    public function test_admin_can_update_connected_rules_and_change_is_audited(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->put(route('system-settings.update'), $this->rules([
                'cancellation_cutoff_hours' => 1,
                'payment_hold_minutes' => 10,
                'customer_minimum_lead_minutes' => 45,
                'late_grace_minutes' => 8,
                'no_show_review_minutes' => 4,
                'no_show_restriction_threshold' => 2,
                'backup_retention_days' => 21,
            ]))
            ->assertRedirect(route('system-settings.edit'))
            ->assertSessionHas('status', 'System settings updated successfully.');

        $settings = app(SiteSettingsService::class);
        $this->assertSame(1, $settings->cancellationCutoffHours());
        $this->assertSame(10, $settings->paymentHoldMinutes());
        $this->assertSame(45, app(CustomerBookingPolicy::class)->minimumLeadMinutes());
        $this->assertSame(8, $settings->lateGraceMinutes());
        $this->assertSame(4, $settings->noShowReviewMinutes());
        $this->assertSame(2, $settings->noShowRestrictionThreshold());
        $this->assertSame(21, $settings->backupRetentionDays());
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'system.settings_updated',
        ]);
        $this->assertSame(10, (int) SiteSetting::valueFor(SiteSettingsService::KEY_PAYMENT_HOLD_MINUTES));
    }

    public function test_lead_time_must_remain_longer_than_payment_hold(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->from(route('system-settings.edit'))
            ->put(route('system-settings.update'), $this->rules([
                'payment_hold_minutes' => 20,
                'customer_minimum_lead_minutes' => 20,
            ]))
            ->assertRedirect(route('system-settings.edit'))
            ->assertSessionHasErrors('customer_minimum_lead_minutes');

        $this->assertSame(15, app(SiteSettingsService::class)->paymentHoldMinutes());
        $this->assertDatabaseMissing('activity_logs', ['action' => 'system.settings_updated']);
    }

    public function test_saved_booking_and_attendance_rules_drive_the_workflows(): void
    {
        Carbon::setTestNow('2026-10-05 10:00:00');
        $settings = app(SiteSettingsService::class);
        $settings->updateSystemRules($this->rules([
            'cancellation_cutoff_hours' => 1,
            'payment_hold_minutes' => 10,
            'customer_minimum_lead_minutes' => 40,
            'late_grace_minutes' => 2,
            'no_show_review_minutes' => 3,
            'no_show_restriction_threshold' => 1,
        ]));

        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $pending = $this->booking($customer, '2026-10-06', '10:00 AM', [
            'payment_method' => PaymentMethodCatalog::METHOD_PAYMONGO,
            'payment_status' => PaymentMethodCatalog::STATUS_PENDING,
            'payment_amount' => 0,
        ]);
        $pending->forceFill(['created_at' => now()->subMinutes(11)])->saveQuietly();
        $future = $this->booking($customer, '2026-10-05', '11:30 AM');

        $this->assertTrue($pending->isPaymentHoldExpired());
        $this->assertTrue(app(BookingCancellationService::class)->canCancel($future));

        $late = $this->booking($customer, '2026-10-05', '10:00 AM');
        Carbon::setTestNow('2026-10-05 10:04:00');
        $this->assertSame(0, app(NoShowService::class)->processOverdue());
        Carbon::setTestNow('2026-10-05 10:05:00');
        $this->assertSame(1, app(NoShowService::class)->processOverdue());
        $this->assertSame(SpaBooking::STATUS_NO_SHOW, $late->fresh()->session_status);
        $this->assertNotNull($customer->fresh()->banned_at);
    }

    public function test_threshold_changes_immediately_reconcile_existing_customer_restrictions(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $this->booking($customer, '2026-10-01', '10:00 AM', ['session_status' => SpaBooking::STATUS_NO_SHOW]);
        $this->booking($customer, '2026-10-02', '10:00 AM', ['session_status' => SpaBooking::STATUS_NO_SHOW]);

        $this->actingAs($admin)
            ->put(route('system-settings.update'), $this->rules(['no_show_restriction_threshold' => 2]))
            ->assertSessionHasNoErrors();
        $this->assertNotNull($customer->fresh()->banned_at);

        $this->put(route('system-settings.update'), $this->rules(['no_show_restriction_threshold' => 3]))
            ->assertSessionHasNoErrors();
        $this->assertNull($customer->fresh()->banned_at);

        $log = ActivityLog::query()->where('action', 'system.settings_updated')->latest('id')->firstOrFail();
        $this->assertContains($customer->id, $log->properties['no_show_restrictions']['restored']);
    }

    public function test_unchanged_threshold_does_not_rewrite_existing_restriction_timestamp(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $customer = User::factory()->create(['role' => User::ROLE_USER, 'banned_at' => now()->subDay()]);
        $original = $customer->banned_at->toDateTimeString();

        $this->actingAs($admin)
            ->put(route('system-settings.update'), $this->rules())
            ->assertSessionHasNoErrors();

        $this->assertSame($original, $customer->fresh()->banned_at->toDateTimeString());
    }

    /** @param array<string, int> $overrides */
    private function rules(array $overrides = []): array
    {
        return array_merge([
            'cancellation_cutoff_hours' => 24,
            'payment_hold_minutes' => 15,
            'customer_minimum_lead_minutes' => 30,
            'late_grace_minutes' => 10,
            'no_show_review_minutes' => 5,
            'no_show_restriction_threshold' => 3,
            'expired_hold_limit' => 3,
            'expired_hold_lookback_hours' => 24,
            'expired_hold_cooldown_minutes' => 60,
            'backup_retention_days' => 14,
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides */
    private function booking(User $customer, string $date, string $time, array $overrides = []): SpaBooking
    {
        return SpaBooking::query()->create(array_merge([
            'user_id' => $customer->id,
            'client_name' => $customer->name,
            'booking_source' => SpaBooking::SOURCE_ONLINE,
            'service_name' => 'Swedish Massage',
            'therapist_name' => 'Liza Reyes',
            'booking_date' => $date,
            'time_slot' => $time,
            'duration_minutes' => 60,
            'amount' => 100,
            'payment_method' => PaymentMethodCatalog::CHANNEL_QRPH,
            'payment_type' => PaymentMethodCatalog::TYPE_FULL,
            'payment_amount' => 100,
            'payment_status' => PaymentMethodCatalog::STATUS_PAID,
            'payment_transaction_id' => 'pay-settings-'.uniqid(),
            'session_status' => SpaBooking::STATUS_CONFIRMED,
        ], $overrides));
    }
}
