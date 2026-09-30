<?php

namespace Tests\Integration;

use App\Models\Therapist;
use App\Models\User;
use Database\Seeders\SiteSettingsSeeder;
use Database\Seeders\SpaServiceSeeder;
use Database\Seeders\SpaTimeSlotSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PostgresBookingConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('This test requires PostgreSQL.');
        }

        if (! str_contains((string) config('database.connections.pgsql.database'), 'test')) {
            $this->fail('The PostgreSQL concurrency test must only run against a test database.');
        }

        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->seed([
            SiteSettingsSeeder::class,
            SpaServiceSeeder::class,
            SpaTimeSlotSeeder::class,
        ]);
    }

    public function test_two_simultaneous_requests_cannot_claim_the_same_therapist_slot(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $therapist = Therapist::query()->create([
            'therapist_code' => 'PG-LOCK-1',
            'name' => 'Concurrency Therapist',
            'status' => 'available',
            'work_on_off_day' => true,
            'is_active' => true,
        ]);
        $bookingDate = now()->addDays(7)->toDateString();
        $barrier = storage_path('framework/testing/concurrency-'.bin2hex(random_bytes(8)));

        $environment = array_filter(array_merge($_SERVER, $_ENV, [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => (string) config('database.connections.pgsql.host'),
            'DB_PORT' => (string) config('database.connections.pgsql.port'),
            'DB_DATABASE' => (string) config('database.connections.pgsql.database'),
            'DB_USERNAME' => (string) config('database.connections.pgsql.username'),
            'DB_PASSWORD' => (string) config('database.connections.pgsql.password'),
        ]), static fn (mixed $value): bool => is_scalar($value));
        $worker = base_path('tests/Support/postgres-booking-worker.php');
        $phpCommand = [PHP_BINARY];
        if (PHP_OS_FAMILY === 'Windows') {
            array_push($phpCommand, '-d', 'extension=pdo_pgsql');
        }
        $processes = collect([$firstUser, $secondUser])->map(fn (User $user): Process => new Process(array_merge($phpCommand, [
            $worker,
            (string) $user->id,
            $therapist->name,
            $bookingDate,
            $barrier,
        ]), base_path(), $environment, null, 30))->all();

        try {
            foreach ($processes as $process) {
                $process->start();
            }
            usleep(250000);
            touch($barrier);
            foreach ($processes as $process) {
                $process->wait();
            }

            $results = collect($processes)->map(fn (Process $process): string => trim($process->getOutput()))->sort()->values()->all();
            $this->assertSame(['created', 'unavailable'], $results, collect($processes)->map(fn (Process $process): string => $process->getErrorOutput())->implode("\n"));
            $this->assertSame(1, DB::table('spa_bookings')
                ->where('therapist_name', $therapist->name)
                ->whereDate('booking_date', $bookingDate)
                ->where('time_slot', '10:00 AM')
                ->count());
        } finally {
            @unlink($barrier);
        }
    }
}
