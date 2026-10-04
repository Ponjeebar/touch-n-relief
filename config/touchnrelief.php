<?php

return [
    'therapist_annual_service_target_hours' => (int) env('THERAPIST_ANNUAL_SERVICE_TARGET_HOURS', 240),
    'client_new_days' => (int) env('CLIENT_NEW_DAYS', 30),
    'client_active_months' => (int) env('CLIENT_ACTIVE_MONTHS', 12),
    'client_auto_archive_months' => (int) env('CLIENT_AUTO_ARCHIVE_MONTHS', 24),
    'schedules' => [
        'appointment_reminders' => env('APPOINTMENT_REMINDER_SCHEDULE', '*/5 * * * *'),
        'automatic_no_shows' => env('AUTOMATIC_NO_SHOW_SCHEDULE', '* * * * *'),
        'client_archive_time' => env('CLIENT_ARCHIVE_SCHEDULE_TIME', '02:15'),
        'google_drive_backup_time' => env('GOOGLE_DRIVE_BACKUP_SCHEDULE_TIME', '02:30'),
    ],
];
