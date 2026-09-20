<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogStaffActivity
{
    /** Routes that should not generate a page-view log entry. */
    private const SKIP_ROUTES = [
        'reporting.data',
        'activity-logs.index',
        'activity-logs.click',
    ];

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethod('GET') || ! $request->route()?->getName()) {
            return $response;
        }

        $routeName = $request->route()->getName();

        if (in_array($routeName, self::SKIP_ROUTES, true)) {
            return $response;
        }

        $user = $request->user();
        if ($user === null || (! $user->isAdmin() && ! $user->isReceptionist())) {
            return $response;
        }

        if (! $response->isSuccessful()) {
            return $response;
        }

        $page = ActivityLogger::pageLabel($routeName);

        ActivityLogger::log(
            ActivityLogger::actionForRoute($routeName),
            'Viewed '.$page,
            ['route' => $routeName, 'url' => $request->path()],
            request: $request,
        );

        return $response;
    }
}
