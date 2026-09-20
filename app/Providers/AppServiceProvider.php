<?php

namespace App\Providers;

use App\Services\UserActivityService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
