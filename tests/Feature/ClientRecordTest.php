<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientRecordTest extends TestCase
{
    use RefreshDatabase;

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
            ->assertSee('Swedish Massage');
    }
}
