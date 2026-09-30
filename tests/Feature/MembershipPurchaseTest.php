<?php

namespace Tests\Feature;

use App\Models\MembershipPlan;
use App\Models\MembershipPurchase;
use App\Models\User;
use App\Services\MembershipPurchaseService;
use App\Services\PaymongoService;
use App\Support\PaymentMethodCatalog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipPurchaseTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_customer_can_purchase_and_activate_membership_after_verified_payment(): void
    {
        Carbon::setTestNow('2026-09-30 10:00:00');
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $plan = MembershipPlan::query()->firstOrFail();

        $this->mock(PaymongoService::class, function ($mock): void {
            $mock->shouldReceive('isConfigured')->once()->andReturnTrue();
            $mock->shouldReceive('startMembershipCheckout')->once()
                ->andReturn('https://checkout.paymongo.com/membership-test');
        });

        $this->actingAs($customer)
            ->post(route('membership.purchase', $plan))
            ->assertRedirect('https://checkout.paymongo.com/membership-test');

        $purchase = MembershipPurchase::query()->firstOrFail();
        $purchase->forceFill(['paymongo_checkout_session_id' => 'cs_membership_test'])->save();
        $session = [
            'id' => 'cs_membership_test',
            'attributes' => [
                'amount' => 49900,
                'currency' => 'PHP',
                'payments' => [[
                    'id' => 'pay_membership_test',
                    'attributes' => [
                        'status' => 'paid',
                        'amount' => 49900,
                        'currency' => 'PHP',
                        'source' => ['type' => 'gcash'],
                    ],
                ]],
            ],
        ];

        $this->mock(PaymongoService::class, function ($mock) use ($session): void {
            $mock->shouldReceive('retrieveCheckoutSession')->once()->with('cs_membership_test')->andReturn($session);
            $mock->shouldReceive('isCheckoutSessionPaid')->once()->with($session)->andReturnTrue();
            $mock->shouldReceive('extractPaidPaymentIdFromSession')->once()->with($session)->andReturn('pay_membership_test');
            $mock->shouldReceive('extractPaymentChannelFromSession')->once()->with($session)->andReturn('gcash');
        });

        $this->actingAs($customer)
            ->get(route('membership.success', $purchase))
            ->assertRedirect(route('landing').'#membership')
            ->assertSessionHas('membership_status');

        $purchase->refresh();
        $this->assertSame(PaymentMethodCatalog::STATUS_PAID, $purchase->payment_status);
        $this->assertSame(MembershipPurchase::STATUS_ACTIVE, $purchase->status);
        $this->assertSame('2027-09-30', $purchase->expires_at?->toDateString());
        $this->assertSame('pay_membership_test', $purchase->payment_transaction_id);
        $this->assertTrue($purchase->isActive());
    }

    public function test_membership_payment_with_wrong_amount_is_not_activated(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $plan = MembershipPlan::query()->firstOrFail();
        $purchase = MembershipPurchase::query()->create([
            'user_id' => $customer->id,
            'membership_plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'validity_days' => 365,
            'amount' => 499,
            'paymongo_checkout_session_id' => 'cs_wrong_amount',
        ]);
        $session = ['id' => 'cs_wrong_amount', 'attributes' => [
            'amount' => 100,
            'currency' => 'PHP',
            'payments' => [['attributes' => ['status' => 'paid']]],
        ]];

        $this->mock(PaymongoService::class, function ($mock) use ($session): void {
            $mock->shouldReceive('retrieveCheckoutSession')->once()->andReturn($session);
            $mock->shouldReceive('isCheckoutSessionPaid')->once()->andReturnTrue();
        });

        $this->actingAs($customer)->get(route('membership.success', $purchase))
            ->assertRedirect(route('landing').'#membership')
            ->assertSessionHasErrors('membership');

        $this->assertSame(PaymentMethodCatalog::STATUS_PENDING, $purchase->fresh()->payment_status);
    }

    public function test_renewal_starts_after_current_membership_expires(): void
    {
        Carbon::setTestNow('2026-09-30 10:00:00');
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $plan = MembershipPlan::query()->firstOrFail();
        MembershipPurchase::query()->create([
            'user_id' => $customer->id,
            'membership_plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'validity_days' => 365,
            'amount' => 499,
            'payment_status' => PaymentMethodCatalog::STATUS_PAID,
            'status' => MembershipPurchase::STATUS_ACTIVE,
            'paid_at' => now()->subMonth(),
            'starts_at' => now()->subMonth(),
            'expires_at' => now()->addMonth(),
        ]);
        $renewal = MembershipPurchase::query()->create([
            'user_id' => $customer->id,
            'membership_plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'validity_days' => 365,
            'amount' => 499,
        ]);

        $renewal = app(MembershipPurchaseService::class)->confirm($renewal, 'pay_renewal', 'qrph');

        $this->assertSame(MembershipPurchase::STATUS_SCHEDULED, $renewal->status);
        $this->assertSame('2026-10-30', $renewal->starts_at?->toDateString());
        $this->assertSame('2027-10-30', $renewal->expires_at?->toDateString());
    }

    public function test_active_member_receives_server_supplied_package_price(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $plan = MembershipPlan::query()->firstOrFail();
        MembershipPurchase::query()->create([
            'user_id' => $customer->id,
            'membership_plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'validity_days' => 365,
            'amount' => 499,
            'payment_status' => PaymentMethodCatalog::STATUS_PAID,
            'status' => MembershipPurchase::STATUS_ACTIVE,
            'paid_at' => now(),
            'starts_at' => now()->subMinute(),
            'expires_at' => now()->addYear(),
        ]);

        $this->actingAs($customer)
            ->get(route('booking.index', ['service' => 'THERA #2']))
            ->assertOk()
            ->assertViewHas('servicePriceMap', fn (array $prices): bool => (float) ($prices['THERA #2'] ?? 0) === 549.0)
            ->assertSee('Member rate')
            ->assertSee('PHP 549.00');
    }

    public function test_staff_roles_cannot_purchase_customer_membership(): void
    {
        $plan = MembershipPlan::query()->firstOrFail();
        $receptionist = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($receptionist)
            ->post(route('membership.purchase', $plan))
            ->assertRedirect(route('receptionist.dashboard'));

        $this->actingAs($admin)
            ->post(route('membership.purchase', $plan))
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseCount('membership_purchases', 0);
    }
}
