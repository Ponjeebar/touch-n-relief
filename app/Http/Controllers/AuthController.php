<?php

namespace App\Http\Controllers;

use App\Models\AuthVerificationCode;
use App\Models\User;
use App\Rules\NotRecentlyUsedPassword;
use App\Services\ActivityLogger;
use App\Services\AuthVerificationCodeService;
use App\Services\WalkInClientService;
use App\Support\MailDeliveryConfiguration;
use App\Support\StrongPassword;
use App\Support\WalkInSchema;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\QueryException;
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
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(
        private readonly WalkInClientService $walkInClients,
        private readonly AuthVerificationCodeService $verificationCodes,
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
            $user = User::query()->whereRaw('LOWER(email) = ?', [Str::lower($validated['email'])])->first();
            if (! $user || $user->isWalkIn() || $user->isArchived()) {
                return redirect()->route('password.request')->with('status', 'If that account exists, a verification code has been sent.');
            }

            $verification = $this->verificationCodes->issue(
                $user->email,
                AuthVerificationCode::PURPOSE_PASSWORD_RESET,
                ['user_id' => $user->id],
                $user->name,
            );
        } catch (\Throwable $exception) {
            Log::error('Password reset email could not be sent.', [
                'message' => $exception->getMessage(),
            ]);

            return redirect()->route('password.request')->withErrors([
                'email' => 'We could not send the verification code right now. Please try again later.',
            ])->withInput($request->only('email'));
        }

        return redirect()->route('verification.show', [
            'verification' => $verification->id,
            'purpose' => AuthVerificationCode::PURPOSE_PASSWORD_RESET,
        ]);
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
        $user = User::query()->whereRaw('LOWER(email) = ?', [Str::lower((string) $request->input('email'))])->first();
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => array_filter(['required', 'confirmed', StrongPassword::rule(), $user ? new NotRecentlyUsedPassword($user) : null]),
        ]);

        $status = PasswordBroker::reset(
            $validated,
            function (User $user, string $password): void {
                $user->passwordHistories()->create(['password' => $user->getAuthPassword()]);
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $user->passwordHistories()->latest()->skip(5)->take(100)->get()->each->delete();

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

        $rememberCustomer = $request->boolean('remember') && $user->isUser();

        Auth::login($user, $rememberCustomer);

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
            'password' => ['required', 'confirmed', StrongPassword::rule()],
            'terms_accepted' => ['accepted'],
        ], [
            'birthday.before_or_equal' => 'You must be at least 15 years old to register.',
            'contact_number.regex' => 'Phone number must be 11 digits starting with 09.',
            'terms_accepted.accepted' => 'You must agree to the Terms and Conditions and Privacy Policy to create an account.',
        ]);

        try {
            $verification = $this->verificationCodes->issue(
                $validated['email'],
                AuthVerificationCode::PURPOSE_REGISTRATION,
                [
                    'registration' => $validated,
                    'matched_walk_in_id' => $matchedWalkIn?->id,
                    'return_to' => $returnTo,
                    'intended' => $intendedBeforeRegister,
                ],
                $validated['name'],
            );
        } catch (\Throwable $exception) {
            Log::error('Registration verification code could not be sent.', ['message' => $exception->getMessage()]);

            return back()->withErrors([
                'email' => 'We could not send the verification code right now. Please try again later.',
            ], 'register')->withInput();
        }

        return redirect()->route('verification.show', [
            'verification' => $verification->id,
            'purpose' => AuthVerificationCode::PURPOSE_REGISTRATION,
        ]);
    }

    public function showVerification(AuthVerificationCode $verification, Request $request): View
    {
        abort_unless(hash_equals($verification->purpose, (string) $request->query('purpose')), 404);

        return view('auth.verify-code', ['verification' => $verification]);
    }

    public function verifyCode(Request $request, AuthVerificationCode $verification): RedirectResponse
    {
        $validated = $request->validate([
            'purpose' => ['required', Rule::in([AuthVerificationCode::PURPOSE_REGISTRATION, AuthVerificationCode::PURPOSE_PASSWORD_RESET])],
            'code' => ['required', 'digits:6'],
        ]);
        $verification = $this->verificationCodes->verify($verification->id, $validated['purpose'], $validated['code']);

        if ($verification->purpose === AuthVerificationCode::PURPOSE_PASSWORD_RESET) {
            $user = User::query()->findOrFail($verification->payload['user_id'] ?? null);
            $token = PasswordBroker::createToken($user);
            $verification->delete();

            return redirect()->route('password.reset', ['token' => $token, 'email' => $user->email]);
        }

        return $this->completeVerifiedRegistration($request, $verification);
    }

    public function resendCode(AuthVerificationCode $verification): RedirectResponse
    {
        if ($verification->last_sent_at->addSeconds(AuthVerificationCodeService::RESEND_SECONDS)->isFuture()) {
            return back()->withErrors(['code' => 'Please wait before requesting another code.']);
        }

        $payload = $verification->payload ?? [];
        $name = (string) ($payload['registration']['name'] ?? '');
        if ($verification->purpose === AuthVerificationCode::PURPOSE_PASSWORD_RESET) {
            $name = (string) User::query()->find($payload['user_id'] ?? null)?->name;
        }
        try {
            $replacement = $this->verificationCodes->issue($verification->email, $verification->purpose, $payload, $name);
        } catch (\Throwable $exception) {
            Log::error('Verification code could not be resent.', ['message' => $exception->getMessage()]);

            return back()->withErrors(['code' => 'We could not send a new code right now. Please try again later.']);
        }

        return redirect()->route('verification.show', [
            'verification' => $replacement->id,
            'purpose' => $replacement->purpose,
        ])->with('status', 'A new verification code has been sent.');
    }

    private function completeVerifiedRegistration(Request $request, AuthVerificationCode $verification): RedirectResponse
    {
        $payload = $verification->payload ?? [];
        $validated = (array) ($payload['registration'] ?? []);
        $matchedWalkIn = ! empty($payload['matched_walk_in_id'])
            ? User::query()->find($payload['matched_walk_in_id'])
            : null;

        Validator::make($validated, [
            'username' => ['required', Rule::unique('users', 'username')->ignore($matchedWalkIn?->id)],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($matchedWalkIn?->id)],
        ])->validate();

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
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (QueryException) {
            return back()
                ->withErrors(['email' => 'Unable to complete registration. Please try again.'], 'register')
                ->withInput();
        }

        $user->forceFill(['email_verified_at' => now()])->save();
        $verification->delete();

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

        $returnTo = (string) ($payload['return_to'] ?? '');
        $intendedBeforeRegister = (string) ($payload['intended'] ?? '');
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
