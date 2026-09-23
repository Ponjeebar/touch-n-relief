<?php

namespace App\Services;

use App\Models\SpaBooking;
use App\Models\Therapist;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class TherapistAvailabilityService
{
    /** @return list<int> 0 = Sunday … 6 = Saturday */
    public function defaultWorkingDays(): array
    {
        return [1, 2, 3, 4, 5, 6];
    }

    /**
     * @return array{automation_enabled: bool, default_working_days: list<int>}
     */
    public function globalSettings(): array
    {
        $settings = app(SiteSettingsService::class)->therapistSchedule();

        return [
            'automation_enabled' => $settings['automation_enabled'],
            'default_working_days' => $settings['default_working_days'],
        ];
    }

    /**
     * @param  list<int>|null  $workingDays
     * @return list<int>
     */
    public function normalizeWorkingDays(?array $workingDays): array
    {
        if ($workingDays === null || $workingDays === []) {
            return $this->globalSettings()['default_working_days'];
        }

        $days = collect($workingDays)
            ->map(fn ($day) => (int) $day)
            ->filter(fn (int $day): bool => $day >= 0 && $day <= 6)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $days !== [] ? $days : $this->defaultWorkingDays();
    }

    public function effectiveStatus(Therapist $therapist, ?Carbon $at = null): string
    {
        $at ??= now();
        $scheduled = $this->effectiveStatusForDate($therapist, $at->copy());
        if ($scheduled === 'off-duty') {
            return $scheduled;
        }

        $name = trim((string) $therapist->name);
        if ($name !== '' && Schema::hasTable('spa_bookings')) {
            $busy = SpaBooking::query()
                ->where('therapist_name', $name)
                ->where('session_status', SpaBooking::STATUS_IN_SESSION)
                ->whereNull('cancelled_at')
                ->whereNull('completed_at')
                ->exists();
            if ($busy) {
                return 'busy';
            }

            if (app(BookingSlotService::class)->therapistIsRestingAt($name, $at)) {
                return 'resting';
            }
        }

        return 'available';
    }

    public function effectiveStatusForDate(Therapist $therapist, Carbon $date): string
    {
        $stored = strtolower(trim((string) $therapist->status));
        $date = $date->copy()->startOfDay();
        if ((bool) $therapist->work_on_off_day) {
            return 'available';
        }

        if ($therapist->day_off_until !== null && $date->lte($therapist->day_off_until->copy()->startOfDay())) {
            return 'off-duty';
        }

        $global = $this->globalSettings();

        if ($global['automation_enabled']) {
            $days = $this->normalizeWorkingDays($therapist->working_days);
            $dayOfWeek = (int) $date->format('w');

            if (! in_array($dayOfWeek, $days, true)) {
                return 'off-duty';
            }
        }

        if ($stored === 'off-duty') {
            return 'off-duty';
        }

        return 'available';
    }

    public function isBookable(Therapist $therapist, ?Carbon $date = null): bool
    {
        return $this->effectiveStatusForDate($therapist, ($date ?? now())->copy()) === 'available';
    }

    public function unavailableLabel(Therapist $therapist, ?Carbon $date = null): ?string
    {
        if ($this->isBookable($therapist, $date)) {
            return null;
        }

        return match ($this->effectiveStatusForDate($therapist, ($date ?? now())->copy())) {
            'off-duty' => 'Off duty',
            default => 'Unavailable',
        };
    }

    public function findByName(string $name): ?Therapist
    {
        $name = trim($name);
        if ($name === '' || ! Schema::hasTable('therapists')) {
            return null;
        }

        return Therapist::query()
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->first();
    }

    /**
     * @throws ValidationException
     */
    public function assertBookableOnDate(string $therapistName, string $bookingDate, string $field = 'therapist'): void
    {
        $therapist = $this->findByName($therapistName);
        if ($therapist === null) {
            return;
        }

        $date = Carbon::parse($bookingDate)->startOfDay();

        if ($this->isBookable($therapist, $date)) {
            return;
        }

        $label = $this->unavailableLabel($therapist, $date) ?? 'Unavailable';

        throw ValidationException::withMessages([
            $field => $therapistName.' is '.$label.' on the selected date. Please choose another therapist or date.',
        ]);
    }

    /**
     * @return list<string>
     */
    public function bookableTherapistNamesForDate(Carbon $date): array
    {
        if (! Schema::hasTable('therapists')) {
            return collect(app(TherapistCatalog::class)->defaultCatalog())
                ->filter(fn (array $row): bool => strtolower((string) ($row['status'] ?? 'available')) === 'available')
                ->pluck('name')
                ->map(fn ($name) => (string) $name)
                ->values()
                ->all();
        }

        $query = Therapist::query();
        if (Schema::hasColumn('therapists', 'is_active')) {
            $query->where('is_active', true);
        }

        return $query
            ->orderBy(Schema::hasColumn('therapists', 'sort_order') ? 'sort_order' : 'therapist_code')
            ->orderBy('therapist_code')
            ->get()
            ->filter(fn (Therapist $therapist): bool => $this->isBookable($therapist, $date))
            ->pluck('name')
            ->map(fn ($name) => (string) $name)
            ->values()
            ->all();
    }

    /**
     * @return array<string, array{bookable: bool, status: string, label: ?string}>
     */
    public function bookingAvailabilityMapForDate(Carbon $date): array
    {
        if (! Schema::hasTable('therapists')) {
            $map = [];
            foreach (app(TherapistCatalog::class)->defaultCatalog() as $row) {
                $name = (string) ($row['name'] ?? '');
                if ($name === '') {
                    continue;
                }
                $status = strtolower((string) ($row['status'] ?? 'available'));
                $bookable = $status === 'available';
                $map[$name] = [
                    'bookable' => $bookable,
                    'status' => $status,
                    'label' => $bookable ? null : ($status === 'busy' ? 'In session' : 'Off duty'),
                ];
            }

            return $map;
        }

        $query = Therapist::query();
        if (Schema::hasColumn('therapists', 'is_active')) {
            $query->where('is_active', true);
        }

        $map = [];
        foreach ($query->orderBy('name')->get() as $therapist) {
            $status = $this->effectiveStatusForDate($therapist, $date);
            $bookable = $status === 'available';
            $map[(string) $therapist->name] = [
                'bookable' => $bookable,
                'status' => $status,
                'label' => $bookable ? null : $this->unavailableLabel($therapist, $date),
            ];
        }

        return $map;
    }

    /**
     * @param  mixed  $raw
     * @return list<int>
     */
    public function workingDaysFromRequest($raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $this->normalizeWorkingDays($decoded);
            }

            $raw = explode(',', $raw);
        }

        if (! is_array($raw)) {
            return [];
        }

        return $this->normalizeWorkingDays($raw);
    }
}
