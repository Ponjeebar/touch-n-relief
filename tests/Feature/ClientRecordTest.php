<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\SpaBooking;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_statuses_recalculate_at_completion_and_configured_time_boundaries(): void
    {
        Carbon::setTestNow('2026-10-15 12:00:00');
        config(['touchnrelief.client_new_days' => 7, 'touchnrelief.client_active_months' => 2]);

        try {
            $staff = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);
            $client = User::factory()->create(['role' => User::ROLE_USER]);
            $customer = Customer::create([
                'customer_id' => 'CUS-STATUS-001', 'full_name' => $client->name,
                'email' => $client->email, 'password' => 'unused-password',
            ]);
            $customer->forceFill(['created_at' => '2026-10-08 12:00:00'])->save();
            $booking = SpaBooking::create([
                'user_id' => $client->id, 'client_name' => $client->name,
                'service_name' => 'Swedish Massage', 'therapist_name' => 'Status Test Therapist',
                'booking_date' => '2026-08-15', 'time_slot' => '10:00 AM',
                'session_status' => SpaBooking::STATUS_CONFIRMED,
            ]);

            $response = $this->actingAs($staff)->get(route('client-records.index', ['sort' => 'inactive']));
            $response->assertOk()->assertSee('How client statuses update')->assertSee('last 2 months')->assertSee('last 7 days');
            $record = $response->viewData('customers')->firstWhere('id', $customer->id);
            $this->assertNotNull($record);
            $this->assertFalse($record->is_active);
            $this->assertTrue($record->is_new_user);

            $booking->forceFill(['session_status' => SpaBooking::STATUS_COMPLETED, 'completed_at' => '2026-08-15 11:00:00'])->save();
            $response = $this->get(route('client-records.index', ['sort' => 'active']));
            $record = $response->viewData('customers')->firstWhere('id', $customer->id);
            $this->assertNotNull($record, 'The completed visit on the cutoff date must qualify as active.');
            $this->assertTrue($record->is_active);
            $this->assertTrue($record->is_new_user, 'New is an additional tag that can coexist with Active.');
            $this->assertSame(0, $this->get(route('client-records.index', ['sort' => 'inactive']))->viewData('customers')->total());

            Carbon::setTestNow('2026-10-15 12:00:01');
            $this->assertSame(0, $this->get(route('client-records.index', ['sort' => 'new_user']))->viewData('customers')->total());
            Carbon::setTestNow('2026-10-16 12:00:00');
            $this->assertSame(0, $this->get(route('client-records.index', ['sort' => 'active']))->viewData('customers')->total());
            $record = $this->get(route('client-records.index', ['sort' => 'inactive']))->viewData('customers')->firstWhere('id', $customer->id);
            $this->assertNotNull($record);
            $this->assertFalse($record->is_active);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_staff_can_view_a_client_record_when_a_transaction_has_no_linked_therapist(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);
        $client = User::factory()->create(['role' => User::ROLE_USER]);
        $customer = Customer::create([
            'customer_id' => 'CUS-TEST-001',
            'full_name' => $client->name,
            'email' => $client->email,
            'password' => 'unused-password',
        ]);

        Transaction::create([
            'transaction_id' => 'TXN-CLIENT-001',
            'client_name' => $client->name,
            'user_id' => $client->id,
            'therapist_id' => 999999,
            'service_name' => 'Swedish Massage',
            'date' => now()->toDateString(),
            'time' => '10:00:00',
            'duration' => 60,
            'amount' => 500,
        ]);

        $this->actingAs($staff)
            ->get(route('client-records.show', $customer))
            ->assertOk()
            ->assertSee('TXN-CLIENT-001')
            ->assertSee('Swedish Massage')
            ->assertSee('client-records.css?v=', false)
            ->assertSee('mobile-navigation.js?v=', false)
            ->assertSee('data-label="Transaction ID"', false)
            ->assertSee('data-label="Session notes"', false);
    }
}
