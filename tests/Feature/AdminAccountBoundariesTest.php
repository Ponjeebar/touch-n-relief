<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Receptionist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccountBoundariesTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_details_are_read_only_and_direct_update_changes_nothing(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->create(['role' => User::ROLE_USER]);
        $customer = Customer::create(['customer_id' => Customer::nextCustomerId(), 'full_name' => $user->name,
            'email' => $user->email, 'number' => $user->contact_number, 'password' => 'ExistingPass9!']);
        $this->actingAs($admin)->get(route('users.index', ['tab' => 'customers']))
            ->assertOk()->assertSee('View Record')->assertDontSee('cr-edit-customer-btn')->assertDontSee('id="cr-edit-customer-modal"', false);
        $beforeCustomer = $customer->fresh()->getAttributes();
        $beforeUser = $user->fresh()->getAttributes();
        $this->put(route('dashboard.customers.update', $customer), ['full_name' => 'Changed Name',
            'email' => 'changed@example.test', 'number' => '09999999999', 'password' => 'ChangedPass9!',
            'birthday' => '1995-01-01', 'is_pregnant' => true, 'pressure_preference' => 'high'])->assertForbidden();
        $this->assertSame($beforeCustomer, $customer->fresh()->getAttributes());
        $this->assertSame($beforeUser, $user->fresh()->getAttributes());
        $this->assertDatabaseMissing('activity_logs', ['action' => 'customer.updated']);

        $this->delete(route('dashboard.customers.destroy', $customer), ['return_to' => 'users.index'])->assertRedirect();
        $this->assertNotNull($customer->fresh()->archived_at);
        $this->patch(route('dashboard.customers.restore', $customer))->assertRedirect();
        $this->assertNull($customer->fresh()->archived_at);
    }

    public function test_admin_can_maintain_receptionist_record_but_cannot_change_login_identity(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);
        $receptionist = Receptionist::create(['receptionist_id' => 'RCP-TEST', 'full_name' => $user->name,
            'username' => $user->username, 'email' => $user->email, 'shift' => 'Morning']);
        $password = $user->password;
        $this->actingAs($admin)->put(route('dashboard.receptionists.update', $receptionist), [
            'full_name' => 'Updated Staff', 'username' => 'changed_login', 'email' => 'changed@example.test',
        ])->assertSessionHasErrors(['username', 'email']);
        $this->assertSame($user->name, $receptionist->fresh()->full_name);
        $this->assertDatabaseMissing('activity_logs', ['action' => 'receptionist.updated']);
        $this->put(route('dashboard.receptionists.update', $receptionist), [
            'full_name' => 'Updated Staff', 'phone_number' => '09170000000', 'address' => 'Work address',
        ])->assertRedirect();
        $this->assertSame('Updated Staff', $user->fresh()->name);
        $this->assertSame($user->email, $receptionist->fresh()->email);
        $this->assertSame($user->username, $receptionist->fresh()->username);
        $this->assertSame($password, $user->fresh()->password);
        $this->assertDatabaseHas('activity_logs', ['action' => 'receptionist.updated', 'subject_id' => $receptionist->id]);
    }
}
