<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogger;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCurrentStaffSession
{
    public const SESSION_KEY = 'staff_session_token';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || (! $user->isAdmin() && ! $user->isReceptionist())) {
            return $next($request);
        }

        if ($user->isArchived()) {
            return $this->endSession(
                $request,
                'Your staff account has been archived. Please contact the administrator.',
                'staff.session_archived',
                'Rejected a session for an archived staff account',
            );
        }

        $expectedToken = (string) ($user->staff_session_token ?? '');
        $sessionToken = (string) $request->session()->get(self::SESSION_KEY, '');

        // Sessions created before this feature was deployed remain valid until the next staff login.
        if ($expectedToken === '' && $sessionToken === '') {
            return $next($request);
        }

        if ($expectedToken !== '' && $sessionToken !== '' && hash_equals($expectedToken, $sessionToken)) {
            return $next($request);
        }

        return $this->endSession(
            $request,
            'This staff account was signed in on another device. Sign in again to continue.',
            'staff.session_rejected',
            'Ended a staff session replaced by a newer login',
        );
    }

    private function endSession(
        Request $request,
        string $message,
        string $action,
        string $description,
    ): RedirectResponse {
        ActivityLogger::log($action, $description, request: $request);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['login' => $message]);
    }
}
