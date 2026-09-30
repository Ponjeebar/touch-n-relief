<?php

use App\Models\SpaBooking;
use App\Models\User;
use App\Services\BookingSlotService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $userId, $therapistName, $bookingDate, $barrier] = $argv;
$deadline = microtime(true) + 10;
while (! file_exists($barrier) && microtime(true) < $deadline) {
    usleep(10000);
}

if (! file_exists($barrier)) {
    fwrite(STDERR, 'Concurrency barrier timed out.');
    exit(3);
}

try {
    DB::transaction(function () use ($userId, $therapistName, $bookingDate): void {
        $slots = app(BookingSlotService::class);
        $slots->assertBookingAvailable(
            (int) $userId,
            'Foot Reflexology',
            $therapistName,
            $bookingDate,
            '10:00 AM',
            45,
            therapistNames: [$therapistName],
            withTherapistLock: true,
        );
        usleep(350000);
        $user = User::query()->findOrFail((int) $userId);
        SpaBooking::query()->create([
            'user_id' => $user->id,
            'client_name' => $user->name,
            'service_name' => 'Foot Reflexology',
            'therapist_name' => $therapistName,
            'booking_date' => $bookingDate,
            'time_slot' => '10:00 AM',
            'duration_minutes' => 45,
            'amount' => 70,
            'session_status' => SpaBooking::STATUS_CONFIRMED,
        ]);
    });
    echo 'created';
} catch (ValidationException) {
    echo 'unavailable';
}
