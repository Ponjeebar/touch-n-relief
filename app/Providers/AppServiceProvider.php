<?php

namespace App\Providers;

use App\Models\SpaBooking;
use App\Services\StaffNotificationService;
use App\Services\UserActivityService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request): Limit {
            $login = Str::lower(trim((string) $request->input('login')));

            return Limit::perMinute(5)->by(Str::transliterate($login).'|'.$request->ip());
        });

        SpaBooking::created(fn (SpaBooking $booking) => app(StaffNotificationService::class)->bookingCreated($booking));
        SpaBooking::updated(fn (SpaBooking $booking) => app(StaffNotificationService::class)->bookingUpdated($booking));

        View::composer([
            'partials.topbar-profile',
            'partials.profile-transactions-modal',
            'partials.profile-transactions-list',
        ], function ($view): void {
            $user = Auth::user();
            $view->with(
                'userTransactions',
                $user ? app(UserActivityService::class)->transactionsForUser($user) : []
            );
        });
    }
}
