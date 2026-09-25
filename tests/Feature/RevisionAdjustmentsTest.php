<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\SpaBooking;
use App\Models\SpaService;
use App\Models\User;
use App\Services\BookingSlotService;
use App\Services\SiteSettingsService;
use App\Support\PaymentMethodCatalog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertHeader('content-type', 'application/json; charset=UTF-8');
        $this->assertStringContainsString('.json', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('"application": "TOUCHnRELIEF"', $response->streamedContent());
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
            ->assertJsonPath('primaryLabel', 'Weekly Sales')
            ->assertJsonCount(7, 'trendLabels');
    }

    public function test_daily_and_yearly_report_trends_are_grouped_without_database_specific_functions(): void
    {
        Carbon::setTestNow('2026-09-25 10:00:00');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
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
