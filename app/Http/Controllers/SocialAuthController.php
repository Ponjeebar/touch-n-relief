<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\WalkInClientService;
use App\Support\WalkInSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as ProviderUser;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class SocialAuthController extends Controller
{
    private const PROVIDERS = ['google', 'facebook'];

    private const PENDING_SESSION_KEY = 'social_auth.pending';

    private const INTENT_SESSION_KEY = 'social_auth.intent';

    public function __construct(private readonly WalkInClientService $walkInClients) {}

    public function redirect(Request $request, string $provider): RedirectResponse|SymfonyRedirectResponse
    {
        if (! $this->isSupportedProvider($provider) || ! $this->isConfigured($provider)) {
            return redirect()->route('login')->withErrors([
                'social' => ucfirst($provider).' login is not configured yet.',
            ]);
        }

        $intent = in_array($request->query('intent'), ['login', 'signup'], true)
            ? $request->query('intent')
            : 'login';
        $request->session()->put(self::INTENT_SESSION_KEY, $intent);

        return Socialite::driver($provider)->scopes(['email'])->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        if (! $this->isSupportedProvider($provider) || ! $this->isConfigured($provider)) {
            return redirect()->route('login')->withErrors(['social' => 'That social login provider is unavailable.']);
        }

        $intent = $request->session()->pull(self::INTENT_SESSION_KEY, 'login');

        try {
            /** @var ProviderUser $providerUser */
            $providerUser = Socialite::driver($provider)->user();
        } catch (\Throwable $exception) {
            Log::warning('Social authentication callback failed.', [
                'provider' => $provider,
                'message' => $exception->getMessage(),
            ]);

            return redirect()->route('login')->withErrors([
                'social' => 'We could not verify your '.ucfirst($provider).' account. Please try again.',
            ]);
        }

        $providerId = trim((string) $providerUser->getId());
        $email = Str::lower(trim((string) $providerUser->getEmail()));

        if ($providerId === '' || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->route('login')->withErrors([
                'social' => ucfirst($provider).' did not provide an email address. Use another account or sign up with email.',
            ]);
        }

        if ($provider === 'google' && ! filter_var($providerUser->user['email_verified'] ?? false, FILTER_VALIDATE_BOOL)) {
            return redirect()->route('login')->withErrors([
                'social' => 'Your Google email address must be verified before you can continue.',
            ]);
        }

        $socialAccount = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_user_id', $providerId)
            ->with('user')
            ->first();

        if ($socialAccount instanceof SocialAccount) {
            if ($intent === 'signup') {
                return $this->existingAccountResponse($provider);
            }

            return $this->loginCustomer($request, $socialAccount->user, $provider);
        }

        $existingUser = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($existingUser instanceof User) {
            if (! $this->canUseSocialLogin($existingUser)) {
                return redirect()->route('login')->withErrors([
                    'social' => 'Social login is available only for active customer accounts.',
                ]);
            }

            if ($intent === 'signup') {
                return $this->existingAccountResponse($provider);
            }

            $providerLink = $existingUser->socialAccounts()->where('provider', $provider)->first();
            if ($providerLink instanceof SocialAccount && $providerLink->provider_user_id !== $providerId) {
                return redirect()->route('login')->withErrors([
                    'social' => 'This customer account is already linked to a different '.ucfirst($provider).' account.',
                ]);
            }

            $existingUser->socialAccounts()->firstOrCreate([
                'provider' => $provider,
                'provider_user_id' => $providerId,
            ]);

            return $this->loginCustomer($request, $existingUser, $provider);
        }

        if ($intent !== 'signup') {
            return redirect()->route('login')->withErrors([
                'social' => 'No customer account uses that email address. Choose Sign up with '.ucfirst($provider).' first.',
            ]);
        }

        $request->session()->put(self::PENDING_SESSION_KEY, [
            'provider' => $provider,
            'provider_user_id' => $providerId,
            'name' => trim((string) $providerUser->getName()) ?: Str::before($email, '@'),
            'email' => $email,
            'created_at' => now()->timestamp,
        ]);

        return redirect()->route('social.complete');
    }

    public function complete(Request $request): View|RedirectResponse
    {
        $pending = $this->pendingRegistration($request);
        if ($pending === null) {
            return redirect()->route('login')->withErrors([
                'social' => 'Your social signup session expired. Please try again.',
            ]);
        }

        return view('auth.social-complete', ['pending' => $pending]);
    }

    public function store(Request $request): RedirectResponse
    {
        $pending = $this->pendingRegistration($request);
        if ($pending === null) {
            return redirect()->route('login')->withErrors([
                'social' => 'Your social signup session expired. Please try again.',
            ]);
        }

        $validated = $request->validate([
            'contact_number' => ['required', 'regex:/^09\d{9}$/'],
            'birthday' => ['required', 'date', 'before_or_equal:'.now()->subYears(15)->toDateString()],
            'sex' => ['required', Rule::in(User::sexOptions())],
            'terms_accepted' => ['accepted'],
        ], [
            'contact_number.regex' => 'Phone number must be 11 digits starting with 09.',
            'birthday.before_or_equal' => 'You must be at least 15 years old to register.',
            'terms_accepted.accepted' => 'You must agree to the Terms and Conditions and Privacy Policy to create an account.',
        ]);

        try {
            $user = DB::transaction(function () use ($pending, $validated): User {
                $existingUser = User::query()
                    ->whereRaw('LOWER(email) = ?', [$pending['email']])
                    ->lockForUpdate()
                    ->first();

                if ($existingUser instanceof User) {
                    if (! $this->canUseSocialLogin($existingUser)) {
                        abort(403, 'Social login is available only for active customer accounts.');
                    }

                    $user = $existingUser;
                } else {
                    $password = Str::password(40);
                    $username = $this->uniqueUsername($pending['email']);
                    $signup = [
                        'name' => $pending['name'],
                        'username' => $username,
                        'email' => $pending['email'],
                        'contact_number' => $validated['contact_number'],
                        'birthday' => $validated['birthday'],
                        'sex' => $validated['sex'],
                        'password' => $password,
                    ];
                    $walkIn = $this->walkInClients->findWalkInForRegistration(
                        $pending['email'],
                        $validated['contact_number'],
                        $pending['name'],
                    );

                    if ($walkIn instanceof User) {
                        $user = $this->walkInClients->upgradeToRegisteredAccount($walkIn, $signup);
                    } else {
                        $user = User::query()->create(WalkInSchema::markRegistered([
                            ...$signup,
                            'role' => User::ROLE_USER,
                        ]));

                        if (Schema::hasTable('registrations')) {
                            $user->registration()->create([
                                'name' => $signup['name'],
                                'username' => $signup['username'],
                                'email' => $signup['email'],
                                'contact_number' => $signup['contact_number'],
                            ]);
                        }

                        $this->walkInClients->ensureRegisteredCustomerFromSignup($user, $signup);
                    }

                    $user->forceFill(['email_verified_at' => now()])->save();
                }

                $user->socialAccounts()->firstOrCreate(
                    ['provider' => $pending['provider']],
                    ['provider_user_id' => $pending['provider_user_id']],
                );

                return $user->fresh();
            });
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'social' => 'We could not create your account. Please try again.',
            ])->withInput();
        }

        $request->session()->forget(self::PENDING_SESSION_KEY);

        return $this->loginCustomer($request, $user, $pending['provider']);
    }

    private function loginCustomer(Request $request, ?User $user, string $provider): RedirectResponse
    {
        if (! $user instanceof User || ! $this->canUseSocialLogin($user)) {
            return redirect()->route('login')->withErrors([
                'social' => 'Social login is available only for active customer accounts.',
            ]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        ActivityLogger::log(
            'login.social',
            'Signed in with '.ucfirst($provider),
            ['provider' => $provider],
            user: $user,
            request: $request,
        );

        return redirect()->intended(route('landing'));
    }

    /** @return array{provider: string, provider_user_id: string, name: string, email: string, created_at: int}|null */
    private function pendingRegistration(Request $request): ?array
    {
        $pending = $request->session()->get(self::PENDING_SESSION_KEY);
        if (! is_array($pending)
            || ! $this->isSupportedProvider((string) ($pending['provider'] ?? ''))
            || empty($pending['provider_user_id'])
            || empty($pending['email'])
            || (int) ($pending['created_at'] ?? 0) < now()->subMinutes(15)->timestamp) {
            $request->session()->forget(self::PENDING_SESSION_KEY);

            return null;
        }

        return $pending;
    }

    private function canUseSocialLogin(User $user): bool
    {
        return $user->isUser() && ! $user->isWalkIn() && ! $user->isArchived() && ! $user->isBanned();
    }

    private function existingAccountResponse(string $provider): RedirectResponse
    {
        return redirect()->route('login')->withErrors([
            'social' => 'That email address is already registered. Choose Sign in with '.ucfirst($provider).' instead.',
        ]);
    }

    private function isSupportedProvider(string $provider): bool
    {
        return in_array($provider, self::PROVIDERS, true);
    }

    private function isConfigured(string $provider): bool
    {
        return filled(config("services.$provider.client_id"))
            && filled(config("services.$provider.client_secret"))
            && filled(config("services.$provider.redirect"));
    }

    private function uniqueUsername(string $email): string
    {
        $base = preg_replace('/[^a-z0-9_]/', '', Str::lower(Str::before($email, '@'))) ?: 'client';
        $base = Str::limit($base, 24, '');
        if (strlen($base) < 3) {
            $base = 'client'.Str::lower(Str::random(5));
        }

        $candidate = $base;
        $suffix = 1;
        while (User::query()->where('username', $candidate)->exists()) {
            $candidate = Str::limit($base, 24, '').$suffix++;
        }

        return $candidate;
    }
}
