<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use App\Services\ActivityLogger;
use App\Services\NoShowService;
use App\Services\SiteSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SystemSettingsController extends Controller
{
    public function edit(SiteSettingsService $settings): View
    {
        return view('system-settings.index', [
            'rules' => $settings->systemRules(),
        ]);
    }

    public function update(Request $request, SiteSettingsService $settings, NoShowService $noShows): RedirectResponse
    {
        $validated = $request->validate([
            'cancellation_cutoff_hours' => ['required', 'integer', 'min:0', 'max:8760'],
            'payment_hold_minutes' => ['required', 'integer', 'min:5', 'max:30'],
            'customer_minimum_lead_minutes' => ['required', 'integer', 'min:6', 'max:1440', 'gt:payment_hold_minutes'],
            'late_grace_minutes' => ['required', 'integer', 'min:1', 'max:60'],
            'no_show_review_minutes' => ['required', 'integer', 'min:1', 'max:60'],
            'no_show_restriction_threshold' => ['required', 'integer', 'min:1', 'max:10'],
            'expired_hold_limit' => ['required', 'integer', 'min:1', 'max:10'],
            'expired_hold_lookback_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'expired_hold_cooldown_minutes' => ['required', 'integer', 'min:15', 'max:1440'],
            'backup_retention_days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $values = array_map(static fn (mixed $value): int => (int) $value, $validated);
        DB::transaction(function () use ($settings, $noShows, $values, $request): void {
            SiteSetting::query()
                ->whereIn('key', array_values($settings->systemRuleStorageKeys()))
                ->orderBy('key')
                ->lockForUpdate()
                ->get(['id']);

            $before = $settings->systemRules();
            $settings->updateSystemRules($values);
            $after = $settings->systemRules();
            $changes = collect($after)->filter(
                static fn (int $value, string $key): bool => $before[$key] !== $value,
            )->mapWithKeys(
                static fn (int $value, string $key): array => [$key => ['from' => $before[$key], 'to' => $value]],
            )->all();

            $restrictionChanges = $before['no_show_restriction_threshold'] !== $after['no_show_restriction_threshold']
                ? $noShows->reconcileCustomerRestrictions($after['no_show_restriction_threshold'])
                : ['restricted' => [], 'restored' => []];

            ActivityLogger::log(
                'system.settings_updated',
                'Updated booking, attendance, account protection, and backup settings',
                ['changes' => $changes, 'no_show_restrictions' => $restrictionChanges],
                request: $request,
            );
        });

        return redirect()
            ->route('system-settings.edit')
            ->with('status', 'System settings updated successfully.');
    }
}
