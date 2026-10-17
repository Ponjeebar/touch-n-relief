<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingSkipTest extends TestCase
{
    use RefreshDatabase;

    public function test_skip_dismisses_onboarding_without_inventing_or_saving_submitted_answers(): void
    {
        foreach ([User::SEX_MALE, User::SEX_FEMALE] as $sex) {
            $user = User::factory()->create(['role' => User::ROLE_USER, 'sex' => $sex,
                'profile_completed_at' => null, 'therapist_gender_preference' => null,
                'pressure_preference' => null, 'is_pregnant' => null]);
            $this->actingAs($user)->post(route('onboarding.store'), [
                'action' => 'skip', 'therapist_gender_preference' => 'male',
                'is_pregnant' => '1', 'return_to' => '//example.com',
            ])->assertRedirect(route('landing'))->assertSessionHas('customer_tour_pending', true);
            $user->refresh();
            $this->assertNotNull($user->profile_completed_at);
            $this->assertNull($user->therapist_gender_preference);
            $this->assertNull($user->pressure_preference);
            $this->assertNull($user->is_pregnant);
            $this->assertFalse($user->canAccessPrenatalServices());
            $this->get(route('landing'))->assertOk()->assertDontSee('id="tnr-registration-onboarding-modal"', false);
            $this->get(route('profile.edit'))->assertOk()->assertDontSee('class="profile-wellness-badge"', false);
            $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action' => 'profile.onboarding_skipped']);
            $this->post(route('onboarding.store'), ['action' => 'skip'])->assertRedirect(route('landing'));
            $this->assertSame(1, ActivityLog::where('user_id', $user->id)->where('action', 'profile.onboarding_skipped')->count());
        }
    }

    public function test_skip_preserves_existing_preferences_and_accepts_safe_return_path(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_USER, 'profile_completed_at' => null,
            'sex' => User::SEX_FEMALE, 'therapist_gender_preference' => 'female',
            'pressure_preference' => 'low', 'is_pregnant' => true]);
        $this->actingAs($user)->post(route('onboarding.store'), ['action' => 'skip', 'return_to' => '/profile'])
            ->assertRedirect('/profile');
        $user->refresh();
        $this->assertSame('female', $user->therapist_gender_preference);
        $this->assertSame('low', $user->pressure_preference);
        $this->assertTrue($user->is_pregnant);
    }

    public function test_completion_still_requires_answers_and_staff_cannot_skip_customer_onboarding(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_USER, 'profile_completed_at' => null, 'sex' => User::SEX_FEMALE]);
        $this->actingAs($user)->post(route('onboarding.store'), [])
            ->assertSessionHasErrors(['therapist_gender_preference', 'pressure_preference', 'is_pregnant'], errorBag: 'onboarding');
        $this->assertNull($user->fresh()->profile_completed_at);
        foreach ([User::ROLE_ADMIN, User::ROLE_RECEPTIONIST] as $role) {
            $staff = User::factory()->create(['role' => $role, 'profile_completed_at' => null]);
            $this->actingAs($staff)->post(route('onboarding.store'), ['action' => 'skip'])->assertRedirect();
            $this->assertNull($staff->fresh()->profile_completed_at);
        }
    }
}
