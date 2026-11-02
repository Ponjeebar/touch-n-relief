<?php

use App\Models\SpaBooking;
use App\Services\BookingRescheduleService;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'pgsql' || config('database.connections.pgsql.database') !== 'touchnrelief_test') {
    throw new RuntimeException('Reschedule workers require the isolated PostgreSQL test database.');
}
[$script, $id, $target, $barrier] = $argv;
Carbon::setTestNow('2026-11-02 08:00:00');
$booking = SpaBooking::findOrFail($id);
touch($barrier.'.'.$id.'.ready');
$deadline = microtime(true) + 15;
while (! file_exists($barrier.'.go') && microtime(true) < $deadline) {
    usleep(10000);
}
if (! file_exists($barrier.'.go')) {
    throw new RuntimeException('Reschedule checkpoint timed out.');
}
try {
    $app->make(BookingRescheduleService::class)->reschedule($booking, '2026-11-05', '10:00 AM', $target);
    echo 'accepted';
} catch (ValidationException) {
    echo 'rejected';
}
