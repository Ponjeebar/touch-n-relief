<?php

namespace Tests\Feature;

use App\Models\AuthVerificationCode;
use App\Models\SpaService;
use App\Models\User;
use App\Services\BookingSlotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PersonalDataValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Exercise field validation repeatedly without hitting the signup request limit.
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function registration(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Maria Clara',
            'username' => 'maria_clara',
            'email' => 'maria@example.test',
            'contact_number' => '09123456789',
            'birthday' => now()->subYears(25)->toDateString(),
            'sex' => User::SEX_FEMALE,
            'password' => 'SecurePass1',
            'password_confirmation' => 'SecurePass1',
            'terms_accepted' => '1',
        ], $overrides);
    }

    public function test_invalid_registration_names_create_no_account_or_verification(): void
    {
        Notification::fake();
        foreach (['Maria123', '123', 'Maria@Clara', '<script>', '---', '   ', 'Maria😀'] as $name) {
            $this->post(route('register'), $this->registration(['name' => $name]))
                ->assertSessionHasErrors('name', null, 'register');
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('auth_verification_codes', 0);
        Notification::assertNothingSent();
    }

    public function test_names_accept_accents_initials_apostrophes_hyphens_and_single_names(): void
    {
        Notification::fake();
        foreach (['María-José O’Neill Jr.', "Jose\u{0301} Dela Cruz", '李明', 'Cher'] as $index => $name) {
            $email = "customer{$index}@example.test";
            $this->post(route('register'), $this->registration(['name' => $name, 'email' => $email]))
                ->assertSessionHasNoErrors();
            $verification = AuthVerificationCode::query()->where('email', $email)->firstOrFail();
            $this->assertSame($name, $verification->payload['registration']['name']);
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_rejects_invalid_related_fields_before_sending_verification(): void
    {
        Notification::fake();
        foreach ([
            ['username', 'name with spaces'], ['username', 'ab'],
            ['email', 'not-an-email'], ['contact_number', '12345678901'],
            ['contact_number', '0912345678x'], ['contact_number', '091234567890'],
            ['birthday', now()->addDay()->toDateString()],
            ['birthday', now()->subYears(14)->toDateString()],
            ['sex', 'invalid'], ['password_confirmation', 'DifferentPass1'],
        ] as [$field, $value]) {
            $this->post(route('register'), $this->registration([$field => $value]))
                ->assertSessionHasErrors($field === 'password_confirmation' ? 'password' : $field, null, 'register');
        }
        $this->assertDatabaseCount('auth_verification_codes', 0);
        $this->assertDatabaseCount('users', 0);
        Notification::assertNothingSent();
    }

    public function test_profile_cannot_bypass_name_validation(): void
    {
        $user = User::factory()->create(['name' => 'Maria Clara', 'username' => 'maria_clara', 'email_verified_at' => now()]);
        $this->actingAs($user)->put(route('profile.update'), [
            'name' => 'Maria123', 'username' => $user->username, 'email' => $user->email,
        ])->assertSessionHasErrors('name', null, 'profile');
        $this->assertSame('Maria Clara', $user->fresh()->name);
    }

    public function test_staff_entry_points_reject_invalid_names_before_creating_records(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->post(route('dashboard.customers.store'), [
            'full_name' => 'Client123', 'email' => 'client@example.test', 'password' => 'SecurePass1',
        ])->assertSessionHasErrors('full_name');
        $this->post(route('dashboard.receptionists.store'), [
            'full_name' => 'Staff123', 'username' => 'bad username', 'email' => 'staff@example.test',
            'birthday' => now()->addDay()->toDateString(), 'password' => 'SecurePass1',
        ])->assertSessionHasErrors(['full_name', 'username', 'birthday']);
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('receptionists', 0);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_google_signup_requires_a_valid_name_and_allows_correcting_the_provider_name(): void
    {
        $pending = [
            'provider' => 'google', 'provider_user_id' => 'google-name-check',
            'name' => 'Client123', 'email' => 'google@example.test', 'created_at' => now()->timestamp,
        ];
        $details = $this->registration(['email' => $pending['email']]);
        unset($details['name']);
        $this->withSession(['social_auth.pending' => $pending])->post(route('social.store'), $details)
            ->assertSessionHasErrors('name');
        $this->assertDatabaseCount('users', 0);
        $this->withSession(['social_auth.pending' => $pending])->post(route('social.store'), [...$details, 'name' => 'María Clara'])
            ->assertRedirect(route('landing'));
        $this->assertDatabaseHas('users', ['email' => $pending['email'], 'name' => 'María Clara']);
        $this->assertDatabaseHas('registrations', ['email' => $pending['email'], 'name' => 'María Clara']);
        $this->assertDatabaseHas('social_accounts', ['provider_user_id' => 'google-name-check']);
    }

    public function test_invalid_therapist_and_walk_in_names_leave_no_session_or_payment_records(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->post(route('therapists.store'), [
            'name' => 'Therapist123', 'birthday' => now()->addDay()->toDateString(),
        ])->assertSessionHasErrors(['name', 'birthday']);
        $this->assertDatabaseMissing('therapists', ['name' => 'Therapist123']);
        SpaService::query()->create([
            'name' => 'Hot Stone', 'price_amount' => 500, 'duration_minutes' => 60, 'is_active' => true,
            'best_for' => 'Relaxation', 'description' => 'Hot stone massage.',
        ]);
        app(BookingSlotService::class)->seedDefaults();
        $this->post(route('appointments.store'), [
            'client_type' => 'walk_in', 'client_name' => 'Client123',
        ])->assertSessionHasErrors('client_name', null, 'appointment');
        $this->assertDatabaseMissing('therapists', ['name' => 'Therapist123']);
        $this->assertDatabaseCount('spa_bookings', 0);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('payment_ledger_entries', 0);
        $this->assertDatabaseCount('users', 1);
    }
}
