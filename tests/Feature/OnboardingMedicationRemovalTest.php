<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OnboardingMedicationRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboarding_omits_medication_question_and_table_is_removed(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_USER,
            'sex' => User::SEX_MALE,
            'profile_completed_at' => null,
        ]);
        $this->assertFalse(Schema::hasTable('user_medications'));

        $this->actingAs($user)->get(route('landing'))
            ->assertOk()
            ->assertSee('Therapist gender preference')
            ->assertDontSee('Current medications')
            ->assertDontSee('name="medications[]"', false);

        $this->actingAs($user)->post(route('onboarding.store'), [
            'therapist_gender_preference' => User::THERAPIST_PREF_NO,
            'pressure_preference' => User::PRESSURE_MEDIUM,
        ])->assertRedirect(route('landing'));

        $this->assertNotNull($user->fresh()->profile_completed_at);
        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee('Current medications');
    }
}
