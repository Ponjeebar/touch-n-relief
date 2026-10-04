<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\SpaService;
use App\Models\Therapist;
use App\Models\User;
use App\Services\BookingSlotService;
use App\Services\SpaServiceCatalog;
use App\Services\TherapistAvailabilityService;
use App\Services\TherapistCatalog;
use App\Support\CustomerEligibility;
use App\Support\PaymentMethodCatalog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SharedBusinessRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_database_catalogs_do_not_restore_inactive_or_deleted_defaults(): void
    {
        SpaService::query()->update(['is_active' => false]);
        Therapist::query()->update(['is_active' => false]);
        SpaService::query()->create([
            'name' => 'Swedish Massage',
            'price_amount' => 85,
            'duration_minutes' => 60,
            'best_for' => 'Relaxation',
            'description' => 'Inactive test service',
            'offering_type' => 'service',
            'is_active' => false,
        ]);
        $therapist = Therapist::query()->create([
            'therapist_code' => 'INACTIVE-1',
            'name' => 'Inactive Therapist',
            'status' => 'available',
            'work_on_off_day' => true,
            'is_active' => false,
        ]);

        $this->assertSame([], app(SpaServiceCatalog::class)->all());
        $this->assertSame([], app(TherapistCatalog::class)->forBooking());
        $this->assertFalse(app(TherapistAvailabilityService::class)->isBookable($therapist));
        $this->assertFalse(SpaService::query()->where('is_active', true)->exists());
        $this->assertFalse(Therapist::query()->where('is_active', true)->exists());
    }

    public function test_unknown_deleted_and_inactive_therapists_are_rejected_by_final_validation(): void
    {
        $availability = app(TherapistAvailabilityService::class);
        Therapist::query()->create([
            'therapist_code' => 'DELETED-1',
            'name' => 'Deleted Therapist',
            'status' => 'available',
            'work_on_off_day' => true,
            'is_active' => true,
        ])->delete();
        foreach (['Unknown Therapist', 'Deleted Therapist', 'Inactive Therapist'] as $name) {
            if ($name === 'Inactive Therapist') {
                Therapist::query()->create([
                    'therapist_code' => 'INACTIVE-2',
                    'name' => $name,
                    'status' => 'available',
                    'work_on_off_day' => true,
                    'is_active' => false,
                ]);
            }

            try {
                $availability->assertBookableOnDate($name, now()->addDay()->toDateString());
                $this->fail($name.' should have been rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('therapist', $exception->errors());
            }
        }
    }

    public function test_empty_appointment_database_does_not_show_operational_sample_clients(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->get(route('appointments.index', ['date' => now()->toDateString()]))
            ->assertOk()
            ->assertDontSee('Otis Wright')
            ->assertDontSee('Zayd Wilson');
    }

    public function test_downpayment_backend_and_both_booking_interfaces_share_one_rate(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->assertSame(50, PaymentMethodCatalog::downpaymentPercentage());
        $this->assertSame(75.0, PaymentMethodCatalog::calculateAmount(150, PaymentMethodCatalog::TYPE_DOWNPAYMENT));

        $customerPage = $this->actingAs($customer)->get(route('booking.index'));
        $this->assertSame(PaymentMethodCatalog::DOWNPAYMENT_RATE, $customerPage->viewData('downpaymentRate'));
        $customerPage->assertSee('50% to confirm');

        $staffPage = $this->actingAs($admin)->get(route('appointments.index'));
        $this->assertSame(PaymentMethodCatalog::DOWNPAYMENT_RATE, $staffPage->viewData('downpaymentRate'));
        $staffPage->assertSee('Downpayment (50%)');
    }

    public function test_customer_age_rule_is_shared_by_registration_profile_and_staff_account_paths(): void
    {
        Carbon::setTestNow('2026-10-04 10:00:00');
        $tooYoung = Carbon::parse(CustomerEligibility::latestEligibleBirthday())->addDay()->toDateString();
        $customer = User::factory()->create([
            'role' => User::ROLE_USER,
            'birthday' => '2000-01-01',
        ]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->post(route('register'), [
            'name' => 'Young Customer', 'username' => 'young_customer', 'email' => 'young@example.test',
            'contact_number' => '09123456789', 'birthday' => $tooYoung, 'sex' => 'female',
            'password' => 'StrongPass1', 'password_confirmation' => 'StrongPass1', 'terms_accepted' => '1',
        ])->assertSessionHasErrors('birthday', null, 'register');

        $this->actingAs($customer)->put(route('profile.update'), [
            'name' => $customer->name, 'username' => $customer->username, 'email' => $customer->email,
            'contact_number' => $customer->contact_number, 'birthday' => $tooYoung,
        ])->assertSessionHasErrors('birthday', null, 'profile');

        $this->actingAs($admin)->post(route('dashboard.customers.store'), [
            'full_name' => 'Young Admin Customer', 'email' => 'young-admin@example.test',
            'birthday' => $tooYoung, 'password' => 'StrongPass1',
        ])->assertSessionHasErrors('birthday');

        $this->flushSession();
        app(BookingSlotService::class)->seedDefaults();
        $staff = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);
        $response = $this->actingAs($staff)->post(route('appointments.store'), [
            'client_type' => 'walk_in', 'client_name' => 'Young Walk In',
            'client_email' => 'young-walkin@example.test', 'client_phone' => '09987654321',
            'client_birthday' => $tooYoung, 'client_password' => 'StrongPass1',
            'client_password_confirmation' => 'StrongPass1',
        ]);
        $response->assertSessionHasErrors('client_birthday', null, 'appointment');
    }

    public function test_social_registration_uses_the_shared_customer_age_rule(): void
    {
        Carbon::setTestNow('2026-10-04 10:00:00');
        $tooYoung = Carbon::parse(CustomerEligibility::latestEligibleBirthday())->addDay()->toDateString();

        $this->withSession([
            'social_auth.pending' => [
                'provider' => 'google',
                'provider_user_id' => 'google-age-test',
                'name' => 'Young Social Customer',
                'email' => 'young-social@example.test',
                'created_at' => now()->timestamp,
            ],
        ])->post(route('social.store'), [
            'contact_number' => '09123456789',
            'birthday' => $tooYoung,
            'sex' => 'female',
            'password' => 'StrongPass1',
            'password_confirmation' => 'StrongPass1',
            'terms_accepted' => '1',
        ])->assertSessionHasErrors('birthday');
    }

    public function test_weak_passwords_are_rejected_for_admin_and_receptionist_created_accounts(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $receptionist = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);

        $this->actingAs($admin)->post(route('dashboard.customers.store'), [
            'full_name' => 'Weak Customer', 'email' => 'weak-customer@example.test', 'password' => 'password',
        ])->assertSessionHasErrors('password');

        $customer = Customer::query()->create([
            'customer_id' => Customer::nextCustomerId(),
            'full_name' => 'Existing Customer',
            'email' => 'existing-customer@example.test',
            'password' => 'StrongPass1',
        ]);
        $this->flushSession();
        $this->actingAs($admin)->put(route('dashboard.customers.update', $customer), [
            'full_name' => $customer->full_name,
            'email' => $customer->email,
            'password' => 'password',
        ])->assertSessionHasErrors('password');

        $this->flushSession();
        $this->actingAs($admin)->post(route('dashboard.receptionists.store'), [
            'full_name' => 'Weak Receptionist', 'username' => 'weak_staff',
            'email' => 'weak-staff@example.test', 'password' => 'password',
        ])->assertSessionHasErrors('password');

        $this->flushSession();
        app(BookingSlotService::class)->seedDefaults();
        $this->actingAs($receptionist)->post(route('appointments.store'), [
            'client_type' => 'walk_in', 'client_name' => 'Weak Walk In',
            'client_email' => 'weak-walkin@example.test', 'client_phone' => '09987654321',
            'client_password' => 'password', 'client_password_confirmation' => 'password',
        ])->assertSessionHasErrors('client_password', null, 'appointment');
    }
}
