<?php

namespace App\Services;

use App\Models\SiteSetting;
use App\Models\Therapist;
use Illuminate\Support\Facades\Schema;

class SiteSettingsService
{
    public const KEY_CONTACT_PHONE = 'footer_contact_phone';

    public const KEY_CONTACT_EMAIL = 'footer_contact_email';

    public const KEY_CONTACT_ADDRESS = 'footer_contact_address';

    public const KEY_HOURS_WEEKDAY = 'footer_hours_weekday';

    public const KEY_HOURS_WEEKEND = 'footer_hours_weekend';

    public const KEY_HOURS_HOLIDAYS = 'footer_hours_holidays';

    public const KEY_FACEBOOK_URL = 'footer_facebook_url';

    public const KEY_INSTAGRAM_URL = 'footer_instagram_url';

    public const KEY_THERAPIST_SCHEDULE_AUTOMATION = 'therapist_schedule_automation';

    public const KEY_THERAPIST_DEFAULT_WORKING_DAYS = 'therapist_default_working_days';

    public const KEY_CANCELLATION_CUTOFF_HOURS = 'booking_cancellation_cutoff_hours';

    public const KEY_PAYMENT_HOLD_MINUTES = 'booking_payment_hold_minutes';

    public const KEY_CUSTOMER_MINIMUM_LEAD_MINUTES = 'booking_customer_minimum_lead_minutes';

    public const KEY_LATE_GRACE_MINUTES = 'appointment_late_grace_minutes';

    public const KEY_NO_SHOW_REVIEW_MINUTES = 'appointment_no_show_review_minutes';

    public const KEY_NO_SHOW_RESTRICTION_THRESHOLD = 'appointment_no_show_restriction_threshold';

    public const KEY_EXPIRED_HOLD_LIMIT = 'booking_expired_hold_limit';

    public const KEY_EXPIRED_HOLD_LOOKBACK_HOURS = 'booking_expired_hold_lookback_hours';

    public const KEY_EXPIRED_HOLD_COOLDOWN_MINUTES = 'booking_expired_hold_cooldown_minutes';

    public const KEY_BACKUP_RETENTION_DAYS = 'backup_retention_days';

    public function cancellationCutoffHours(): int
    {
        return max(0, min(8760, (int) SiteSetting::valueFor(self::KEY_CANCELLATION_CUTOFF_HOURS, '24')));
    }

    public function updateCancellationCutoffHours(int $hours): void
    {
        SiteSetting::put(self::KEY_CANCELLATION_CUTOFF_HOURS, (string) max(0, min(8760, $hours)));
    }

    /** @return array<string, int> */
    public function systemRules(): array
    {
        $paymentHold = $this->paymentHoldMinutes();

        return [
            'cancellation_cutoff_hours' => $this->cancellationCutoffHours(),
            'payment_hold_minutes' => $paymentHold,
            'customer_minimum_lead_minutes' => $this->customerMinimumLeadMinutes(),
            'late_grace_minutes' => $this->lateGraceMinutes(),
            'no_show_review_minutes' => $this->noShowReviewMinutes(),
            'no_show_restriction_threshold' => $this->noShowRestrictionThreshold(),
            'expired_hold_limit' => $this->expiredHoldLimit(),
            'expired_hold_lookback_hours' => $this->expiredHoldLookbackHours(),
            'expired_hold_cooldown_minutes' => $this->expiredHoldCooldownMinutes(),
            'backup_retention_days' => $this->backupRetentionDays(),
        ];
    }

    /** @param array<string, int> $rules */
    public function updateSystemRules(array $rules): void
    {
        $keys = [
            'cancellation_cutoff_hours' => self::KEY_CANCELLATION_CUTOFF_HOURS,
            'payment_hold_minutes' => self::KEY_PAYMENT_HOLD_MINUTES,
            'customer_minimum_lead_minutes' => self::KEY_CUSTOMER_MINIMUM_LEAD_MINUTES,
            'late_grace_minutes' => self::KEY_LATE_GRACE_MINUTES,
            'no_show_review_minutes' => self::KEY_NO_SHOW_REVIEW_MINUTES,
            'no_show_restriction_threshold' => self::KEY_NO_SHOW_RESTRICTION_THRESHOLD,
            'expired_hold_limit' => self::KEY_EXPIRED_HOLD_LIMIT,
            'expired_hold_lookback_hours' => self::KEY_EXPIRED_HOLD_LOOKBACK_HOURS,
            'expired_hold_cooldown_minutes' => self::KEY_EXPIRED_HOLD_COOLDOWN_MINUTES,
            'backup_retention_days' => self::KEY_BACKUP_RETENTION_DAYS,
        ];

        foreach ($keys as $input => $key) {
            SiteSetting::put($key, (string) $rules[$input]);
        }
    }

    public function paymentHoldMinutes(): int
    {
        return $this->integerSetting(self::KEY_PAYMENT_HOLD_MINUTES, 15, 5, 30);
    }

    public function customerMinimumLeadMinutes(): int
    {
        return max(
            $this->integerSetting(
                self::KEY_CUSTOMER_MINIMUM_LEAD_MINUTES,
                (int) config('touchnrelief.booking.customer_minimum_lead_minutes', 30),
                6,
                1440,
            ),
            $this->paymentHoldMinutes() + 1,
        );
    }

    public function lateGraceMinutes(): int
    {
        return $this->integerSetting(self::KEY_LATE_GRACE_MINUTES, 10, 1, 60);
    }

    public function noShowReviewMinutes(): int
    {
        return $this->integerSetting(self::KEY_NO_SHOW_REVIEW_MINUTES, 5, 1, 60);
    }

    public function noShowRestrictionThreshold(): int
    {
        return $this->integerSetting(self::KEY_NO_SHOW_RESTRICTION_THRESHOLD, 3, 1, 10);
    }

    public function expiredHoldLimit(): int
    {
        return $this->integerSetting(
            self::KEY_EXPIRED_HOLD_LIMIT,
            (int) config('touchnrelief.booking.expired_hold_limit', 3),
            1,
            10,
        );
    }

    public function expiredHoldLookbackHours(): int
    {
        return $this->integerSetting(
            self::KEY_EXPIRED_HOLD_LOOKBACK_HOURS,
            (int) config('touchnrelief.booking.expired_hold_lookback_hours', 24),
            1,
            168,
        );
    }

    public function expiredHoldCooldownMinutes(): int
    {
        return $this->integerSetting(
            self::KEY_EXPIRED_HOLD_COOLDOWN_MINUTES,
            (int) config('touchnrelief.booking.expired_hold_cooldown_minutes', 60),
            15,
            1440,
        );
    }

    public function backupRetentionDays(): int
    {
        return $this->integerSetting(
            self::KEY_BACKUP_RETENTION_DAYS,
            (int) config('services.google_drive_backup.retention_days', 14),
            1,
            365,
        );
    }

    private function integerSetting(string $key, int $default, int $minimum, int $maximum): int
    {
        return max($minimum, min($maximum, (int) SiteSetting::valueFor($key, (string) $default)));
    }

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
            'facebook_url' => '',
            'instagram_url' => '',
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
            'facebook_url' => SiteSetting::valueFor(self::KEY_FACEBOOK_URL, $defaults['facebook_url']) ?? $defaults['facebook_url'],
            'instagram_url' => SiteSetting::valueFor(self::KEY_INSTAGRAM_URL, $defaults['instagram_url']) ?? $defaults['instagram_url'],
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
        SiteSetting::put(self::KEY_FACEBOOK_URL, trim($data['facebook_url'] ?? ''));
        SiteSetting::put(self::KEY_INSTAGRAM_URL, trim($data['instagram_url'] ?? ''));
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
        if (! Schema::hasTable('site_settings')) {
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

        if (SiteSetting::valueFor(self::KEY_CANCELLATION_CUTOFF_HOURS) === null) {
            SiteSetting::put(self::KEY_CANCELLATION_CUTOFF_HOURS, '24');
        }

        $defaults = [
            self::KEY_PAYMENT_HOLD_MINUTES => 15,
            self::KEY_CUSTOMER_MINIMUM_LEAD_MINUTES => (int) config('touchnrelief.booking.customer_minimum_lead_minutes', 30),
            self::KEY_LATE_GRACE_MINUTES => 10,
            self::KEY_NO_SHOW_REVIEW_MINUTES => 5,
            self::KEY_NO_SHOW_RESTRICTION_THRESHOLD => 3,
            self::KEY_EXPIRED_HOLD_LIMIT => (int) config('touchnrelief.booking.expired_hold_limit', 3),
            self::KEY_EXPIRED_HOLD_LOOKBACK_HOURS => (int) config('touchnrelief.booking.expired_hold_lookback_hours', 24),
            self::KEY_EXPIRED_HOLD_COOLDOWN_MINUTES => (int) config('touchnrelief.booking.expired_hold_cooldown_minutes', 60),
            self::KEY_BACKUP_RETENTION_DAYS => (int) config('services.google_drive_backup.retention_days', 14),
        ];

        foreach ($defaults as $key => $value) {
            if (SiteSetting::valueFor($key) === null) {
                SiteSetting::put($key, (string) $value);
            }
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

        if (! empty($data['apply_working_days_to_all']) && Schema::hasTable('therapists')) {
            Therapist::query()->update(['working_days' => $days]);
        }
    }
}
