<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Receptionist;
use App\Models\User;
use App\Rules\NotRecentlyUsedPassword;
use App\Services\ActivityLogger;
use App\Services\UserActivityService;
use App\Support\StrongPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        private readonly UserActivityService $activity,
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

        $validated = $request->validateWithBag('profile', [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'contact_number' => ['nullable', 'regex:/^09\d{9}$/'],
            'birthday' => ['nullable', 'date', 'before_or_equal:today'],
            'username' => ['required', 'string', 'min:3', 'max:30', Rule::unique('users', 'username')->ignore($user->id), 'alpha_dash:ascii'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'current_password' => ['nullable', 'string', 'required_with:password'],
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
        ]);

        if (! empty($validated['password'])) {
            if (! Hash::check((string) ($validated['current_password'] ?? ''), $user->getAuthPassword())) {
                return back()
                    ->withErrors(['current_password' => 'Current password is incorrect.'], 'profile')
                    ->withInput();
            }
            $user->password = $validated['password'];
        }

        $user->name = $validated['name'];
        $user->email = $validated['email'];
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

        ActivityLogger::log(
            'profile.updated',
            'Updated account profile',
            ['email' => $user->email],
            subject: $user,
            request: $request,
        );

        return back()->with('status', 'Profile updated successfully.');
    }
}
