<?php

namespace App\Services;

use App\Models\SiteSetting;

class SiteSettingsService
{
    public const KEY_CONTACT_PHONE = 'footer_contact_phone';

    public const KEY_CONTACT_EMAIL = 'footer_contact_email';

    public const KEY_CONTACT_ADDRESS = 'footer_contact_address';

    public const KEY_HOURS_WEEKDAY = 'footer_hours_weekday';

    public const KEY_HOURS_WEEKEND = 'footer_hours_weekend';

    public const KEY_HOURS_HOLIDAYS = 'footer_hours_holidays';

    public const KEY_THERAPIST_SCHEDULE_AUTOMATION = 'therapist_schedule_automation';

    public const KEY_THERAPIST_DEFAULT_WORKING_DAYS = 'therapist_default_working_days';

    /**
     * @return array<string, string>
     */
    public function footerDefaults(): array
    {
        return [
            'contact_phone' => '+63 912 345 6789',
            'contact_email' => 'hello@touchnreliefspa.com',
            'contact_address' => 'Wellness Ave, City Center',
            'hours_weekday' => 'Mon - Fri: 9:00 AM - 9:00 PM',
            'hours_weekend' => 'Sat - Sun: 10:00 AM - 10:00 PM',
            'hours_holidays' => 'Holidays: By Appointment',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function footer(): array
    {
        $defaults = $this->footerDefaults();

        return [
            'contact_phone' => SiteSetting::valueFor(self::KEY_CONTACT_PHONE, $defaults['contact_phone']) ?? $defaults['contact_phone'],
            'contact_email' => SiteSetting::valueFor(self::KEY_CONTACT_EMAIL, $defaults['contact_email']) ?? $defaults['contact_email'],
            'contact_address' => SiteSetting::valueFor(self::KEY_CONTACT_ADDRESS, $defaults['contact_address']) ?? $defaults['contact_address'],
            'hours_weekday' => SiteSetting::valueFor(self::KEY_HOURS_WEEKDAY, $defaults['hours_weekday']) ?? $defaults['hours_weekday'],
            'hours_weekend' => SiteSetting::valueFor(self::KEY_HOURS_WEEKEND, $defaults['hours_weekend']) ?? $defaults['hours_weekend'],
            'hours_holidays' => SiteSetting::valueFor(self::KEY_HOURS_HOLIDAYS, $defaults['hours_holidays']) ?? $defaults['hours_holidays'],
        ];
    }

    /**
     * @param  array<string, string>  $data
     */
    public function updateFooter(array $data): void
    {
        SiteSetting::put(self::KEY_CONTACT_PHONE, trim($data['contact_phone'] ?? ''));
        SiteSetting::put(self::KEY_CONTACT_EMAIL, trim($data['contact_email'] ?? ''));
        SiteSetting::put(self::KEY_CONTACT_ADDRESS, trim($data['contact_address'] ?? ''));
        SiteSetting::put(self::KEY_HOURS_WEEKDAY, trim($data['hours_weekday'] ?? ''));
        SiteSetting::put(self::KEY_HOURS_WEEKEND, trim($data['hours_weekend'] ?? ''));
        SiteSetting::put(self::KEY_HOURS_HOLIDAYS, trim($data['hours_holidays'] ?? ''));
    }

    /**
     * @return array{automation_enabled: bool, default_working_days: list<int>}
     */
    public function therapistSchedule(): array
    {
        $defaults = $this->therapistScheduleDefaults();

        $automationRaw = SiteSetting::valueFor(self::KEY_THERAPIST_SCHEDULE_AUTOMATION, $defaults['automation_enabled'] ? '1' : '0');
        $daysRaw = SiteSetting::valueFor(self::KEY_THERAPIST_DEFAULT_WORKING_DAYS, json_encode($defaults['default_working_days']));

        $decodedDays = json_decode((string) $daysRaw, true);
        $days = is_array($decodedDays) ? $decodedDays : $defaults['default_working_days'];

        $days = collect($days)
            ->map(fn ($day) => (int) $day)
            ->filter(fn (int $day): bool => $day >= 0 && $day <= 6)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return [
            'automation_enabled' => in_array(strtolower((string) $automationRaw), ['1', 'true', 'yes', 'on'], true),
            'default_working_days' => $days !== [] ? $days : $defaults['default_working_days'],
        ];
    }

    /**
     * @return array{automation_enabled: bool, default_working_days: list<int>}
     */
    public function therapistScheduleDefaults(): array
    {
        return [
            'automation_enabled' => true,
            'default_working_days' => [1, 2, 3, 4, 5, 6],
        ];
    }

    public function ensureSeeded(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('site_settings')) {
            return;
        }

        $scheduleDefaults = $this->therapistScheduleDefaults();

        if (SiteSetting::valueFor(self::KEY_THERAPIST_SCHEDULE_AUTOMATION) === null) {
            SiteSetting::put(
                self::KEY_THERAPIST_SCHEDULE_AUTOMATION,
                $scheduleDefaults['automation_enabled'] ? '1' : '0',
            );
        }

        if (SiteSetting::valueFor(self::KEY_THERAPIST_DEFAULT_WORKING_DAYS) === null) {
            SiteSetting::put(
                self::KEY_THERAPIST_DEFAULT_WORKING_DAYS,
                json_encode($scheduleDefaults['default_working_days']),
            );
        }
    }

    /**
     * @param  array{automation_enabled?: bool, default_working_days?: list<int>, apply_working_days_to_all?: bool}  $data
     */
    public function updateTherapistSchedule(array $data): void
    {
        SiteSetting::put(
            self::KEY_THERAPIST_SCHEDULE_AUTOMATION,
            ! empty($data['automation_enabled']) ? '1' : '0',
        );

        $days = collect($data['default_working_days'] ?? [])
            ->map(fn ($day) => (int) $day)
            ->filter(fn (int $day): bool => $day >= 0 && $day <= 6)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($days === []) {
            $days = $this->therapistScheduleDefaults()['default_working_days'];
        }

        SiteSetting::put(self::KEY_THERAPIST_DEFAULT_WORKING_DAYS, json_encode($days));

        if (! empty($data['apply_working_days_to_all']) && \Illuminate\Support\Facades\Schema::hasTable('therapists')) {
            \App\Models\Therapist::query()->update(['working_days' => $days]);
        }
    }
}
