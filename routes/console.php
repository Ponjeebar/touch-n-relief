<?php

use App\Models\AuthVerificationCode;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\SpaBooking;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('clients:archive-inactive', function () {
    $months = max((int) config('touchnrelief.client_auto_archive_months', 24), 1);
    $cutoff = now()->subMonths($months)->startOfDay();
    $archived = 0;

    Customer::query()->active()->registered()->orderBy('id')->chunkById(100, function ($customers) use ($cutoff, $months, &$archived): void {
        foreach ($customers as $customer) {
            $user = User::query()->whereRaw('LOWER(email) = ?', [strtolower(trim((string) $customer->email))])->first();
            $lastCompleted = SpaBooking::query()
                ->where(function ($query) use ($user, $customer): void {
                    if ($user !== null) {
                        $query->where('user_id', $user->id)
                            ->orWhereRaw('LOWER(TRIM(client_name)) = ?', [strtolower(trim((string) $customer->full_name))]);
                    } else {
                        $query->whereRaw('LOWER(TRIM(client_name)) = ?', [strtolower(trim((string) $customer->full_name))]);
                    }
                })
                ->where(function ($query): void {
                    $query->whereNotNull('completed_at')->orWhere('session_status', SpaBooking::STATUS_COMPLETED);
                })
                ->max('booking_date');
            $referenceDate = $lastCompleted ? \Carbon\Carbon::parse((string) $lastCompleted)->startOfDay() : $customer->created_at?->copy()->startOfDay();
            if ($referenceDate === null || $referenceDate->gt($cutoff)) {
                continue;
            }

            $hasUpcomingBooking = SpaBooking::query()
                ->when($user !== null, fn ($query) => $query->where('user_id', $user->id), fn ($query) => $query->whereRaw('LOWER(TRIM(client_name)) = ?', [strtolower(trim((string) $customer->full_name))]))
                ->whereDate('booking_date', '>=', now()->toDateString())
                ->whereNull('cancelled_at')
                ->exists();
            if ($hasUpcomingBooking) {
                continue;
            }

            $customer->archive();
            $user?->archive();
            ActivityLog::query()->create([
                'user_id' => null,
                'user_role' => 'system',
                'user_name' => 'System',
                'action' => 'customer.archived',
                'subject_type' => $customer->getMorphClass(),
                'subject_id' => $customer->getKey(),
                'description' => 'Automatically archived customer '.$customer->full_name,
                'properties' => ['reason' => 'No completed appointment within the configured '.$months.' month retention period'],
                'created_at' => now(),
            ]);
            $archived++;
        }
    });

    $this->info("Archived {$archived} inactive client record(s).");
})->purpose('Soft archive clients inactive beyond the configured retention period');

Schedule::command('appointments:send-reminders')->everyFiveMinutes();
Schedule::command('clients:archive-inactive')->dailyAt('02:15')->withoutOverlapping();
Schedule::call(fn () => AuthVerificationCode::query()->where('expires_at', '<', now())->delete())
    ->hourly()
    ->name('prune-expired-auth-verification-codes')
    ->withoutOverlapping();
