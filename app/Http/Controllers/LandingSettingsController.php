<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\SiteSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LandingSettingsController extends Controller
{
    public function edit(SiteSettingsService $settings): View
    {
        return view('landing-settings.index', [
            'footer' => $settings->footer(),
        ]);
    }

    public function update(Request $request, SiteSettingsService $settings): RedirectResponse
    {
        $validated = $request->validate([
            'contact_phone' => ['required', 'string', 'max:80'],
            'contact_email' => ['required', 'email', 'max:120'],
            'contact_address' => ['required', 'string', 'max:200'],
            'hours_weekday' => ['required', 'string', 'max:120'],
            'hours_weekend' => ['required', 'string', 'max:120'],
            'hours_holidays' => ['required', 'string', 'max:120'],
        ]);

        $settings->updateFooter($validated);

        ActivityLogger::log(
            'landing.footer_updated',
            'Updated landing page footer contact and opening hours',
            $validated,
            request: $request,
        );

        return redirect()
            ->route('landing-settings.edit')
            ->with('status', 'Landing page footer updated successfully.');
    }
}
