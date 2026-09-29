<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\SiteSetting;
use App\Models\SpaBooking;
use App\Models\SpaService;
use App\Models\Therapist;
use App\Models\User;
use App\Services\BookingSlotService;
use App\Services\SiteSettingsService;
use App\Support\PaymentMethodCatalog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RevisionAdjustmentsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_backup_download_uses_portable_json_without_zip_extension(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $response = $this->actingAs($admin)->get(route('reporting.backup'));

        $response->assertOk()
            ->assertHeader('content-type', 'application/json; charset=UTF-8')
            ->assertHeader('cache-control', 'no-store, private');
        $this->assertStringContainsString('.json', (string) $response->headers->get('content-disposition'));
        $content = $response->streamedContent();
        $this->assertStringContainsString('"application": "TOUCHnRELIEF"', $content);
        $this->assertStringContainsString('"format_version": 2', $content);
        $this->assertStringContainsString('"service_time_slots"', $content);
        $this->assertStringContainsString('"tables_sha256"', $content);
    }

    public function test_appointment_export_downloads_a_dated_csv(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);
        $client = User::factory()->create(['role' => User::ROLE_USER]);
        SpaBooking::create([
            'user_id' => $client->id,
            'client_name' => $client->name,
            'service_name' => 'Swedish Massage',
            'therapist_name' => 'Liza Reyes',
            'booking_date' => '2026-09-24',
            'time_slot' => '10:00 AM',
            'amount' => 100,
            'payment_status' => PaymentMethodCatalog::STATUS_PAID,
            'payment_amount' => 100,
        ]);

        $response = $this->actingAs($staff)->get(route('appointments.export', ['date' => '2026-09-24']));

        $response->assertOk();
        $this->assertStringContainsString('appointments-2026-09-24.csv', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('Swedish Massage', $response->streamedContent());
    }

    public function test_appointment_export_honors_date_range_and_current_status_filter(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);
        $client = User::factory()->create(['role' => User::ROLE_USER]);
        foreach ([
            ['date' => '2026-09-20', 'status' => SpaBooking::STATUS_COMPLETED, 'service' => 'Thai Massage'],
            ['date' => '2026-09-21', 'status' => SpaBooking::STATUS_CANCELLED, 'service' => 'Hot Stone'],
            ['date' => '2026-10-01', 'status' => SpaBooking::STATUS_COMPLETED, 'service' => 'Foot Reflexology'],
        ] as $row) {
            SpaBooking::create([
                'user_id' => $client->id,
                'client_name' => $client->name,
                'service_name' => $row['service'],
                'therapist_name' => 'Liza Reyes',
                'booking_date' => $row['date'],
                'time_slot' => '10:00 AM',
                'session_status' => $row['status'],
                'completed_at' => $row['status'] === SpaBooking::STATUS_COMPLETED ? $row['date'].' 11:00:00' : null,
                'cancelled_at' => $row['status'] === SpaBooking::STATUS_CANCELLED ? $row['date'].' 09:00:00' : null,
            ]);
        }

        $response = $this->actingAs($staff)->get(route('appointments.export', [
            'scope' => 'range',
            'date_from' => '2026-09-20',
            'date_to' => '2026-09-30',
            'status_scope' => 'current',
            'status_filter' => 'completed',
        ]));

        $csv = $response->streamedContent();
        $response->assertOk();
        $this->assertStringContainsString('Thai Massage', $csv);
        $this->assertStringNotContainsString('Hot Stone', $csv);
        $this->assertStringNotContainsString('Foot Reflexology', $csv);
    }

    public function test_therapist_service_hours_use_selected_year_and_configured_target(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->get(route('therapist-tracking.index'))->assertOk();
        $therapist = Therapist::query()->firstOrFail();
        DB::table('transactions')->insert([
            'transaction_id' => 'TRX-YEARLY-HOURS',
            'client_name' => 'Hours Client',
            'therapist_id' => $therapist->id,
            'service_name' => 'Swedish Massage',
            'date' => '2025-06-10',
            'time' => '10:00:00',
            'duration' => 120,
            'amount' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get(route('therapist-tracking.index', ['year' => 2025]))
            ->assertOk()
            ->assertSee('2025 service hours')
            ->assertSee('2.0 / 240 hrs (1%)');
    }

    public function test_archived_clients_can_be_filtered_and_restored_without_deleting_history(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $client = User::factory()->create(['role' => User::ROLE_USER, 'email' => 'restore@example.com']);
        $customer = Customer::query()->create([
            'customer_id' => 'CUS-RESTORE',
            'full_name' => 'Restore Client',
            'email' => $client->email,
            'password' => 'Password1',
        ]);
        $customer->archive();
        $client->archive();

        $this->actingAs($admin)->get(route('client-records.index', ['sort' => 'archived']))
            ->assertOk()
            ->assertSee('Restore Client')
            ->assertSee('Archived');

        $this->patch(route('dashboard.customers.restore', $customer), ['return_to' => 'client-records.index'])
            ->assertRedirect(route('client-records.index'));

        $this->assertNull($customer->fresh()->archived_at);
        $this->assertNull($client->fresh()->archived_at);
    }

    public function test_inactive_client_command_soft_archives_records_after_retention_period(): void
    {
        Carbon::setTestNow('2026-09-29 02:15:00');
        $client = User::factory()->create([
            'role' => User::ROLE_USER,
            'email' => 'inactive@example.com',
            'created_at' => '2023-01-01 09:00:00',
        ]);
        $customer = Customer::query()->create([
            'customer_id' => 'CUS-INACTIVE',
            'full_name' => 'Inactive Client',
            'email' => $client->email,
            'password' => 'Password1',
            'created_at' => '2023-01-01 09:00:00',
        ]);
        SpaBooking::query()->create([
            'user_id' => $client->id,
            'client_name' => $customer->full_name,
            'service_name' => 'Swedish Massage',
            'therapist_name' => 'Liza Reyes',
            'booking_date' => '2023-02-01',
            'time_slot' => '10:00 AM',
            'session_status' => SpaBooking::STATUS_COMPLETED,
            'completed_at' => '2023-02-01 11:00:00',
        ]);

        Artisan::call('clients:archive-inactive');

        $this->assertNotNull($customer->fresh()->archived_at);
        $this->assertNotNull($client->fresh()->archived_at);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'customer.archived',
            'subject_id' => $customer->id,
            'user_name' => 'System',
        ]);
    }

    public function test_weekly_reporting_returns_the_selected_monday_to_sunday_range(): void
    {
        Carbon::setTestNow('2026-09-25 10:00:00');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin)->getJson(route('reporting.data', [
            'period' => 'weekly',
            'period_value' => '2026-09-14',
        ]));

        $response->assertOk()
            ->assertJsonPath('period', 'weekly')
            ->assertJsonPath('periodValue', '2026-09-14')
            ->assertJsonPath('periodValueLabel', 'Sep 14 - Sep 20, 2026')
            ->assertJsonPath('primaryLabel', 'Weekly Net Sales')
            ->assertJsonCount(7, 'trendLabels');
    }

    public function test_daily_and_yearly_report_trends_are_grouped_without_database_specific_functions(): void
    {
        Carbon::setTestNow('2026-09-25 10:00:00');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $client = User::factory()->create(['role' => User::ROLE_USER]);
        DB::table('transactions')->insert([
            [
                'transaction_id' => 'TRX-HEROKU-1', 'client_name' => 'Client One', 'therapist_id' => 1,
                'service_name' => 'Swedish Massage', 'date' => '2026-09-25', 'time' => '08:15:00',
                'duration' => 60, 'amount' => 80, 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'transaction_id' => 'TRX-HEROKU-2', 'client_name' => 'Client Two', 'therapist_id' => 1,
                'service_name' => 'Swedish Massage', 'date' => '2026-09-25', 'time' => '08:45:00',
                'duration' => 60, 'amount' => 120, 'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
        $bookingOne = SpaBooking::create([
            'user_id' => $client->id,
            'client_name' => 'Client One', 'service_name' => 'Swedish Massage', 'therapist_name' => 'Liza Reyes',
            'booking_date' => '2026-10-10', 'time_slot' => '08:00 AM', 'amount' => 80,
        ]);
        $bookingTwo = SpaBooking::create([
            'user_id' => $client->id,
            'client_name' => 'Client Two', 'service_name' => 'Swedish Massage', 'therapist_name' => 'Liza Reyes',
            'booking_date' => '2026-10-10', 'time_slot' => '09:00 AM', 'amount' => 120,
        ]);
        DB::table('payment_ledger_entries')->insert([
            [
                'spa_booking_id' => $bookingOne->id, 'entry_type' => 'initial_payment', 'amount' => 80,
                'occurred_at' => '2026-09-25 08:15:00', 'is_estimated' => false, 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'spa_booking_id' => $bookingTwo->id, 'entry_type' => 'initial_payment', 'amount' => 120,
                'occurred_at' => '2026-09-25 08:45:00', 'is_estimated' => false, 'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        $this->actingAs($admin)->getJson(route('reporting.data', [
            'period' => 'daily', 'period_value' => 'friday',
        ]))->assertOk()->assertJsonPath('trendData.8', 200);

        $this->actingAs($admin)->getJson(route('reporting.data', [
            'period' => 'yearly', 'period_value' => '2026',
        ]))->assertOk()->assertJsonPath('trendData.8', 200);
    }

    public function test_reporting_pdf_downloads_the_selected_period(): void
    {
        Carbon::setTestNow('2026-09-25 10:00:00');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin)->get(route('reporting.pdf', [
            'period' => 'weekly',
            'period_value' => '2026-09-14',
        ]));

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString(
            'touchnrelief-report-weekly-2026-09-14.pdf',
            (string) $response->headers->get('content-disposition'),
        );
        $this->assertStringStartsWith('%PDF-', (string) $response->getContent());
    }

    public function test_landing_settings_save_and_render_social_links(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $settings = app(SiteSettingsService::class);
        $footer = $settings->footer();

        $response = $this->actingAs($admin)->put(route('landing-settings.update'), [
            ...$footer,
            'facebook_url' => 'https://www.facebook.com/buenostouche',
            'instagram_url' => 'https://www.instagram.com/buenostouche',
            'cancellation_cutoff_hours' => 24,
        ]);

        $response->assertRedirect(route('landing-settings.edit'));
        $this->assertSame(
            'https://www.facebook.com/buenostouche',
            SiteSetting::valueFor(SiteSettingsService::KEY_FACEBOOK_URL),
        );
        Auth::logout();

        $this->get(route('landing'))
            ->assertOk()
            ->assertSee('https://www.facebook.com/buenostouche', false)
            ->assertSee('https://www.instagram.com/buenostouche', false);
    }

    public function test_landing_settings_reject_non_web_social_links(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $footer = app(SiteSettingsService::class)->footer();

        $this->actingAs($admin)->put(route('landing-settings.update'), [
            ...$footer,
            'facebook_url' => 'javascript:alert(1)',
            'instagram_url' => '',
            'cancellation_cutoff_hours' => 24,
        ])->assertSessionHasErrors('facebook_url');
    }

    public function test_staff_downpayment_is_rejected_inside_one_hour_cutoff(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $client = User::factory()->create(['role' => User::ROLE_USER]);
        $this->actingAs($staff)->get(route('appointments.index'))->assertOk();
        $service = SpaService::query()->where('is_active', true)->firstOrFail();
        $slot = app(BookingSlotService::class)->slotMapByService()[$service->name][0];
        $date = '2026-09-24';
        $appointment = Carbon::createFromFormat('Y-m-d g:i A', $date.' '.$slot);
        Carbon::setTestNow($appointment->copy()->subMinutes(30));

        $this->post(route('appointments.store'), [
            'client_type' => 'registered',
            'client_user_id' => $client->id,
            'client_name' => $client->name,
            'client_email' => $client->email,
            'client_phone' => $client->contact_number,
            'service' => $service->name,
            'therapist' => '',
            'booking_date' => $date,
            'time_slot' => $slot,
            'payment_method' => PaymentMethodCatalog::METHOD_CASH_COUNTER,
            'payment_type' => PaymentMethodCatalog::TYPE_DOWNPAYMENT,
        ])->assertSessionHasErrors(['payment_type'], null, 'appointment');

        $this->assertDatabaseCount('spa_bookings', 0);
    }
}
