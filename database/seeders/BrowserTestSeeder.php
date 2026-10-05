<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Receptionist;
use App\Models\SpaBooking;
use App\Models\User;
use App\Services\BookingRefundService;
use App\Services\PaymentLedgerService;
use App\Support\PaymentMethodCatalog;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class BrowserTestSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (! app()->environment('e2e')) {
            throw new RuntimeException('BrowserTestSeeder may only run in the e2e environment.');
        }

        $password = 'AuditPassword9';
        $customerUser = User::create([
            'name' => 'Browser Customer',
            'username' => 'browser_customer',
            'email' => 'customer@browser.test',
            'contact_number' => '09170000001',
            'birthday' => '1995-05-15',
            'sex' => User::SEX_FEMALE,
            'therapist_gender_preference' => User::THERAPIST_PREF_NO,
            'is_pregnant' => false,
            'pressure_preference' => User::PRESSURE_MEDIUM,
            'profile_completed_at' => now(),
            'password' => $password,
            'role' => User::ROLE_USER,
        ]);

        Customer::create([
            'customer_id' => 'CUS-E2E-001',
            'full_name' => $customerUser->name,
            'birthday' => $customerUser->birthday,
            'number' => $customerUser->contact_number,
            'email' => $customerUser->email,
            'password' => $password,
        ]);

        $receptionist = User::create([
            'name' => 'Browser Receptionist',
            'username' => 'browser_receptionist',
            'email' => 'receptionist@browser.test',
            'contact_number' => '09170000002',
            'password' => $password,
            'role' => User::ROLE_RECEPTIONIST,
        ]);

        Receptionist::create([
            'receptionist_id' => 'REC-E2E-001',
            'full_name' => $receptionist->name,
            'username' => $receptionist->username,
            'email' => $receptionist->email,
            'phone_number' => $receptionist->contact_number,
            'birthday' => '1993-03-12',
            'address' => 'Browser Test Address',
            'shift' => '9:00 AM - 6:00 PM',
        ]);

        ActivityLog::create([
            'user_id' => $receptionist->id,
            'user_role' => User::ROLE_RECEPTIONIST,
            'user_name' => $receptionist->name,
            'action' => 'login',
            'description' => 'Existing receptionist browser-test account',
            'created_at' => now()->subDay(),
        ]);

        User::create([
            'name' => 'Browser Administrator',
            'username' => 'browser_admin',
            'email' => 'admin@browser.test',
            'contact_number' => '09170000003',
            'password' => $password,
            'role' => User::ROLE_ADMIN,
        ]);

        $bookings = [
            [
                'booking_date' => now()->addDay()->toDateString(),
                'time_slot' => '4:00 PM',
                'service_name' => 'Swedish Massage',
                'therapist_name' => 'Liza Reyes',
                'session_status' => SpaBooking::STATUS_CONFIRMED,
            ],
            [
                'booking_date' => now()->toDateString(),
                'time_slot' => now()->format('g:i A'),
                'service_name' => 'Foot Reflexology',
                'therapist_name' => 'Erica Tamondong',
                'session_status' => SpaBooking::STATUS_IN_SESSION,
                'session_started_at' => now()->subMinutes(10),
            ],
            [
                'booking_date' => now()->subDay()->toDateString(),
                'time_slot' => '2:00 PM',
                'service_name' => 'Hot Stone',
                'therapist_name' => 'Angela Fernandez',
                'session_status' => SpaBooking::STATUS_COMPLETED,
                'session_started_at' => now()->subDay()->setTime(14, 0),
                'completed_at' => now()->subDay()->setTime(15, 30),
            ],
            [
                'booking_date' => now()->subMinutes(15)->toDateString(),
                'time_slot' => now()->subMinutes(15)->format('g:i A'),
                'service_name' => 'Thai Massage',
                'therapist_name' => 'Carlos Mendoza',
                'session_status' => SpaBooking::STATUS_CONFIRMED,
            ],
            [
                'booking_date' => now()->toDateString(),
                'time_slot' => now()->format('g:i A'),
                'service_name' => 'Aromatherapy',
                'therapist_name' => 'Juan dela Cruz',
                'session_status' => SpaBooking::STATUS_CONFIRMED,
                'amount' => 100,
                'payment_type' => PaymentMethodCatalog::TYPE_DOWNPAYMENT,
                'payment_amount' => 50,
                'payment_transaction_id' => 'pay_e2e_partial_start',
            ],
        ];

        foreach ($bookings as $details) {
            $booking = SpaBooking::create(array_merge([
                'user_id' => $customerUser->id,
                'client_name' => $customerUser->name,
                'booking_source' => SpaBooking::SOURCE_ONLINE,
                'duration_minutes' => 60,
                'amount' => 85,
                'payment_method' => PaymentMethodCatalog::CHANNEL_QRPH,
                'payment_type' => PaymentMethodCatalog::TYPE_FULL,
                'payment_amount' => 85,
                'payment_status' => PaymentMethodCatalog::STATUS_PAID,
                'payment_transaction_id' => 'pay_e2e_'.strtolower(str_replace(' ', '_', $details['service_name'])),
            ], $details));

            app(PaymentLedgerService::class)->recordInitialPayment($booking);
        }

        foreach (range(0, 2) as $index) {
            $booking = SpaBooking::create([
                'user_id' => $customerUser->id, 'client_name' => $customerUser->name,
                'booking_source' => SpaBooking::SOURCE_ONLINE, 'service_name' => 'Foot Reflexology',
                'therapist_name' => 'Erica Tamondong', 'duration_minutes' => 60,
                'booking_date' => now()->toDateString(), 'time_slot' => '10:00 AM',
                'amount' => 100, 'payment_amount' => 100, 'payment_type' => PaymentMethodCatalog::TYPE_FULL,
                'payment_method' => PaymentMethodCatalog::METHOD_CASH_COUNTER,
                'payment_status' => PaymentMethodCatalog::STATUS_PAID,
                'payment_transaction_id' => 'REFUND-E2E-'.$index,
                'cancelled_at' => now(), 'session_status' => SpaBooking::STATUS_CANCELLED,
            ]);
            app(PaymentLedgerService::class)->recordInitialPayment($booking);
            app(BookingRefundService::class)->processRefund($booking);
        }
    }
}
