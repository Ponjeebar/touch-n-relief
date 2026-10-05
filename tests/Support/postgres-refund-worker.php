<?php

use App\Models\SpaBooking;
use App\Services\BookingRefundService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

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

try {
    app(BookingRefundService::class)->completeManualRefund(
        SpaBooking::query()->findOrFail((int) $bookingId),
        'PostgreSQL concurrent refund test.',
        (int) $staffId,
        ['method' => 'cash', 'recipient' => 'Concurrency test customer', 'confirmed' => true, 'expected_amount' => 50, 'evidence' => UploadedFile::fake()->createWithContent('acknowledgment.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF")],
    );
    echo 'processed';
} catch (ValidationException) {
    echo 'stale';
}
