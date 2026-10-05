<?php

use App\Http\Controllers\StaffAppointmentController;
use App\Http\Controllers\SystemSettingsController;
use App\Models\MembershipPurchase;
use App\Models\RefundConfirmation;
use App\Models\SpaBooking;
use App\Models\User;
use App\Services\BookingCancellationService;
use App\Services\BookingSlotService;
use App\Services\MembershipPurchaseService;
use App\Services\NoShowService;
use App\Services\PaymongoService;
use App\Services\SiteSettingsService;
use App\Services\SpaSessionService;
use App\Support\PaymentMethodCatalog;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $action, $recordId, $actor, $barrier] = $argv;
$deadline = microtime(true) + 10;
while (! file_exists($barrier) && microtime(true) < $deadline) {
    usleep(10000);
}

if (! file_exists($barrier)) {
    fwrite(STDERR, 'Concurrency barrier timed out.');
    exit(3);
}

try {
    if ($action === 'webhook' || ($action === 'late-payment-race' && $actor === 'webhook')) {
        $booking = SpaBooking::query()->findOrFail((int) $recordId);
        $paymongo = Mockery::mock(PaymongoService::class);
        $paymongo->shouldReceive('resolvePaymentIdForBooking')->zeroOrMoreTimes()->andReturn('pay_pg_replay');
        $paymongo->shouldReceive('isConfigured')->zeroOrMoreTimes()->andReturnTrue();
        $paymongo->shouldReceive('verifyWebhookSignature')->once()->andReturnTrue();
        $paymongo->shouldReceive('resolvePaymentChannelForPaymentId')->zeroOrMoreTimes()->andReturn('gcash');
        $paymongo->shouldReceive('paymentSupportsApiRefund')->zeroOrMoreTimes()->andReturnTrue();
        $paymongo->shouldReceive('createRefund')->zeroOrMoreTimes()->andReturn([
            'id' => 'ref_pg_late_payment',
            'attributes' => ['status' => 'succeeded'],
        ]);
        $app->instance(PaymongoService::class, $paymongo);
        $payload = json_encode(['data' => ['attributes' => [
            'type' => 'payment.paid',
            'data' => [
                'id' => 'pay_pg_replay',
                'type' => 'payment',
                'attributes' => [
                    'status' => 'paid',
                    'amount' => (int) round((float) $booking->payment_amount * 100),
                    'currency' => 'PHP',
                    'metadata' => ['booking_id' => (string) $booking->id],
                ],
            ],
        ]]]) ?: '{}';
        $request = Request::create('/webhooks/paymongo', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => 'valid',
        ], $payload);
        $response = $app->make(Illuminate\Contracts\Http\Kernel::class)->handle($request);
        echo $response->getStatusCode() === 200 ? 'accepted' : 'failed';
    } elseif ($action === 'late-payment-race') {
        $booking = SpaBooking::query()->findOrFail((int) $recordId);
        $userId = (int) str_replace('booking-', '', $actor);
        DB::transaction(function () use ($booking, $userId): void {
            $slots = app(BookingSlotService::class);
            $slots->assertBookingAvailable(
                $userId,
                (string) $booking->service_name,
                (string) $booking->therapist_name,
                $booking->booking_date->format('Y-m-d'),
                (string) $booking->time_slot,
                (int) $booking->duration_minutes,
                therapistNames: [(string) $booking->therapist_name],
                withTherapistLock: true,
            );
            $customer = User::query()->findOrFail($userId);
            SpaBooking::query()->create([
                'user_id' => $customer->id,
                'client_name' => $customer->name,
                'booking_source' => SpaBooking::SOURCE_ONLINE,
                'service_name' => $booking->service_name,
                'therapist_name' => $booking->therapist_name,
                'booking_date' => $booking->booking_date,
                'time_slot' => $booking->time_slot,
                'duration_minutes' => $booking->duration_minutes,
                'amount' => $booking->amount,
                'payment_amount' => $booking->amount,
                'payment_method' => PaymentMethodCatalog::METHOD_CASH_COUNTER,
                'payment_status' => PaymentMethodCatalog::STATUS_PAID,
                'session_status' => SpaBooking::STATUS_CONFIRMED,
            ]);
        });
        echo 'booked';
    } elseif ($action === 'no-show') {
        $booking = SpaBooking::query()->findOrFail((int) $recordId);
        $result = $actor === 'automatic'
            ? app(NoShowService::class)->processOverdue()
            : app(NoShowService::class)->record($booking, 0);
        echo ($actor === 'automatic' ? $result > 0 : $result !== null) ? 'marked' : 'stale';
    } elseif ($action === 'completion') {
        $booking = SpaBooking::query()->findOrFail((int) $recordId);
        if ($actor === 'automatic') {
            app(SpaSessionService::class)->autoCompleteIfExpired($booking, now());
        } else {
            app(SpaSessionService::class)->complete($booking);
        }
        echo 'completed';
    } elseif ($action === 'membership') {
        app(MembershipPurchaseService::class)->confirm(
            MembershipPurchase::query()->findOrFail((int) $recordId),
            'pay_pg_membership_'.$actor,
            'gcash',
            'cs_pg_membership_'.$actor,
        );
        echo 'confirmed';
    } elseif ($action === 'refund-dispute') {
        [$staffId, $version] = explode(':', $actor);
        $staff = User::query()->findOrFail((int) $staffId);
        Auth::setUser($staff);
        $request = Request::create('/refund-confirmations/'.$recordId.'/dispute', 'PATCH', [
            'action' => 'resolve', 'note' => 'Verified the cash acknowledgment.', 'dispute_version' => $version,
        ]);
        $request->setUserResolver(fn (): User => $staff);
        $app->make(StaffAppointmentController::class)->refundDispute($request, RefundConfirmation::query()->findOrFail((int) $recordId));
        echo 'resolved';
    } elseif ($action === 'settings') {
        $profiles = [
            'a' => [10, 40, 2],
            'b' => [20, 60, 4],
        ];
        [$hold, $lead, $threshold] = $profiles[$actor];
        $request = Request::create('/system-settings', 'PUT', [
            'cancellation_cutoff_hours' => 24,
            'payment_hold_minutes' => $hold,
            'customer_minimum_lead_minutes' => $lead,
            'late_grace_minutes' => 10,
            'no_show_review_minutes' => 5,
            'no_show_restriction_threshold' => $threshold,
            'expired_hold_limit' => 3,
            'expired_hold_lookback_hours' => 24,
            'expired_hold_cooldown_minutes' => 60,
            'backup_retention_days' => 14,
        ]);
        $admin = User::query()->findOrFail((int) $recordId);
        Auth::setUser($admin);
        $request->setUserResolver(fn (): User => $admin);
        $request->setLaravelSession($app->make('session')->driver());
        $app->make(SystemSettingsController::class)->update(
            $request,
            app(SiteSettingsService::class),
            app(NoShowService::class),
        );
        echo 'saved';
    } elseif ($action === 'cancel-start') {
        if ($actor === 'cancel') {
            app(BookingCancellationService::class)->cancel(
                SpaBooking::query()->findOrFail((int) $recordId),
                'schedule_conflict',
            );
            echo 'cancelled';
        } else {
            DB::transaction(function () use ($recordId): void {
                $booking = SpaBooking::query()->lockForUpdate()->findOrFail((int) $recordId);
                if ($booking->cancelled_at !== null) {
                    throw ValidationException::withMessages(['booking' => 'stale']);
                }
                $booking->forceFill([
                    'session_status' => SpaBooking::STATUS_IN_SESSION,
                    'session_started_at' => now(),
                ])->save();
            });
            echo 'started';
        }
    }
} catch (ValidationException) {
    echo 'stale';
}
