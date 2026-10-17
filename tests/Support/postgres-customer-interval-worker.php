<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\StaffAppointmentController;
use App\Models\SpaBooking;
use App\Models\Therapist;
use App\Models\User;
use App\Services\BookingRescheduleService;
use App\Services\PaymentLedgerService;
use App\Services\SpaSessionService;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'pgsql' || config('database.connections.pgsql.database') !== 'touchnrelief_test') {
    throw new RuntimeException('Customer interval workers require the isolated PostgreSQL test database.');
}
[$script, $action, $bookingId, $otherId, $staffId, $ready, $go, $entered] = $argv;
Carbon::setTestNow(str_contains($action, 'reschedule') ? '2026-10-19 10:00:00' : '2026-10-19 13:09:00');

function pauseAfterIntervalCheck(): void
{
    file_put_contents($GLOBALS['ready'], 'checked');
    $deadline = microtime(true) + 15;
    while (! file_exists($GLOBALS['go']) && microtime(true) < $deadline) {
        usleep(10000);
    }
    if (! file_exists($GLOBALS['go'])) {
        throw new RuntimeException('Customer interval checkpoint timed out.');
    }
}

class PausedAuditSession extends SpaSessionService
{
    public function canConfirmPaymentAndStart(SpaBooking $booking, ?Carbon $now = null): bool
    {
        $eligible = parent::canConfirmPaymentAndStart($booking, $now);
        pauseAfterIntervalCheck();

        return $eligible;
    }
}

class PausedAuditReschedule extends BookingRescheduleService
{
    public function assertSlotAvailable(SpaBooking $booking, string $bookingDate, string $timeSlot, bool $withTherapistLock = false, ?string $serviceName = null, ?string $therapistName = null, bool $forStaff = false): void
    {
        parent::assertSlotAvailable($booking, $bookingDate, $timeSlot, $withTherapistLock, $serviceName, $therapistName, $forStaff);
        if ($withTherapistLock) {
            pauseAfterIntervalCheck();
        }
    }
}

$staff = User::findOrFail((int) $staffId);
Auth::setUser($staff);
$booking = SpaBooking::findOrFail((int) $bookingId);
if ($action === 'paused-cancel') {
    SpaBooking::retrieved(function (SpaBooking $retrieved) use ($bookingId): void {
        if ($retrieved->id === (int) $bookingId && DB::transactionLevel() > 0 && empty($GLOBALS['cancel_checkpoint_reached'])) {
            $GLOBALS['cancel_checkpoint_reached'] = true;
            pauseAfterIntervalCheck();
        }
    });
}
try {
    if (in_array($action, ['paused-start', 'plain-start', 'paused-cancel'], true)) {
        $request = Request::create('/appointments/'.$booking->id.'/start', 'PATCH', ['arrival_confirmed' => '1']);
    } elseif ($action === 'new-booking') {
        $client = $booking->user;
        $request = Request::create('/appointments', 'POST', [
            'client_type' => 'registered', 'client_user_id' => $client->id,
            'client_name' => $client->name, 'client_email' => $client->email, 'client_phone' => $client->contact_number,
            'service' => 'Swedish Massage', 'therapist' => Therapist::findOrFail((int) $otherId)->name,
            'booking_mode' => 'scheduled', 'booking_date' => '2026-10-19', 'time_slot' => '2:00 PM',
            'payment_method' => 'cash_counter', 'payment_type' => 'full', 'counter_amount_tendered' => 1000,
        ]);
    } else {
        if ($action === 'paused-reschedule') {
            $app->instance(BookingRescheduleService::class, $app->make(PausedAuditReschedule::class));
        } else {
            $booking = SpaBooking::findOrFail((int) $otherId);
        }
        $request = Request::create('/appointments/'.$booking->id.'/reschedule', 'PATCH', ['booking_date' => '2026-10-19', 'time_slot' => '2:00 PM']);
    }
    $request->setUserResolver(fn () => $staff);
    $request->setLaravelSession($app->make('session')->driver());
    if (! str_starts_with($action, 'paused-')) {
        touch($entered);
    }
    $response = match ($action) {
        'paused-start' => $app->make(DashboardController::class)->startSession($request, $booking, new PausedAuditSession, $app->make(PaymentLedgerService::class)),
        'plain-start' => $app->make(DashboardController::class)->startSession($request, $booking, $app->make(SpaSessionService::class), $app->make(PaymentLedgerService::class)),
        'paused-cancel' => $app->make(StaffAppointmentController::class)->cancel($request, $booking),
        'new-booking' => $app->make(StaffAppointmentController::class)->store($request),
        default => $app->make(StaffAppointmentController::class)->reschedule($request, $booking),
    };
    echo $response->getSession()->has('errors') ? 'rejected' : 'accepted';
} catch (ValidationException) {
    echo 'rejected';
}
