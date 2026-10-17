<?php

namespace Tests\Feature;

use App\Models\BookingRefund;
use App\Models\PaymentLedgerEntry;
use App\Models\SpaBooking;
use App\Models\SpaService;
use App\Models\Therapist;
use App\Models\TimeSlot;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BookingSlotService;
use App\Services\PaymentLedgerService;
use Carbon\Carbon;
use Database\Seeders\SiteSettingsSeeder;
use Database\Seeders\SpaServiceSeeder;
use Database\Seeders\SpaTimeSlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

class SystemAuditFollowUpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-19 13:00:00');
        $this->seed([SiteSettingsSeeder::class, SpaServiceSeeder::class, SpaTimeSlotSeeder::class]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_therapist_rename_cannot_orphan_a_paid_appointment(): void
    {
        $therapist = $this->therapist();
        $booking = $this->booking(['therapist_name' => $therapist->name]);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->putJson(route('therapists.update', $therapist->therapist_code), ['name' => 'Renamed Therapist', 'is_active' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->assertSame($therapist->name, $therapist->fresh()->name);
        $this->assertSame($therapist->name, $booking->fresh()->therapist_name);
        $this->assertTrue(app(BookingSlotService::class)->therapistSlotTaken($therapist->name, now()->toDateString(), '1:00 PM', 60));
    }

    public function test_therapist_with_appointments_or_history_must_be_retained(): void
    {
        $therapist = $this->therapist();
        $booking = $this->booking(['therapist_name' => $therapist->name]);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->deleteJson(route('therapists.destroy', $therapist->therapist_code))
            ->assertUnprocessable();
        $booking->delete();
        Transaction::create(['transaction_id' => 'AUDIT-HISTORY', 'therapist_id' => $therapist->id, 'client_name' => 'Audit Client', 'service_name' => 'Swedish Massage', 'date' => now()->toDateString(), 'time' => '10:00:00', 'duration' => 60, 'amount' => 100]);
        $this->deleteJson(route('therapists.destroy', $therapist->therapist_code))->assertUnprocessable();
        $this->assertNotNull($therapist->fresh());
        $this->assertDatabaseHas('transactions', ['transaction_id' => 'AUDIT-HISTORY', 'therapist_id' => $therapist->id]);
    }

    public function test_deactivation_requires_resolving_reservations_but_other_profile_edits_remain_allowed(): void
    {
        $therapist = $this->therapist();
        $booking = $this->booking(['therapist_name' => $therapist->name]);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->putJson(route('therapists.update', $therapist->therapist_code), ['name' => $therapist->name, 'is_active' => false])
            ->assertUnprocessable()->assertJsonValidationErrors('is_active');
        $this->assertTrue($therapist->fresh()->is_active);
        $this->putJson(route('therapists.update', $therapist->therapist_code), ['name' => $therapist->name, 'is_active' => true, 'bio' => 'Updated biography'])
            ->assertOk();
        $this->assertSame('Updated biography', $therapist->fresh()->bio);
        $booking->forceFill(['session_status' => 'cancelled', 'cancelled_at' => now()])->save();
        $this->putJson(route('therapists.update', $therapist->therapist_code), ['name' => $therapist->name, 'is_active' => false])
            ->assertOk();
        $this->assertFalse($therapist->fresh()->is_active);
        $this->assertNotNull($booking->fresh());
    }

    public function test_service_rename_preserves_enabled_and_disabled_date_overrides(): void
    {
        $service = SpaService::where('name', 'Swedish Massage')->firstOrFail();
        $slots = app(BookingSlotService::class);
        $labels = $slots->offeredSlotLabelsForServiceOnDate($service->name, now()->toDateString());
        $slot = TimeSlot::where('label', $labels[0])->firstOrFail();
        $enabledSlot = TimeSlot::where('label', $labels[1])->firstOrFail();
        DB::table('service_slot_date_overrides')->insert(['service_name' => $service->name, 'slot_date' => now()->toDateString(), 'time_slot_id' => $slot->id, 'is_enabled' => false, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('service_slot_date_overrides')->insert(['service_name' => $service->name, 'slot_date' => now()->toDateString(), 'time_slot_id' => $enabledSlot->id, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->put(route('services.update', $service), ['name' => 'Swedish Massage Revised', 'price_amount' => $service->price_amount, 'duration_minutes' => $service->duration_minutes, 'best_for' => $service->best_for ?: 'Relaxation', 'description' => $service->description ?: 'Massage service'])
            ->assertSessionHasNoErrors();
        $this->assertNotContains($slot->label, $slots->offeredSlotLabelsForServiceOnDate($service->fresh()->name, now()->toDateString()));
        $this->assertDatabaseHas('service_slot_date_overrides', ['service_name' => $service->fresh()->name, 'time_slot_id' => $slot->id, 'is_enabled' => false]);
        $this->assertContains($enabledSlot->label, $slots->offeredSlotLabelsForServiceOnDate($service->fresh()->name, now()->toDateString()));
    }

    public function test_scheduled_completion_updates_history_and_report_hours_once_without_polling(): void
    {
        $this->therapist();
        $booking = $this->booking(['session_status' => 'in_session', 'session_started_at' => now()->subHour(), 'time_slot' => '12:00 PM']);
        app(PaymentLedgerService::class)->recordInitialPayment($booking);
        $this->assertSame(0, Artisan::call('appointments:complete-expired-sessions'));
        $this->assertSame(0, Artisan::call('appointments:complete-expired-sessions'));
        $this->assertSame('completed', $booking->fresh()->session_status);
        $this->assertSame(1, Transaction::where('spa_booking_id', $booking->id)->count());
        $this->assertSame(1, PaymentLedgerEntry::where('spa_booking_id', $booking->id)->count());
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->getJson(route('reporting.data', ['period' => 'monthly']))->assertOk()->assertJsonPath('hoursValue', 1)->assertJsonPath('primaryAmount', 100);
        $this->assertTrue(collect(Schedule::events())->contains(fn ($event) => str_contains((string) ($event->command ?? ''), 'appointments:complete-expired-sessions')));
    }

    public function test_year_choices_include_collections_without_a_completed_visit(): void
    {
        $booking = $this->booking(['session_status' => 'no_show', 'booking_date' => '2024-10-19']);
        app(PaymentLedgerService::class)->recordInitialPayment($booking, Carbon::parse('2024-10-19 13:00:00'));
        $response = $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->getJson(route('reporting.data', ['period' => 'yearly', 'period_value' => '2024']))->assertOk()->assertJsonPath('primaryAmount', 100);
        $this->assertContains(2024, $response->json('availableYears'));
    }

    public function test_cross_period_refund_remains_negative_in_reporting_and_pdf_details(): void
    {
        $past = $this->booking(['service_name' => 'Swedish Massage']);
        $today = $this->booking(['service_name' => 'Foot Reflexology', 'amount' => 50, 'payment_amount' => 50]);
        $ledger = app(PaymentLedgerService::class);
        $ledger->recordInitialPayment($past, now()->subMonth());
        $ledger->recordInitialPayment($today);
        $refund = BookingRefund::create(['spa_booking_id' => $past->id, 'payment_component' => 'initial_payment', 'processing_channel' => 'manual', 'payment_method' => 'cash_counter', 'amount' => 100, 'status' => 'processed', 'reference' => 'AUDIT-CROSS-PERIOD', 'requested_at' => now(), 'processed_at' => now()]);
        $ledger->recordRefund($refund);
        $response = $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))->getJson(route('reporting.data', ['period' => 'monthly']))
            ->assertOk()->assertJsonPath('primaryAmount', -50)->assertJsonPath('serviceChartType', 'bar');
        $data = $response->json();
        $index = array_search('Swedish Massage', $data['serviceLabels'], true);
        $this->assertSame(-100, $data['serviceTotals'][$index]);
        $data['salesTrendChart'] = $data['serviceRevenueChart'] = 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg"/>');
        $data['ledgerRows'] = [];
        $html = view('reporting.pdf', $data)->render();
        $this->assertStringContainsString('Swedish Massage', $html);
        $this->assertStringContainsString('-100.00', $html);
    }

    private function therapist(): Therapist
    {
        return Therapist::create(['therapist_code' => 'AUDIT-LINKED', 'name' => 'Audit Therapist', 'is_active' => true, 'status' => 'available', 'work_on_off_day' => true]);
    }

    private function booking(array $values = []): SpaBooking
    {
        $client = User::factory()->create(['role' => User::ROLE_USER]);

        return SpaBooking::create(array_replace(['user_id' => $client->id, 'client_name' => $client->name, 'service_name' => 'Swedish Massage', 'therapist_name' => 'Audit Therapist', 'booking_date' => now()->toDateString(), 'time_slot' => '1:00 PM', 'duration_minutes' => 60, 'amount' => 100, 'payment_amount' => 100, 'payment_method' => 'cash_counter', 'payment_status' => 'paid', 'session_status' => 'confirmed'], $values));
    }
}
