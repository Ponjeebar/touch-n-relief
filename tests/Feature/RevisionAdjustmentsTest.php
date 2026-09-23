<?php

namespace Tests\Feature;

use App\Models\SpaBooking;
use App\Models\SpaService;
use App\Models\User;
use App\Services\BookingSlotService;
use App\Support\PaymentMethodCatalog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
