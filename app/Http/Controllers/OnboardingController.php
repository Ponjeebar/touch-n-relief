<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class OnboardingController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isUser()) {
            return redirect()->route('landing');
        }

        $rules = [
            'therapist_gender_preference' => ['required', Rule::in([
                User::THERAPIST_PREF_MALE,
                User::THERAPIST_PREF_FEMALE,
                User::THERAPIST_PREF_NO,
            ])],
            'pressure_preference' => ['required', Rule::in([
                User::PRESSURE_LOW,
                User::PRESSURE_MEDIUM,
                User::PRESSURE_HIGH,
            ])],
        ];

        if ($user->sex === User::SEX_FEMALE) {
            $rules['is_pregnant'] = ['required', Rule::in(['0', '1', 0, 1])];
        }

        $validated = $request->validateWithBag('onboarding', $rules);

        $user->therapist_gender_preference = $validated['therapist_gender_preference'];
        $user->pressure_preference = $validated['pressure_preference'];
        $user->is_pregnant = $user->sex === User::SEX_FEMALE
            ? (bool) (int) $request->input('is_pregnant')
            : null;
        $user->profile_completed_at = now();
        $user->save();

        Auth::login($user->refresh());

        ActivityLogger::log(
            'profile.onboarding_completed',
            'Completed wellness profile questionnaire',
            user: $user,
            request: $request,
        );

        $returnTo = trim((string) $request->input('return_to', ''));
        if ($this->isSafeReturnTo($request, $returnTo)) {
            return redirect()->to($returnTo);
        }

        return redirect()->route('landing');
    }

    private function isSafeReturnTo(Request $request, string $url): bool
    {
        if ($url === '') {
            return false;
        }

        if (str_starts_with($url, '/')) {
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
}
