<?php

namespace Tests\Feature;

use App\Models\MembershipPlan;
use App\Models\MembershipPurchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipPlanEditingTest extends TestCase
{
    use RefreshDatabase;

    private function changes(): array
    {
        return ['name' => 'Updated Membership', 'description' => 'Updated member benefits.', 'price_amount' => '650.50', 'validity_days' => 180, 'benefits' => "Member package rates\n\nPriority updates", 'is_active' => '1'];
    }

    public function test_admin_can_edit_plan_and_publish_changes_without_changing_existing_purchases(): void
    {
        $plan = MembershipPlan::firstOrFail();
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $purchase = MembershipPurchase::create([
            'user_id' => $customer->id, 'membership_plan_id' => $plan->id,
            'plan_name' => $plan->name, 'validity_days' => $plan->validity_days, 'amount' => $plan->price_amount,
            'payment_method' => 'paymongo', 'payment_status' => 'paid', 'status' => 'active',
            'paid_at' => now(), 'starts_at' => now(), 'expires_at' => now()->addDays($plan->validity_days),
        ]);
        $original = $purchase->fresh()->getAttributes();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->get(route('services.index'))->assertOk()->assertSee('Edit membership');
        $this->actingAs($admin)->put(route('membership-plans.update', $plan), $this->changes())
            ->assertRedirect(route('services.index'))->assertSessionHasNoErrors();
        $this->assertSame('650.50', $plan->fresh()->price_amount);
        $this->assertSame(180, $plan->fresh()->validity_days);
        $this->assertSame(['Member package rates', 'Priority updates'], $plan->fresh()->benefits);
        $this->assertSame($original, $purchase->fresh()->getAttributes());
        $this->actingAs($customer)->get(route('landing'))->assertOk()->assertSee('Updated Membership')->assertSee('650.50')->assertSee('Priority updates');
        $this->assertDatabaseHas('activity_logs', ['action' => 'membership_plan.updated', 'subject_id' => $plan->id]);
        $this->actingAs($admin)->put(route('membership-plans.update', $plan), [...$this->changes(), 'is_active' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse($plan->fresh()->is_active);
        $this->actingAs($customer)->post(route('membership.purchase', $plan))->assertSessionHasErrors('membership');
        $this->assertSame($original, $purchase->fresh()->getAttributes());
    }

    public function test_invalid_plan_edit_keeps_values_and_does_not_update_the_plan(): void
    {
        $plan = MembershipPlan::firstOrFail();
        $original = $plan->getAttributes();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->from(route('services.index'))->put(route('membership-plans.update', $plan), [
                ...$this->changes(), '_membership_plan_id' => $plan->id,
                'name' => '', 'price_amount' => -1, 'validity_days' => 0, 'is_active' => 'invalid',
            ])->assertRedirect(route('services.index'))->assertSessionHasErrors(['name', 'price_amount', 'validity_days', 'is_active'], null, 'membership');
        $this->get(route('services.index'))->assertOk()->assertSee('Updated member benefits.')->assertSee('class="membership-editor"', false);
        $this->assertSame($original, $plan->fresh()->getAttributes());
    }

    public function test_customers_and_receptionists_cannot_edit_membership_plans(): void
    {
        $plan = MembershipPlan::firstOrFail();
        $original = $plan->getAttributes();
        foreach ([User::ROLE_USER, User::ROLE_RECEPTIONIST] as $role) {
            $response = $this->actingAs(User::factory()->create(['role' => $role]))
                ->put(route('membership-plans.update', $plan), $this->changes());
            if ($role === User::ROLE_RECEPTIONIST) {
                $response->assertRedirect(route('receptionist.dashboard'));
            } else {
                $response->assertForbidden();
            }
        }
        $this->assertSame($original, $plan->fresh()->getAttributes());
    }
}
