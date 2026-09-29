<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isUser()) {
            return $next($request);
        }

        if ($user?->isAdmin()) {
            return redirect()->route('dashboard');
        }

        if ($user?->isReceptionist()) {
            return redirect()->route('receptionist.dashboard');
        }

        abort(403);
    }
}
