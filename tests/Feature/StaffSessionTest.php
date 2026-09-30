<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureCurrentStaffSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_receptionist_login_replaces_the_previous_session(): void
    {
        $receptionist = User::factory()->create([
            'role' => User::ROLE_RECEPTIONIST,
            'password' => 'CorrectPassword123!',
        ]);

        $this->post(route('login.attempt'), [
            'login' => $receptionist->email,
            'password' => 'CorrectPassword123!',
        ])->assertRedirect(route('receptionist.dashboard'));

        $firstToken = (string) $receptionist->fresh()->staff_session_token;
        $this->assertNotSame('', $firstToken);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $receptionist->forceFill(['staff_session_token' => $firstToken])->save();

        $this->post(route('login.attempt'), [
            'login' => $receptionist->email,
            'password' => 'CorrectPassword123!',
        ])->assertRedirect(route('receptionist.dashboard'));

        $secondToken = (string) $receptionist->fresh()->staff_session_token;
        $this->assertNotSame($firstToken, $secondToken);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $receptionist->id,
            'action' => 'staff.session_replaced',
        ]);

        $this->withSession([EnsureCurrentStaffSession::SESSION_KEY => $firstToken])
            ->actingAs($receptionist->fresh())
            ->get(route('receptionist.dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['login']);

        $this->assertGuest();
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $receptionist->id,
            'action' => 'staff.session_rejected',
        ]);
    }

    public function test_administrator_sessions_use_the_same_replacement_rule(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'staff_session_token' => 'new-admin-token',
        ]);

        $this->withSession([EnsureCurrentStaffSession::SESSION_KEY => 'old-admin-token'])
            ->actingAs($admin)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['login']);

        $this->assertGuest();
    }

    public function test_customer_sessions_are_not_restricted_to_one_device(): void
    {
        $customer = User::factory()->create([
            'role' => User::ROLE_USER,
            'staff_session_token' => 'unrelated-token',
        ]);

        $this->withSession([EnsureCurrentStaffSession::SESSION_KEY => 'different-token'])
            ->actingAs($customer)
            ->get(route('profile.edit'))
            ->assertOk();

        $this->assertAuthenticatedAs($customer);
    }

    public function test_staff_password_change_rotates_other_sessions_without_signing_out_the_current_device(): void
    {
        $receptionist = User::factory()->create([
            'role' => User::ROLE_RECEPTIONIST,
            'password' => 'CurrentPassword9',
            'staff_session_token' => 'original-token',
        ]);

        $this->withSession([EnsureCurrentStaffSession::SESSION_KEY => 'original-token'])
            ->actingAs($receptionist)
            ->put(route('profile.update'), [
                'name' => $receptionist->name,
                'email' => $receptionist->email,
                'username' => $receptionist->username,
                'current_password' => 'CurrentPassword9',
                'password' => 'ReplacementPassword9',
                'password_confirmation' => 'ReplacementPassword9',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $newToken = (string) $receptionist->fresh()->staff_session_token;
        $this->assertNotSame('original-token', $newToken);
        $this->assertSame($newToken, session(EnsureCurrentStaffSession::SESSION_KEY));

        $this->get(route('receptionist.dashboard'))->assertOk();
        $this->assertAuthenticatedAs($receptionist);
    }

    public function test_archived_staff_are_signed_out_on_their_next_request(): void
    {
        $receptionist = User::factory()->create([
            'role' => User::ROLE_RECEPTIONIST,
            'archived_at' => now(),
            'staff_session_token' => 'active-token',
        ]);

        $this->withSession([EnsureCurrentStaffSession::SESSION_KEY => 'active-token'])
            ->actingAs($receptionist)
            ->get(route('receptionist.dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['login']);

        $this->assertGuest();
    }
}
