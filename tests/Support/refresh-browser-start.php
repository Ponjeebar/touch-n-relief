<?php

use App\Models\SpaBooking;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';
putenv('APP_ENV=e2e');
$_ENV['APP_ENV'] = 'e2e';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$expected = realpath(base_path('database/browser-tests.sqlite'));
$actual = realpath((string) config('database.connections.sqlite.database'));
if (! $app->environment('e2e') || config('database.default') !== 'sqlite' || ! $expected || $actual !== $expected) {
    throw new RuntimeException('Browser fixture refresh requires the isolated browser database.');
}

$booking = SpaBooking::where('payment_transaction_id', 'pay_e2e_partial_start')->sole();
$booking->forceFill([
    'booking_date' => now()->toDateString(),
    'time_slot' => now()->format('g:i A'),
    'session_status' => SpaBooking::STATUS_CONFIRMED,
    'session_started_at' => null,
    'completed_at' => null,
    'cancelled_at' => null,
])->save();
