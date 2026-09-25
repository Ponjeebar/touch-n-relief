<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessibilityMarkupTest extends TestCase
{
    use RefreshDatabase;

    public function test_receptionist_dashboard_does_not_label_a_non_form_time_slot_list(): void
    {
        $receptionist = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);

        $this->actingAs($receptionist)
            ->get(route('receptionist.dashboard'))
            ->assertOk()
            ->assertDontSee('for="tnr-reschedule-slots"', false)
            ->assertSee('id="tnr-reschedule-slots-label"', false)
            ->assertSee('aria-labelledby="tnr-reschedule-slots-label"', false)
            ->assertSee('aria-describedby="tnr-reschedule-slots-hint"', false);
    }
}
