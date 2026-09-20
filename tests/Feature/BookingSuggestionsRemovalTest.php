<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingSuggestionsRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_page_has_no_personalized_suggestions(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_USER]);

        $this->actingAs($customer)
            ->get(route('booking.index'))
            ->assertOk()
            ->assertDontSee('Suggested for You')
            ->assertDontSee('booking-suggestions');
    }

    public function test_staff_client_search_remains_without_service_suggestions(): void
    {
        $receptionist = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);

        $this->actingAs($receptionist)
            ->get(route('appointments.index'))
            ->assertOk()
            ->assertSee('add-client-search-results')
            ->assertDontSee('add-client-suggestions');

        $this->assertFalse(\Illuminate\Support\Facades\Route::has('appointments.clients.suggestions'));
    }
}
