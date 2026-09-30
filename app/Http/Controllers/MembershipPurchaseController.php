<?php

namespace App\Http\Controllers;

use App\Models\MembershipPlan;
use App\Models\MembershipPurchase;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\MembershipPurchaseService;
use App\Services\PaymongoService;
use App\Support\PaymentMethodCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MembershipPurchaseController extends Controller
{
    public function __construct(
        private readonly PaymongoService $paymongo,
        private readonly MembershipPurchaseService $memberships,
    ) {}

    public function store(Request $request, MembershipPlan $membershipPlan): RedirectResponse
    {
        if (! $membershipPlan->is_active) {
            return back()->withErrors(['membership' => 'This membership plan is no longer available.']);
        }

        if (! $this->paymongo->isConfigured()) {
            return back()->withErrors(['membership' => 'Online payment is unavailable right now. Please contact the spa.']);
        }

        $user = $request->user();
        abort_unless($user instanceof User && $user->isUser() && ! $user->isWalkIn(), 403);

        $purchase = DB::transaction(function () use ($user, $membershipPlan): MembershipPurchase {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            return MembershipPurchase::query()
                ->where('user_id', $user->id)
                ->where('membership_plan_id', $membershipPlan->id)
                ->where('payment_status', PaymentMethodCatalog::STATUS_PENDING)
                ->latest('id')
                ->first()
                ?? MembershipPurchase::query()->create([
                    'user_id' => $user->id,
                    'membership_plan_id' => $membershipPlan->id,
                    'plan_name' => $membershipPlan->name,
                    'validity_days' => max((int) $membershipPlan->validity_days, 1),
                    'amount' => $membershipPlan->price_amount,
                    'payment_method' => PaymentMethodCatalog::METHOD_PAYMONGO,
                    'payment_status' => PaymentMethodCatalog::STATUS_PENDING,
                    'status' => MembershipPurchase::STATUS_PENDING,
                ]);
        });

        return $this->openCheckout($purchase);
    }

    public function retry(Request $request, MembershipPurchase $membershipPurchase): RedirectResponse
    {
        $this->authorizeOwner($request, $membershipPurchase);

        if ($membershipPurchase->payment_status === PaymentMethodCatalog::STATUS_PAID) {
            return redirect()->to(route('landing').'#membership')
                ->with('membership_status', 'This membership payment has already been verified.');
        }

        return $this->openCheckout($membershipPurchase);
    }

    public function success(Request $request, MembershipPurchase $membershipPurchase): RedirectResponse
    {
        $this->authorizeOwner($request, $membershipPurchase);

        if ($membershipPurchase->payment_status !== PaymentMethodCatalog::STATUS_PAID
            && filled($membershipPurchase->paymongo_checkout_session_id)) {
            try {
                $session = $this->paymongo->retrieveCheckoutSession($membershipPurchase->paymongo_checkout_session_id);
                if ($this->paymongo->isCheckoutSessionPaid($session)
                    && $this->memberships->evidenceMatches($membershipPurchase, $session)) {
                    $membershipPurchase = $this->memberships->confirm(
                        $membershipPurchase,
                        $this->paymongo->extractPaidPaymentIdFromSession($session),
                        $this->paymongo->extractPaymentChannelFromSession($session),
                        (string) ($session['id'] ?? ''),
                    );
                }
            } catch (\Throwable $exception) {
                Log::warning('Membership success callback could not verify payment.', [
                    'membership_purchase_id' => $membershipPurchase->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $membershipPurchase->refresh();
        if ($membershipPurchase->payment_status !== PaymentMethodCatalog::STATUS_PAID) {
            return redirect()->to(route('landing').'#membership')
                ->withErrors(['membership' => 'Payment is still being verified. Your membership will activate after PayMongo confirms it.']);
        }

        ActivityLogger::log(
            'membership.activated',
            'Activated '.$membershipPurchase->plan_name,
            ['membership_purchase_id' => $membershipPurchase->id],
            request: $request,
        );

        return redirect()->to(route('landing').'#membership')
            ->with('membership_status', 'Membership activated. Your member rates are now available when booking.');
    }

    public function cancel(Request $request, MembershipPurchase $membershipPurchase): RedirectResponse
    {
        $this->authorizeOwner($request, $membershipPurchase);

        return redirect()->to(route('landing').'#membership')
            ->with('membership_status', 'Membership checkout was not completed. You can continue payment from the membership section.');
    }

    private function openCheckout(MembershipPurchase $purchase): RedirectResponse
    {
        if (filled($purchase->paymongo_checkout_session_id)) {
            try {
                $session = $this->paymongo->retrieveCheckoutSession($purchase->paymongo_checkout_session_id);
                if ($this->paymongo->isCheckoutSessionPaid($session)
                    && $this->memberships->evidenceMatches($purchase, $session)) {
                    $this->memberships->confirm(
                        $purchase,
                        $this->paymongo->extractPaidPaymentIdFromSession($session),
                        $this->paymongo->extractPaymentChannelFromSession($session),
                        (string) ($session['id'] ?? ''),
                    );

                    return redirect()->to(route('landing').'#membership')
                        ->with('membership_status', 'Membership payment verified successfully.');
                }

                $checkoutUrl = (string) ($session['attributes']['checkout_url'] ?? '');
                $sessionStatus = strtolower((string) ($session['attributes']['status'] ?? ''));
                if ($this->paymongo->checkoutSessionHasUsablePaymentMethods($session)
                    && $this->paymongo->isCheckoutUrl($checkoutUrl)
                    && ! in_array($sessionStatus, ['expired', 'cancelled', 'canceled'], true)) {
                    return redirect()->away($checkoutUrl);
                }
            } catch (\Throwable) {
                // Create a replacement checkout below.
            }
        }

        try {
            return redirect()->away($this->paymongo->startMembershipCheckout($purchase));
        } catch (\Throwable $exception) {
            Log::warning('Membership checkout could not be started.', [
                'membership_purchase_id' => $purchase->id,
                'message' => $exception->getMessage(),
            ]);

            return back()->withErrors(['membership' => 'Membership checkout could not be opened. Please try again.']);
        }
    }

    private function authorizeOwner(Request $request, MembershipPurchase $purchase): void
    {
        abort_unless($request->user()?->id === $purchase->user_id, 403);
    }
}
