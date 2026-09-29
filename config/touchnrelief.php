<?php

return [
    'therapist_annual_service_target_hours' => (int) env('THERAPIST_ANNUAL_SERVICE_TARGET_HOURS', 240),
    'client_new_days' => (int) env('CLIENT_NEW_DAYS', 30),
    'client_active_months' => (int) env('CLIENT_ACTIVE_MONTHS', 12),
    'client_auto_archive_months' => (int) env('CLIENT_AUTO_ARCHIVE_MONTHS', 24),
];
