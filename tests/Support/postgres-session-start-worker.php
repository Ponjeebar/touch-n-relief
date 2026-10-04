<?php

use App\Http\Controllers\DashboardController;
use App\Models\SpaBooking;
use App\Models\User;
use App\Services\PaymentLedgerService;
use App\Services\SpaSessionService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $staffId, $bookingId, $barrier] = $argv;
$deadline = microtime(true) + 10;
while (! file_exists($barrier) && microtime(true) < $deadline) {
    usleep(10000);
}

if (! file_exists($barrier)) {
    fwrite(STDERR, 'Concurrency barrier timed out.');
    exit(3);
}

$staff = User::query()->findOrFail((int) $staffId);
$booking = SpaBooking::query()->findOrFail((int) $bookingId);
$request = Request::create('/appointments/'.$booking->id.'/start', 'PATCH', [
    'arrival_confirmed' => '1',
    'amount_tendered' => '50.00',
    'payment_reference' => 'PG-CONCURRENT',
]);
$request->setUserResolver(fn (): User => $staff);
$request->setLaravelSession(app('session')->driver());

app(DashboardController::class)->startSession(
    $request,
    $booking,
    app(SpaSessionService::class),
    app(PaymentLedgerService::class),
);

$winner = SpaBooking::query()->findOrFail((int) $bookingId)->balance_collected_by;
echo (int) $winner === (int) $staffId ? 'started' : 'stale';
