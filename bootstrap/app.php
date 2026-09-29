<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsCustomer;
use App\Http\Middleware\EnsureUserIsStaff;
use App\Http\Middleware\LogStaffActivity;
use App\Http\Middleware\UseCanonicalHost;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(UseCanonicalHost::class);

        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_PROTO);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'customer' => EnsureUserIsCustomer::class,
            'staff' => EnsureUserIsStaff::class,
            'staff.activity' => LogStaffActivity::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/paymongo',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
