<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureCurrentStaffSession;
use App\Models\AuthVerificationCode;
use App\Models\Customer;
use App\Models\Receptionist;
use App\Models\User;
use App\Notifications\ProfileEmailChangeRequestedNotification;
use App\Rules\NotRecentlyUsedPassword;
use App\Services\ActivityLogger;
use App\Services\AuthVerificationCodeService;
use App\Services\UserActivityService;
use App\Support\CustomerEligibility;
use App\Support\SensitiveInput;
use App\Support\StrongPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        private readonly UserActivityService $activity,
        private readonly AuthVerificationCodeService $verificationCodes,
    ) {}

    public function edit(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return redirect()->route('landing');
        }

        $linkedCustomer = Customer::query()
            ->whereRaw('LOWER(email) = ?', [strtolower((string) $user->email)])
            ->first();
        $birthday = $linkedCustomer?->birthday;

        return view('profile', [
            'user' => $user,
            'transactions' => $this->activity->transactionsForUser($user),
            'customerBirthday' => $birthday?->format('M d, Y') ?? null,
            'customerAge' => $birthday?->age,
        ]);
    }

    /**
     * @return list<array{transaction_id: string, service: string, therapist: string, date: string, time: string, duration: string, amount: string, status: string}>
     */
    public function userTransactions(User $user): array
    {
        return $this->activity->transactionsForUser($user);
    }

    public function updatePhoto(Request $request): RedirectResponse
    {
        $request->validateWithBag('profile', [
            'profile_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();
        $oldPath = $user->profile_photo_path;
        $newPath = $request->file('profile_photo')->store('profile-photos', media_storage_disk());
        $user->profile_photo_path = $newPath;
        $user->save();

        if ($oldPath && $oldPath !== $newPath) {
            Storage::disk(media_storage_disk())->delete($oldPath);
        }

        return back()->with('status', 'Profile photo saved successfully.');
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $originalEmail = strtolower(trim((string) $user->email));
        $originalUsername = strtolower(trim((string) $user->username));
        $linkedCustomer = $user->isUser()
            ? Customer::query()->whereRaw('LOWER(email) = ?', [$originalEmail])->first()
            : null;
        $linkedReceptionist = $user->isReceptionist()
            ? Receptionist::query()
                ->whereRaw('LOWER(email) = ?', [$originalEmail])
                ->orWhereRaw('LOWER(username) = ?', [$originalUsername])
                ->first()
            : null;
        $newStaffSessionToken = null;

        $birthdayRules = $user->isUser()
            ? ['nullable', 'date', CustomerEligibility::birthdayRule()]
            : ['nullable', 'date', 'before_or_equal:today'];

        $validated = $request->validateWithBag('profile', [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'contact_number' => ['nullable', 'regex:/^09\d{9}$/'],
            'birthday' => $birthdayRules,
            'username' => ['required', 'string', 'min:3', 'max:30', Rule::unique('users', 'username')->ignore($user->id), 'alpha_dash:ascii'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'current_password' => ['nullable', 'string', 'required_with:password'],
            'email_current_password' => ['nullable', 'string'],
            'password' => ['nullable', 'confirmed', StrongPassword::rule(), new NotRecentlyUsedPassword($user)],
            'sex' => ['nullable', Rule::in(User::sexOptions())],
            'therapist_gender_preference' => ['nullable', Rule::in([
                User::THERAPIST_PREF_MALE,
                User::THERAPIST_PREF_FEMALE,
                User::THERAPIST_PREF_NO,
            ])],
            'is_pregnant' => ['nullable', 'boolean'],
            'pressure_preference' => ['nullable', Rule::in([
                User::PRESSURE_LOW,
                User::PRESSURE_MEDIUM,
                User::PRESSURE_HIGH,
            ])],
            'receptionist_address' => ['nullable', 'string', 'max:255'],
            'receptionist_birthday' => ['nullable', 'date'],
        ], [
            'contact_number.regex' => 'Phone number must be 11 digits starting with 09.',
            'birthday.before_or_equal' => $user->isUser()
                ? CustomerEligibility::birthdayMessage()
                : 'The birthday must be today or an earlier date.',
        ]);

        $requestedEmail = strtolower(trim((string) $validated['email']));
        $emailChangeRequested = ! hash_equals($originalEmail, $requestedEmail);

        if ($emailChangeRequested) {
            $emailCurrentPassword = (string) ($validated['email_current_password'] ?? $validated['current_password'] ?? '');
            if (! Hash::check($emailCurrentPassword, $user->getAuthPassword())) {
                $errorKey = $request->filled('email_current_password') ? 'email_current_password' : 'current_password';

                return back()
                    ->withErrors([$errorKey => 'Current password is incorrect.'], 'profile')
                    ->withInput(SensitiveInput::safeForFlash($request));
            }
        }

        if (! empty($validated['password'])) {
            if (! Hash::check((string) ($validated['current_password'] ?? ''), $user->getAuthPassword())) {
                return back()
                    ->withErrors(['current_password' => 'Current password is incorrect.'], 'profile')
                    ->withInput(SensitiveInput::safeForFlash($request));
            }

            $user->password = $validated['password'];

            if ($user->isAdmin() || $user->isReceptionist()) {
                $newStaffSessionToken = Str::random(64);
                $user->staff_session_token = $newStaffSessionToken;
            }
        }

        $user->name = $validated['name'];
        $user->email = $originalEmail;
        $user->contact_number = $validated['contact_number'] ?? null;
        if ($request->has('birthday')) {
            $user->birthday = $validated['birthday'] ?? null;
        }
        $user->username = $validated['username'];

        if ($user->isUser()) {
            if (! empty($validated['sex'])) {
                $user->sex = $validated['sex'];
            }
            if (! empty($validated['therapist_gender_preference'])) {
                $user->therapist_gender_preference = $validated['therapist_gender_preference'];
            }
            if (! empty($validated['pressure_preference'])) {
                $user->pressure_preference = $validated['pressure_preference'];
            }
            $effectiveSex = $validated['sex'] ?? $user->sex;
            if ($effectiveSex === User::SEX_FEMALE) {
                $request->validateWithBag('profile', [
                    'is_pregnant' => ['required', Rule::in(['0', '1', 0, 1])],
                ]);
                $user->is_pregnant = (bool) (int) $request->input('is_pregnant');
            } else {
                $user->is_pregnant = null;
            }
            if ($user->profile_completed_at === null && $user->sex && $user->therapist_gender_preference && $user->pressure_preference) {
                $user->profile_completed_at = now();
            }
        }

        if ($request->hasFile('profile_photo')) {
            if (! empty($user->profile_photo_path) && Storage::disk(media_storage_disk())->exists($user->profile_photo_path)) {
                Storage::disk(media_storage_disk())->delete($user->profile_photo_path);
            }
            $user->profile_photo_path = $request->file('profile_photo')->store('profile-photos', media_storage_disk());
        }

        DB::transaction(function () use ($request, $user, $validated, $linkedCustomer, $linkedReceptionist): void {
            if (! empty($validated['password'])) {
                $user->passwordHistories()->create(['password' => $user->getOriginal('password')]);
            }
            $user->save();

            if ($linkedReceptionist !== null) {
                $linkedReceptionist->full_name = (string) $user->name;
                $linkedReceptionist->username = (string) $user->username;
                $linkedReceptionist->email = (string) $user->email;
                $linkedReceptionist->phone_number = $validated['contact_number'] ?? null;
                $linkedReceptionist->address = $validated['receptionist_address'] ?? $linkedReceptionist->address;
                $linkedReceptionist->birthday = $validated['receptionist_birthday'] ?? $linkedReceptionist->birthday;
                $linkedReceptionist->save();
            }

            if ($linkedCustomer !== null) {
                $linkedCustomer->full_name = (string) $user->name;
                $linkedCustomer->email = (string) $user->email;
                if ($request->has('birthday')) {
                    $linkedCustomer->birthday = $validated['birthday'] ?? null;
                }
                $linkedCustomer->number = $validated['contact_number'] ?? $linkedCustomer->number;
                if (! empty($validated['password'])) {
                    $linkedCustomer->password = $validated['password'];
                }
                $linkedCustomer->save();
            }
        });

        if (! empty($validated['password'])) {
            $user->passwordHistories()->latest()->skip(5)->take(100)->get()->each->delete();
        }

        Auth::login($user->refresh());

        if ($newStaffSessionToken !== null) {
            $request->session()->put(EnsureCurrentStaffSession::SESSION_KEY, $newStaffSessionToken);
        }

        if ($emailChangeRequested) {
            $verification = null;

            try {
                $verification = $this->verificationCodes->issue(
                    $requestedEmail,
                    AuthVerificationCode::PURPOSE_EMAIL_CHANGE,
                    [
                        'user_id' => $user->getKey(),
                        'original_email' => $originalEmail,
                        'new_email' => $requestedEmail,
                    ],
                    (string) $user->name,
                );

                Notification::route('mail', $originalEmail)
                    ->notify(new ProfileEmailChangeRequestedNotification($requestedEmail, (string) $user->name));
            } catch (\Throwable $exception) {
                $verification?->delete();
                Log::warning('Profile email verification could not be sent.', [
                    'user_id' => $user->getKey(),
                    'message' => $exception->getMessage(),
                ]);

                return back()
                    ->withErrors(['email' => 'Your other profile changes were saved, but we could not send an email verification code. Please try the email change again.'], 'profile')
                    ->withInput(SensitiveInput::safeForFlash($request));
            }

            ActivityLogger::log(
                'profile.email_change_requested',
                'Requested an account email change',
                ['old_email' => $originalEmail, 'new_email' => $requestedEmail],
                subject: $user,
                request: $request,
            );

            return redirect()->route('profile.email-change.show', $verification)
                ->with('status', 'Your profile was saved. Enter the code sent to your new email address to finish changing it.');
        }

        ActivityLogger::log(
            'profile.updated',
            'Updated account profile',
            ['email' => $user->email],
            subject: $user,
            request: $request,
        );

        return back()->with('status', 'Profile updated successfully.');
    }

    public function showEmailChangeVerification(Request $request, AuthVerificationCode $verification): View
    {
        $this->authorizeEmailChangeVerification($request, $verification);

        return view('auth.verify-code', [
            'verification' => $verification,
            'verificationSubmitUrl' => route('profile.email-change.verify', $verification),
            'verificationResendUrl' => route('profile.email-change.resend', $verification),
            'verificationBackUrl' => route($this->profileReturnRoute($request->user())),
            'verificationBackLabel' => 'Back to profile',
        ]);
    }

    public function verifyEmailChange(Request $request, AuthVerificationCode $verification): RedirectResponse
    {
        $this->authorizeEmailChangeVerification($request, $verification);

        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);
        $verification = $this->verificationCodes->verify(
            $verification->getKey(),
            AuthVerificationCode::PURPOSE_EMAIL_CHANGE,
            $validated['code'],
        );
        $payload = $verification->payload ?? [];
        $originalEmail = strtolower(trim((string) ($payload['original_email'] ?? '')));
        $newEmail = strtolower(trim((string) ($payload['new_email'] ?? '')));

        if ($originalEmail === '' || $newEmail === '') {
            $verification->delete();
            throw ValidationException::withMessages(['code' => 'This email-change request is invalid. Start a new request from your profile.']);
        }

        $newStaffSessionToken = DB::transaction(function () use ($request, $verification, $originalEmail, $newEmail): ?string {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->getKey());

            if (! hash_equals($originalEmail, strtolower(trim((string) $user->email)))) {
                $verification->delete();
                throw ValidationException::withMessages(['code' => 'Your account email changed after this request. Start a new request from your profile.']);
            }

            $emailIsTaken = User::query()
                ->whereKeyNot($user->getKey())
                ->whereRaw('LOWER(email) = ?', [$newEmail])
                ->exists();
            if ($emailIsTaken) {
                throw ValidationException::withMessages(['code' => 'That email address is already in use. Return to your profile and choose another email.']);
            }

            $linkedCustomer = $user->isUser()
                ? Customer::query()->whereRaw('LOWER(email) = ?', [$originalEmail])->lockForUpdate()->first()
                : null;
            $linkedReceptionist = $user->isReceptionist()
                ? Receptionist::query()
                    ->where(function ($query) use ($originalEmail, $user): void {
                        $query->whereRaw('LOWER(email) = ?', [$originalEmail])
                            ->orWhereRaw('LOWER(username) = ?', [strtolower(trim((string) $user->username))]);
                    })
                    ->lockForUpdate()
                    ->first()
                : null;

            $staffSessionToken = null;
            if ($user->isAdmin() || $user->isReceptionist()) {
                $staffSessionToken = Str::random(64);
                $user->staff_session_token = $staffSessionToken;
            }

            $user->email = $newEmail;
            $user->email_verified_at = now();
            $user->save();

            if ($linkedCustomer !== null) {
                $linkedCustomer->email = $newEmail;
                $linkedCustomer->save();
            }

            if ($linkedReceptionist !== null) {
                $linkedReceptionist->email = $newEmail;
                $linkedReceptionist->save();
            }

            $verification->delete();

            return $staffSessionToken;
        });

        if ($newStaffSessionToken !== null) {
            $request->session()->put(EnsureCurrentStaffSession::SESSION_KEY, $newStaffSessionToken);
        }

        $user = $request->user()->refresh();
        Auth::setUser($user);
        ActivityLogger::log(
            'profile.email_changed',
            'Verified and changed the account email',
            ['old_email' => $originalEmail, 'new_email' => $newEmail],
            subject: $user,
            request: $request,
        );

        return redirect()->route($this->profileReturnRoute($user))
            ->with('status', 'Your email address has been verified and updated.');
    }

    public function resendEmailChangeVerification(Request $request, AuthVerificationCode $verification): RedirectResponse
    {
        $this->authorizeEmailChangeVerification($request, $verification);

        if ($verification->last_sent_at->addSeconds(AuthVerificationCodeService::RESEND_SECONDS)->isFuture()) {
            return back()->withErrors(['code' => 'Please wait before requesting another code.']);
        }

        try {
            $replacement = $this->verificationCodes->issue(
                $verification->email,
                AuthVerificationCode::PURPOSE_EMAIL_CHANGE,
                $verification->payload ?? [],
                (string) $request->user()->name,
            );
        } catch (\Throwable $exception) {
            Log::warning('Profile email verification code could not be resent.', [
                'user_id' => $request->user()->getKey(),
                'message' => $exception->getMessage(),
            ]);

            return back()->withErrors(['code' => 'We could not send a new code right now. Please try again later.']);
        }

        return redirect()->route('profile.email-change.show', $replacement)
            ->with('status', 'A new verification code has been sent.');
    }

    private function authorizeEmailChangeVerification(Request $request, AuthVerificationCode $verification): void
    {
        $payload = $verification->payload ?? [];

        abort_unless(
            $verification->purpose === AuthVerificationCode::PURPOSE_EMAIL_CHANGE
                && (int) ($payload['user_id'] ?? 0) === (int) $request->user()->getKey(),
            404,
        );
    }

    private function profileReturnRoute(User $user): string
    {
        return match ($user->role) {
            User::ROLE_ADMIN => 'dashboard',
            User::ROLE_RECEPTIONIST => 'receptionist.dashboard',
            default => 'profile.edit',
        };
    }
}
