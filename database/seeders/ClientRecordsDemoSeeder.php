<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClientRecordsDemoSeeder extends Seeder
{
    /**
     * Ensure at least one customer exists for Client Records.
     */
    public function run(): void
    {
        $email = 'demo.client@touchrelief.local';

        $customer = Customer::query()->where('email', $email)->first();

        if ($customer === null) {
            $nextNumber = (int) (Customer::query()->max('id') ?? 0) + 1;
            $customerId = 'CUS-' . str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);

            $customer = Customer::query()->create([
                'customer_id' => $customerId,
                'full_name' => 'Maria Santos (Demo)',
                'birthday' => '1988-03-22',
                'number' => '+639171001001',
                'email' => $email,
                'password' => 'Password123!',
            ]);
        }

        $clientName = $customer->full_name;

        if (DB::table('transactions')->where('client_name', $clientName)->exists()) {
            return;
        }

        $demoTxns = [
            [
                'transaction_id' => 'T-DEMO-001',
                'service_name' => 'Hot Stone',
                'date' => now()->subDays(14)->toDateString(),
                'time' => '10:00:00',
                'duration' => 90,
                'amount' => 145.00,
                'notes' => 'Client requested light pressure around shoulder area. Session completed smoothly.',
            ],
            [
                'transaction_id' => 'T-DEMO-002',
                'service_name' => 'Swedish Massage',
                'date' => now()->subDays(21)->toDateString(),
                'time' => '14:30:00',
                'duration' => 60,
                'amount' => 85.00,
                'notes' => 'Follow-up on upper back tension. Recommended stretching between visits.',
            ],
            [
                'transaction_id' => 'T-DEMO-003',
                'service_name' => 'Deep Tissue',
                'date' => now()->subDays(28)->toDateString(),
                'time' => '16:00:00',
                'duration' => 90,
                'amount' => 140.00,
                'notes' => null,
            ],
        ];

        $linkedUserId = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($customer->email)])
            ->value('id');

        foreach ($demoTxns as $txn) {
            DB::table('transactions')->insert([
                'transaction_id' => $txn['transaction_id'],
                'client_name' => $clientName,
                'user_id' => $linkedUserId,
                'therapist_id' => 1,
                'service_name' => $txn['service_name'],
                'date' => $txn['date'],
                'time' => $txn['time'],
                'duration' => $txn['duration'],
                'amount' => $txn['amount'],
                'notes' => $txn['notes'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
