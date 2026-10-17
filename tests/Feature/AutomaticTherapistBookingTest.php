<?php

namespace Tests\Feature;

use App\Models\SpaBooking;
use App\Models\Therapist;
use App\Models\User;
use App\Services\BookingSlotService;
use App\Services\UserActivityService;
use App\Support\PaymentMethodCatalog;
use Carbon\Carbon;
use Database\Seeders\SpaServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AutomaticTherapistBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-19 08:00:00');
        config()->set('services.paymongo.secret_key', 'sk_test_auto_therapist');
        $this->seed(SpaServiceSeeder::class);
        app(BookingSlotService::class)->seedDefaults();
        app(BookingSlotService::class)->attachDefaultSlotsForServicesWithoutSchedule();
        foreach (['Ada Therapist', 'Bea Therapist'] as $name) {
            Therapist::query()->create(['therapist_code' => str_replace(' ', '-', $name), 'name' => $name, 'is_active' => true, 'status' => 'available', 'work_on_off_day' => true]);
        }
        Therapist::query()->whereNotIn('name', ['Ada Therapist', 'Bea Therapist'])->update(['is_active' => false]);
        Http::fake(['api.paymongo.com/*' => Http::response(['data' => [
            'id' => 'cs_auto_test', 'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/auto-test'],
        ]])]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function choices(array $overrides = []): array
    {
        return array_replace([
            'service' => 'Foot Reflexology', 'therapist' => BookingSlotService::ANY_THERAPIST,
            'booking_date' => '2026-10-20', 'time_slot' => '10:00 AM',
            'payment_type' => 'full', 'no_show_policy_accepted' => '1',
        ], $overrides);
    }

    private function busy(string $name, ?User $user = null): SpaBooking
    {
        $user ??= User::factory()->create(['role' => User::ROLE_USER]);

        return SpaBooking::query()->create([
            'user_id' => $user->id, 'client_name' => $user->name, 'service_name' => 'Foot Reflexology',
            'therapist_name' => $name, 'booking_date' => '2026-10-20', 'time_slot' => '10:00 AM',
            'duration_minutes' => 45, 'amount' => 70, 'session_status' => SpaBooking::STATUS_CONFIRMED,
            'payment_method' => 'cash_counter', 'payment_status' => PaymentMethodCatalog::STATUS_PAID,
            'payment_amount' => 70, 'payment_type' => 'full',
        ]);
    }

    public function test_any_option_shows_union_of_availability_and_reserves_a_named_eligible_therapist(): void
    {
        $this->busy('Ada Therapist');
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $response = $this->actingAs($customer)->getJson(route('booking.availability', $this->choices()))->assertOk();
        $this->assertContains('10:00 AM', $response->json('offered_slots'));
        $this->assertNotContains('10:00 AM', $response->json('fully_booked_slots'));
        $this->post(route('booking.store'), $this->choices())->assertRedirect('https://checkout.paymongo.com/auto-test');
        $booking = SpaBooking::query()->where('user_id', $customer->id)->sole();
        $this->assertSame('Bea Therapist', $booking->therapist_name);
        $this->assertSame(PaymentMethodCatalog::STATUS_PENDING, $booking->payment_status);
        $this->assertSame('cs_auto_test', $booking->paymongo_checkout_session_id);
        $history = app(UserActivityService::class)->transactionsForUser($customer);
        $this->assertSame('Bea Therapist', $history[0]['therapist']);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('payment_ledger_entries', 0);
        Http::assertSentCount(1);
    }

    public function test_no_capacity_rejects_submission_without_checkout_or_extra_booking(): void
    {
        $this->busy('Ada Therapist');
        $this->busy('Bea Therapist');
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $response = $this->actingAs($customer)->getJson(route('booking.availability', $this->choices()))->assertOk();
        $this->assertContains('10:00 AM', $response->json('fully_booked_slots'));
        $this->post(route('booking.store'), $this->choices())->assertSessionHasErrors('time_slot', null, 'booking');
        $this->assertDatabaseCount('spa_bookings', 2);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('payment_ledger_entries', 0);
        Http::assertNothingSent();
    }

    public function test_inactive_and_off_duty_therapists_are_not_assignable(): void
    {
        Therapist::query()->where('name', 'Ada Therapist')->update(['is_active' => false]);
        Therapist::query()->where('name', 'Bea Therapist')->update(['work_on_off_day' => false, 'day_off_until' => '2026-10-25']);
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $response = $this->actingAs($customer)->getJson(route('booking.availability', $this->choices()))->assertOk();
        $this->assertSame($response->json('offered_slots'), $response->json('fully_booked_slots'));
        $this->post(route('booking.store'), $this->choices())->assertSessionHasErrors('therapist', null, 'booking');
        $this->assertDatabaseCount('spa_bookings', 0);
        Http::assertNothingSent();
    }

    public function test_auto_assignment_preserves_customer_conflicts_and_minimum_lead_time(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $this->busy('Ada Therapist', $customer);
        $this->actingAs($customer)->post(route('booking.store'), $this->choices())
            ->assertSessionHas('booking_user_conflict');
        $this->assertDatabaseCount('spa_bookings', 1);
        $this->post(route('booking.store'), $this->choices(['booking_date' => '2026-10-19', 'time_slot' => '8:00 AM']))
            ->assertSessionHasErrors('time_slot', null, 'booking');
        $this->assertDatabaseCount('spa_bookings', 1);
        Http::assertNothingSent();
    }
}
