<?php

namespace Tests\Feature;

use App\Models\SpaBooking;
use App\Models\Therapist;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\TherapistSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TherapistServiceHoursTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_service_hours_use_the_selected_year_and_cap_target_progress_without_losing_hours(): void
    {
        Carbon::setTestNow('2026-10-15 12:00:00');
        config(['touchnrelief.therapist_annual_service_target_hours' => 5]);

        try {
            $this->seed(TherapistSeeder::class);
            $therapist = Therapist::query()->firstOrFail();
            $staff = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);
            foreach ([['2025-12-31', 60], ['2026-01-01', 90]] as $index => [$date, $minutes]) {
                Transaction::create([
                    'transaction_id' => 'TXN-HOURS-'.$index, 'client_name' => 'Service Hours Test',
                    'therapist_id' => $therapist->id, 'service_name' => 'Swedish Massage',
                    'date' => $date, 'time' => '10:00:00', 'duration' => $minutes, 'amount' => 100,
                ]);
            }
            SpaBooking::create([
                'user_id' => User::factory()->create(['role' => User::ROLE_USER])->id,
                'client_name' => 'Unstarted Hours Test', 'service_name' => 'Swedish Massage',
                'therapist_name' => $therapist->name, 'booking_date' => '2026-10-15',
                'time_slot' => '03:00 PM', 'duration_minutes' => 120,
                'session_status' => SpaBooking::STATUS_CONFIRMED,
            ]);
            $response = $this->actingAs($staff)->get(route('therapist-tracking.index', ['year' => 2026]));
            $response->assertOk()->assertSee('How service hours and percentages are calculated')
                ->assertSee('January 1 to December 31, 2026')->assertSee('5 target hours');
            $record = $response->viewData('therapists')->firstWhere('id', $therapist->therapist_code);
            $this->assertEquals(1.5, $record['total_hours']);
            $this->assertSame(30, $record['service_hours_pct']);

            Transaction::create([
                'transaction_id' => 'TXN-HOURS-OVER-TARGET', 'client_name' => 'Service Hours Test',
                'therapist_id' => $therapist->id, 'service_name' => 'Swedish Massage',
                'date' => '2026-10-14', 'time' => '10:00:00', 'duration' => 600, 'amount' => 100,
            ]);
            $record = $this->get(route('therapist-tracking.index', ['year' => 2026]))
                ->viewData('therapists')->firstWhere('id', $therapist->therapist_code);
            $this->assertEquals(11.5, $record['total_hours']);
            $this->assertSame(100, $record['service_hours_pct']);
            $record = $this->get(route('therapist-tracking.index', ['year' => 2025]))
                ->viewData('therapists')->firstWhere('id', $therapist->therapist_code);
            $this->assertEquals(1, $record['total_hours']);
            $this->assertSame(20, $record['service_hours_pct']);
        } finally {
            Carbon::setTestNow();
        }
    }
}
