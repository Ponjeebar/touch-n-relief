<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureCurrentStaffSession;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsCustomer;
use App\Http\Middleware\EnsureUserIsStaff;
use App\Http\Middleware\LogStaffActivity;
use App\Http\Middleware\UseCanonicalHost;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);
        $middleware->prepend(UseCanonicalHost::class);
        $middleware->append(AddSecurityHeaders::class);

        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_PROTO);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'customer' => EnsureUserIsCustomer::class,
            'current.staff.session' => EnsureCurrentStaffSession::class,
            'staff' => EnsureUserIsStaff::class,
            'staff.activity' => LogStaffActivity::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/paymongo',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response): Response {
            $requestId = request()->attributes->get('request_id');
            if (is_string($requestId) && $requestId !== '') {
                $response->headers->set('X-Request-ID', $requestId);
            }

            return $response;
        });
    })->create();
