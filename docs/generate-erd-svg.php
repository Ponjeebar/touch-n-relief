<?php
/**
 * Generates a single connected ERD SVG for TOUCHnRELIEF.
 * Run: php docs/generate-erd-svg.php
 */

$tables = [
    'users' => [
        'x' => 720, 'y' => 40,
        'cols' => [
            ['id', 'PK'],
            ['name', ''],
            ['username', 'UK'],
            ['email', 'UK'],
            ['email_verified_at', ''],
            ['contact_number', ''],
            ['department', ''],
            ['job_title', ''],
            ['location', ''],
            ['profile_photo_path', ''],
            ['birthday', ''],
            ['sex', ''],
            ['therapist_gender_preference', ''],
            ['is_pregnant', ''],
            ['pressure_preference', ''],
            ['profile_completed_at', ''],
            ['password', ''],
            ['role', ''],
            ['is_walk_in', 'IDX'],
            ['remember_token', ''],
            ['created_at', ''],
            ['updated_at', ''],
        ],
    ],
    'password_reset_tokens' => [
        'x' => 40, 'y' => 40,
        'cols' => [
            ['email', 'PK'],
            ['token', ''],
            ['created_at', ''],
        ],
    ],
    'sessions' => [
        'x' => 40, 'y' => 200,
        'cols' => [
            ['id', 'PK'],
            ['user_id', 'IDX→users'],
            ['ip_address', ''],
            ['user_agent', ''],
            ['payload', ''],
            ['last_activity', 'IDX'],
        ],
    ],
    'registrations' => [
        'x' => 40, 'y' => 420,
        'cols' => [
            ['id', 'PK'],
            ['user_id', 'FK→users'],
            ['name', ''],
            ['username', ''],
            ['email', ''],
            ['contact_number', ''],
            ['created_at', 'IDX'],
            ['updated_at', ''],
        ],
    ],
    'user_medications' => [
        'x' => 40, 'y' => 660,
        'cols' => [
            ['id', 'PK'],
            ['user_id', 'FK→users'],
            ['name', ''],
            ['sort_order', ''],
            ['created_at', ''],
            ['updated_at', ''],
        ],
    ],
    'customers' => [
        'x' => 40, 'y' => 880,
        'cols' => [
            ['id', 'PK'],
            ['customer_id', 'UK'],
            ['full_name', ''],
            ['birthday', ''],
            ['number', ''],
            ['email', 'UK'],
            ['password', ''],
            ['created_at', ''],
            ['updated_at', ''],
        ],
    ],
    'receptionists' => [
        'x' => 40, 'y' => 1140,
        'cols' => [
            ['id', 'PK'],
            ['receptionist_id', 'UK'],
            ['full_name', ''],
            ['username', 'UK'],
            ['email', 'UK'],
            ['address', ''],
            ['phone_number', ''],
            ['birthday', ''],
            ['shift', ''],
            ['profile_picture', ''],
            ['created_at', ''],
            ['updated_at', ''],
        ],
    ],
    'therapists' => [
        'x' => 1380, 'y' => 40,
        'cols' => [
            ['id', 'PK'],
            ['therapist_code', 'UK'],
            ['name', ''],
            ['role', ''],
            ['bio', ''],
            ['contact_number', ''],
            ['address', ''],
            ['email', ''],
            ['birthday', ''],
            ['avatar_initials', ''],
            ['photo_url', ''],
            ['landing_photo', ''],
            ['specializations', ''],
            ['certifications', ''],
            ['sessions_label', ''],
            ['accent_color', ''],
            ['status', ''],
            ['working_days', ''],
            ['day_off_until', ''],
            ['work_on_off_day', ''],
            ['total_hours', ''],
            ['rating', ''],
            ['service_hours_pct', ''],
            ['is_active', ''],
            ['sort_order', ''],
            ['created_at', ''],
            ['updated_at', ''],
        ],
    ],
    'spa_services' => [
        'x' => 1380, 'y' => 620,
        'cols' => [
            ['id', 'PK'],
            ['name', 'UK'],
            ['price_amount', ''],
            ['duration_minutes', ''],
            ['best_for', ''],
            ['description', ''],
            ['image', ''],
            ['prenatal_only', ''],
            ['is_active', ''],
            ['created_at', ''],
            ['updated_at', ''],
        ],
    ],
    'spa_bookings' => [
        'x' => 720, 'y' => 520,
        'cols' => [
            ['id', 'PK'],
            ['user_id', 'FK→users'],
            ['client_name', 'IDX'],
            ['booking_source', 'IDX'],
            ['service_name', 'LOG→spa_services'],
            ['therapist_name', 'LOG→therapists'],
            ['booking_date', ''],
            ['time_slot', ''],
            ['duration_minutes', ''],
            ['amount', ''],
            ['notes', ''],
            ['cancelled_at', ''],
            ['cancellation_reason', ''],
            ['rescheduled_at', ''],
            ['rescheduled_from_date', ''],
            ['rescheduled_from_time_slot', ''],
            ['completed_at', ''],
            ['session_started_at', ''],
            ['session_status', ''],
            ['created_at', ''],
            ['updated_at', ''],
        ],
    ],
    'transactions' => [
        'x' => 720, 'y' => 1120,
        'cols' => [
            ['id', 'PK'],
            ['spa_booking_id', 'FK→spa_bookings'],
            ['transaction_id', 'UK'],
            ['client_name', ''],
            ['user_id', 'FK→users'],
            ['therapist_id', 'LOG→therapists'],
            ['service_name', ''],
            ['date', ''],
            ['time', ''],
            ['duration', ''],
            ['amount', ''],
            ['notes', ''],
            ['created_at', ''],
            ['updated_at', ''],
        ],
    ],
    'customer_notifications' => [
        'x' => 1050, 'y' => 1120,
        'cols' => [
            ['id', 'PK'],
            ['user_id', 'FK→users'],
            ['spa_booking_id', 'FK→spa_bookings'],
            ['type', ''],
            ['title', ''],
            ['message', ''],
            ['details', ''],
            ['dedup_key', 'UK*'],
            ['read_at', ''],
            ['created_at', ''],
            ['updated_at', ''],
        ],
    ],
    'time_slots' => [
        'x' => 1380, 'y' => 920,
        'cols' => [
            ['id', 'PK'],
            ['label', 'UK'],
            ['sort_order', ''],
            ['is_active', ''],
            ['is_custom', ''],
            ['created_at', ''],
            ['updated_at', ''],
        ],
    ],
    'service_time_slots' => [
        'x' => 1720, 'y' => 920,
        'cols' => [
            ['id', 'PK'],
            ['service_name', 'UK* LOG→spa_services'],
            ['time_slot_id', 'FK→time_slots'],
            ['created_at', ''],
            ['updated_at', ''],
        ],
    ],
    'service_slot_date_overrides' => [
        'x' => 1720, 'y' => 1120,
        'cols' => [
            ['id', 'PK'],
            ['service_name', 'UK*'],
            ['slot_date', ''],
            ['time_slot_id', 'FK→time_slots'],
            ['is_enabled', ''],
            ['created_at', ''],
            ['updated_at', ''],
        ],
    ],
    'store_closures' => [
        'x' => 1720, 'y' => 1340,
        'cols' => [
            ['id', 'PK'],
            ['closure_date', 'UK'],
            ['note', ''],
            ['created_at', ''],
            ['updated_at', ''],
        ],
    ],
    'activity_logs' => [
        'x' => 380, 'y' => 1120,
        'cols' => [
            ['id', 'PK'],
            ['user_id', 'FK→users'],
            ['user_role', 'IDX'],
            ['user_name', ''],
            ['action', 'IDX'],
            ['subject_type', ''],
            ['subject_id', ''],
            ['description', ''],
            ['properties', ''],
            ['ip_address', ''],
            ['user_agent', ''],
            ['created_at', 'IDX'],
        ],
    ],
    'site_settings' => [
        'x' => 380, 'y' => 1480,
        'cols' => [
            ['id', 'PK'],
            ['key', 'UK'],
            ['value', ''],
            ['created_at', ''],
            ['updated_at', ''],
        ],
    ],
    'cache' => [
        'x' => 620, 'y' => 1480,
        'cols' => [
            ['key', 'PK'],
            ['value', ''],
            ['expiration', 'IDX'],
        ],
    ],
    'cache_locks' => [
        'x' => 820, 'y' => 1480,
        'cols' => [
            ['key', 'PK'],
            ['owner', ''],
            ['expiration', 'IDX'],
        ],
    ],
    'jobs' => [
        'x' => 1020, 'y' => 1480,
        'cols' => [
            ['id', 'PK'],
            ['queue', 'IDX'],
            ['payload', ''],
            ['attempts', ''],
            ['reserved_at', ''],
            ['available_at', ''],
            ['created_at', ''],
        ],
    ],
    'job_batches' => [
        'x' => 1220, 'y' => 1480,
        'cols' => [
            ['id', 'PK'],
            ['name', ''],
            ['total_jobs', ''],
            ['pending_jobs', ''],
            ['failed_jobs', ''],
            ['failed_job_ids', ''],
            ['options', ''],
            ['cancelled_at', ''],
            ['created_at', ''],
            ['finished_at', ''],
        ],
    ],
    'failed_jobs' => [
        'x' => 1420, 'y' => 1480,
        'cols' => [
            ['id', 'PK'],
            ['uuid', 'UK'],
            ['connection', ''],
            ['queue', ''],
            ['payload', ''],
            ['exception', ''],
            ['failed_at', ''],
        ],
    ],
];

$fkLinks = [
    ['registrations', 'users'],
    ['user_medications', 'users'],
    ['spa_bookings', 'users'],
    ['transactions', 'users'],
    ['transactions', 'spa_bookings'],
    ['customer_notifications', 'users'],
    ['customer_notifications', 'spa_bookings'],
    ['activity_logs', 'users'],
    ['service_time_slots', 'time_slots'],
    ['service_slot_date_overrides', 'time_slots'],
];

$logLinks = [
    ['sessions', 'users'],
    ['customers', 'users', 'email'],
    ['receptionists', 'users', 'email'],
    ['spa_bookings', 'spa_services', 'service_name'],
    ['spa_bookings', 'therapists', 'therapist_name'],
    ['transactions', 'therapists', 'therapist_id'],
    ['service_time_slots', 'spa_services', 'service_name'],
];

$boxW = 260;
$rowH = 16;
$headerH = 28;
$pad = 6;

function boxHeight(array $cols, int $headerH, int $rowH, int $pad): int
{
    return $headerH + (count($cols) * $rowH) + $pad;
}

function boxCenter(array $table, int $boxW, int $headerH, int $rowH, int $pad): array
{
    $h = boxHeight($table['cols'], $headerH, $rowH, $pad);

    return [
        'cx' => $table['x'] + ($boxW / 2),
        'cy' => $table['y'] + ($h / 2),
        'h' => $h,
    ];
}

$maxY = 0;
foreach ($tables as $t) {
    $maxY = max($maxY, $t['y'] + boxHeight($t['cols'], $headerH, $rowH, $pad));
}

$width = 2100;
$height = $maxY + 120;

$svg = [];
$svg[] = '<?xml version="1.0" encoding="UTF-8"?>';
$svg[] = sprintf('<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">', $width, $height, $width, $height);
$svg[] = '<defs>';
$svg[] = '<marker id="arrow" markerWidth="8" markerHeight="8" refX="6" refY="3" orient="auto"><path d="M0,0 L6,3 L0,6 Z" fill="#334155"/></marker>';
$svg[] = '<marker id="arrow-dash" markerWidth="8" markerHeight="8" refX="6" refY="3" orient="auto"><path d="M0,0 L6,3 L0,6 Z" fill="#64748b"/></marker>';
$svg[] = '</defs>';
$svg[] = '<rect width="100%" height="100%" fill="#f8fafc"/>';
$svg[] = '<text x="40" y="28" font-family="Segoe UI, Arial, sans-serif" font-size="22" font-weight="700" fill="#0f172a">TOUCHnRELIEF — Connected Database ERD (Full System)</text>';
$svg[] = '<text x="40" y="52" font-family="Segoe UI, Arial, sans-serif" font-size="12" fill="#475569">PK = Primary Key · FK = Foreign Key · UK = Unique · UK* = Composite Unique · IDX = Index · LOG = Logical link (app-level) · plain = regular column</text>';

// Relationship lines (behind boxes)
foreach ($fkLinks as [$from, $to]) {
    $a = boxCenter($tables[$from], $boxW, $headerH, $rowH, $pad);
    $b = boxCenter($tables[$to], $boxW, $headerH, $rowH, $pad);
    $svg[] = sprintf(
        '<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f" stroke="#334155" stroke-width="1.5" marker-end="url(#arrow)"/>',
        $a['cx'], $a['cy'], $b['cx'], $b['cy']
    );
}

foreach ($logLinks as $link) {
    [$from, $to, $label] = [$link[0], $link[1], $link[2] ?? ''];
    $a = boxCenter($tables[$from], $boxW, $headerH, $rowH, $pad);
    $b = boxCenter($tables[$to], $boxW, $headerH, $rowH, $pad);
    $mx = ($a['cx'] + $b['cx']) / 2;
    $my = ($a['cy'] + $b['cy']) / 2;
    $svg[] = sprintf(
        '<path d="M %.1f %.1f Q %.1f %.1f %.1f %.1f" fill="none" stroke="#64748b" stroke-width="1.2" stroke-dasharray="6 4" marker-end="url(#arrow-dash)"/>',
        $a['cx'], $a['cy'], $mx, $my - 40, $b['cx'], $b['cy']
    );
    if ($label !== '') {
        $svg[] = sprintf('<text x="%.1f" y="%.1f" font-family="Segoe UI, Arial, sans-serif" font-size="9" fill="#64748b">%s</text>', $mx, $my - 44, htmlspecialchars($label));
    }
}

// Entity boxes
foreach ($tables as $name => $table) {
    $h = boxHeight($table['cols'], $headerH, $rowH, $pad);
    $x = $table['x'];
    $y = $table['y'];

    $svg[] = sprintf('<rect x="%d" y="%d" width="%d" height="%d" rx="6" fill="#ffffff" stroke="#1e293b" stroke-width="1.5"/>', $x, $y, $boxW, $h);
    $svg[] = sprintf('<rect x="%d" y="%d" width="%d" height="%d" rx="6" fill="#1e293b"/>', $x, $y, $boxW, $headerH);
    $svg[] = sprintf('<rect x="%d" y="%d" width="%d" height="%d" fill="#1e293b"/>', $x, $y + ($headerH - 6), $boxW, 6);
    $svg[] = sprintf('<text x="%d" y="%d" font-family="Consolas, monospace" font-size="13" font-weight="700" fill="#ffffff">%s</text>', $x + 8, $y + 19, htmlspecialchars($name));

    $cy = $y + $headerH + 12;
    foreach ($table['cols'] as [$col, $key]) {
        $keyColor = match (true) {
            str_starts_with($key, 'PK') => '#b45309',
            str_starts_with($key, 'FK') => '#0369a1',
            str_starts_with($key, 'UK') => '#7c3aed',
            str_starts_with($key, 'IDX') => '#059669',
            str_starts_with($key, 'LOG') => '#64748b',
            default => '#334155',
        };
        $badge = $key !== '' ? $key : '—';
        $svg[] = sprintf('<text x="%d" y="%d" font-family="Consolas, monospace" font-size="10" fill="#334155">%s</text>', $x + 8, $cy, htmlspecialchars($col));
        $svg[] = sprintf('<text x="%d" y="%d" font-family="Consolas, monospace" font-size="9" font-weight="600" fill="%s" text-anchor="end">%s</text>', $x + $boxW - 8, $cy, $keyColor, htmlspecialchars($badge));
        $cy += $rowH;
    }
}

$svg[] = '</svg>';

$outSvg = __DIR__.'/database-erd-connected.svg';
file_put_contents($outSvg, implode("\n", $svg));
echo "Written: {$outSvg}\n";
