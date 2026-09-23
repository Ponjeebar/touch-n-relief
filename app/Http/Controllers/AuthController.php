<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\MailDeliveryConfiguration;
use App\Services\ActivityLogger;
use App\Services\WalkInClientService;
use App\Support\WalkInSchema;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(
        private readonly WalkInClientService $walkInClients,
    ) {}

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function showForgotPassword(): View
    {
        return view('auth.login');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        if (! MailDeliveryConfiguration::isReady() && ! app()->environment('testing')) {
            return redirect()->route('password.request')->withErrors([
                'email' => 'Email delivery is not configured. Please contact the spa administrator.',
            ])->withInput($request->only('email'));
        }

        try {
            $status = PasswordBroker::sendResetLink([
                'email' => $validated['email'],
            ]);
        } catch (\Throwable $exception) {
            Log::error('Password reset email could not be sent.', [
                'message' => $exception->getMessage(),
            ]);

            return redirect()->route('password.request')->withErrors([
                'email' => 'We could not send the reset link right now. Please try again later.',
            ])->withInput($request->only('email'));
        }

        if ($status === PasswordBroker::RESET_LINK_SENT) {
            return redirect()->route('password.request')->with('status', __($status));
        }

        return redirect()->route('password.request')->withErrors([
            'email' => __($status),
        ])->withInput($request->only('email'));
    }

    public function showResetPassword(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $status = PasswordBroker::reset(
            $validated,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === PasswordBroker::PASSWORD_RESET) {
            if (Auth::check()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return redirect()->route('login')->with('status', __($status));
        }

        return back()->withErrors([
            'email' => [__($status)],
        ])->withInput($request->only('email'));
    }

    public function login(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return redirect()->route('login')
                ->withErrors($validator)
                ->onlyInput('login', 'remember');
        }

        $credentials = $validator->validated();

        $identifier = trim($credentials['login']);
        $password = $credentials['password'];
        $remember = $request->boolean('remember');

        $user = $this->findUserForLogin($identifier);

        if ($user instanceof User && $user->isWalkIn()) {
            return redirect()->route('login')->withErrors([
                'login' => 'This walk-in account is not finished yet. Open Sign Up, enter the same name and phone number used at the spa, and complete registration to set your password.',
            ])->onlyInput('login', 'remember')->with('open_register_tab', true);
        }

        if ($user instanceof User && $user->isArchived()) {
            return redirect()->route('login')->withErrors([
                'login' => 'This account has been archived. Please contact the spa administrator.',
            ])->onlyInput('login', 'remember');
        }


        if ($user instanceof User && $user->isBanned()) {
            return redirect()->route('login')->withErrors([
                'login' => 'This account has been banned after three no-show appointments. Please contact the spa administrator.',
            ])->onlyInput('login', 'remember');
        }

        if ($user === null || ! Hash::check($password, $user->getAuthPassword())) {
            return redirect()->route('login')->withErrors([
                'login' => 'Invalid username, name, email, or password.',
            ])->onlyInput('login', 'remember');
        }

        Auth::login($user, $remember);

        $request->session()->regenerate();

        ActivityLogger::log(
            'login',
            'Signed in to the system',
            ['email' => $user->email],
            user: $user,
            request: $request,
        );

        return redirect()->intended($this->homeRouteFor($user));
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof User) {
            ActivityLogger::log(
                'logout',
                'Signed out of the system',
                user: $user,
                request: $request,
            );
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function register(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('users')) {
            return back()
                ->withErrors(['email' => 'Registration is unavailable right now. Please try again later.'], 'register')
                ->withInput();
        }

        $minimumBirthday = now()->subYears(15)->toDateString();
        $returnTo = trim((string) $request->input('return_to', ''));
        $intendedBeforeRegister = trim((string) $request->session()->get('url.intended', ''));

        $lookup = $request->validateWithBag('register', [
            'name' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'regex:/^09\d{9}$/'],
        ], [
            'contact_number.regex' => 'Phone number must be 11 digits starting with 09.',
        ]);

        $matchedWalkIn = $this->walkInClients->findWalkInForRegistration(
            (string) $request->input('email', ''),
            $lookup['contact_number'],
            $lookup['name'],
        );

        $validated = $request->validateWithBag('register', [
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:30',
                Rule::unique('users', 'username')->ignore($matchedWalkIn?->id),
                'alpha_dash:ascii',
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($matchedWalkIn?->id),
            ],
            'contact_number' => ['required', 'regex:/^09\d{9}$/'],
            'birthday' => ['required', 'date', 'before_or_equal:'.$minimumBirthday],
            'sex' => ['required', Rule::in(User::sexOptions())],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'birthday.before_or_equal' => 'You must be at least 15 years old to register.',
            'contact_number.regex' => 'Phone number must be 11 digits starting with 09.',
        ]);

        try {
            $user = DB::transaction(function () use ($validated, $matchedWalkIn) {
                if ($matchedWalkIn instanceof User) {
                    return $this->walkInClients->upgradeToRegisteredAccount($matchedWalkIn, $validated);
                }

                $user = User::create(WalkInSchema::markRegistered([
                    'name' => $validated['name'],
                    'username' => $validated['username'],
                    'email' => $validated['email'],
                    'contact_number' => $validated['contact_number'],
                    'sex' => $validated['sex'],
                    'password' => $validated['password'],
                    'role' => User::ROLE_USER,
                ]));

                if (Schema::hasColumn('users', 'birthday')) {
                    $user->update(['birthday' => $validated['birthday']]);
                }

                if (Schema::hasTable('registrations')) {
                    $user->registration()->create([
                        'name' => $validated['name'],
                        'username' => $validated['username'],
                        'email' => $validated['email'],
                        'contact_number' => $validated['contact_number'],
                    ]);
                }

                $this->walkInClients->ensureRegisteredCustomerFromSignup($user->fresh(), $validated);

                return $user->fresh();
            });
        } catch (\Illuminate\Validation\ValidationException $exception) {
            throw $exception;
        } catch (\Illuminate\Database\QueryException) {
            return back()
                ->withErrors(['email' => 'Unable to complete registration. Please try again.'], 'register')
                ->withInput();
        }

        Auth::login($user);
        $request->session()->regenerate();

        ActivityLogger::log(
            $matchedWalkIn instanceof User ? 'register.walk_in_upgrade' : 'register',
            $matchedWalkIn instanceof User
                ? 'Registered account linked to previous walk-in visits'
                : 'Created a new account',
            ['email' => $user->email],
            user: $user,
            request: $request,
        );

        $target = $returnTo !== '' ? $returnTo : $intendedBeforeRegister;
        if ($this->isSafeReturnTo($request, $target)) {
            return redirect()->to($target);
        }

        return redirect()->intended($this->homeRouteFor($user));
    }

    private function isSafeReturnTo(Request $request, string $url): bool
    {
        if ($url === '') {
            return false;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//') && ! str_contains($url, '\\')) {
            return true;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host'])) {
            return false;
        }

        $targetHost = strtolower((string) ($parts['host'] ?? ''));
        $targetPort = (int) ($parts['port'] ?? 0);

        $requestHost = strtolower((string) $request->getHost());
        $requestPort = (int) $request->getPort();

        return $targetHost === $requestHost
            && ($targetPort === 0 || $targetPort === $requestPort);
    }

    protected function homeRouteFor(User $user): string
    {
        if ($user->isAdmin()) {
            return route('dashboard');
        }

        if ($user->isReceptionist()) {
            return route('receptionist.dashboard');
        }

        return route('landing');
    }

    /**
     * Resolve the user by email (case-insensitive), username (case-insensitive), or display name (case-insensitive).
     */
    protected function findUserForLogin(string $identifier): ?User
    {
        if ($identifier === '') {
            return null;
        }

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return User::query()
                ->active()
                ->whereRaw('LOWER(email) = ?', [Str::lower($identifier)])
                ->first();
        }

        $lower = Str::lower($identifier);

        return User::query()
            ->active()
            ->where(function ($q) use ($lower) {
                $q->whereRaw('LOWER(username) = ?', [$lower])
                    ->orWhereRaw('LOWER(name) = ?', [$lower]);
            })
            ->first();
    }
}
