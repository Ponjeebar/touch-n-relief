<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    @include('partials.theme-head')
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Configure TouchNRelief booking and operational rules">
    <title>System Settings</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/landing-settings.css') }}">
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}">
    @include('partials.staff-mobile-style')
</head>
<body>
    <div class="app-shell">
        <div class="dashboard">
            <aside class="sidebar">
                <div class="brand">
                    <span class="brand-logo"><img src="{{ asset('images/dashboard/logo.png') }}" alt="TOUCHnRELIEF logo" class="brand-logo-img"></span>
                    <span class="brand-copy">
                        <span class="brand-text">TOUCHnRELIEF</span>
                        <span class="brand-subtext">Appointment and Record Management System</span>
                    </span>
                </div>
                @include('partials.sidebar-nav', ['active' => 'system-settings'])
                <div class="sidebar-footer">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="logout" type="submit"><span class="nav-icon"><i class="bi bi-box-arrow-right"></i></span><span class="nav-text">Logout</span></button>
                    </form>
                </div>
            </aside>

            <main class="main">
                <div class="topbar">
                    <div class="welcome">
                        @include('partials.topbar-panel-badge')
                        <h1>System Settings</h1>
                        <div class="subtitle">Configure booking, attendance, account protection, and backup rules</div>
                    </div>
                    <div class="right">
                        @include('partials.topbar-notifications')
                        @include('partials.topbar-settings')
                        @include('partials.topbar-profile')
                    </div>
                </div>

                @include('partials.status-toast')

                <section class="ls-wrap system-settings-wrap" aria-label="System settings">
                    <div class="ls-tip">
                        <span class="ls-tip-icon" aria-hidden="true"><i class="bi bi-sliders"></i></span>
                        <div class="ls-tip-copy">
                            <strong>Operational rules</strong>
                            <p>Saved values are used by the actual customer booking, payment hold, attendance, account restriction, and backup workflows. Changes apply when each workflow is checked again.</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('system-settings.update') }}" class="ls-form" id="systemSettingsForm">
                        @csrf
                        @method('PUT')

                        <div class="system-settings-grid">
                            <article class="ls-section">
                                <header class="ls-section-head">
                                    <span class="ls-section-icon hours" aria-hidden="true"><i class="bi bi-calendar2-check"></i></span>
                                    <div>
                                        <h2 class="ls-section-title">Booking and payment</h2>
                                        <p class="ls-section-desc">Controls when customers may change appointments and how long online reservations remain held.</p>
                                    </div>
                                </header>
                                <div class="ls-fields system-settings-fields">
                                    @include('system-settings.partials.number-field', ['name' => 'cancellation_cutoff_hours', 'label' => 'Cancellation and rescheduling cutoff', 'unit' => 'hours', 'min' => 0, 'max' => 8760, 'help' => 'Both customer actions disappear inside this period. Enter 1 for a one-hour cutoff.'])
                                    @include('system-settings.partials.number-field', ['name' => 'payment_hold_minutes', 'label' => 'Payment hold', 'unit' => 'minutes', 'min' => 5, 'max' => 30, 'help' => 'An unpaid PayMongo reservation releases after this period.'])
                                    @include('system-settings.partials.number-field', ['name' => 'customer_minimum_lead_minutes', 'label' => 'Minimum online booking lead time', 'unit' => 'minutes', 'min' => 6, 'max' => 1440, 'help' => 'Must be longer than the payment hold so an expired slot can still be booked by someone else.'])
                                </div>
                            </article>

                            <article class="ls-section">
                                <header class="ls-section-head">
                                    <span class="ls-section-icon contact" aria-hidden="true"><i class="bi bi-person-check"></i></span>
                                    <div>
                                        <h2 class="ls-section-title">Attendance and No Show</h2>
                                        <p class="ls-section-desc">Controls the late period, staff review window, and repeated No Show account restriction.</p>
                                    </div>
                                </header>
                                <div class="ls-fields system-settings-fields">
                                    @include('system-settings.partials.number-field', ['name' => 'late_grace_minutes', 'label' => 'Late grace period', 'unit' => 'minutes', 'min' => 1, 'max' => 60, 'help' => 'Staff may start the scheduled session during this period.'])
                                    @include('system-settings.partials.number-field', ['name' => 'no_show_review_minutes', 'label' => 'Staff review window', 'unit' => 'minutes', 'min' => 1, 'max' => 60, 'help' => 'After the grace period, staff receive this additional time before automatic No Show.'])
                                    @include('system-settings.partials.number-field', ['name' => 'no_show_restriction_threshold', 'label' => 'No Show restriction threshold', 'unit' => 'recorded No Shows', 'min' => 1, 'max' => 10, 'help' => 'The customer account is restricted when it reaches this number.'])
                                </div>
                            </article>

                            <article class="ls-section">
                                <header class="ls-section-head">
                                    <span class="ls-section-icon hours" aria-hidden="true"><i class="bi bi-shield-check"></i></span>
                                    <div>
                                        <h2 class="ls-section-title">Reservation abuse protection</h2>
                                        <p class="ls-section-desc">Temporarily pauses online booking for accounts that repeatedly let payment holds expire.</p>
                                    </div>
                                </header>
                                <div class="ls-fields system-settings-fields">
                                    @include('system-settings.partials.number-field', ['name' => 'expired_hold_limit', 'label' => 'Expired hold limit', 'unit' => 'holds', 'min' => 1, 'max' => 10, 'help' => 'Number of expired holds that triggers the cooldown.'])
                                    @include('system-settings.partials.number-field', ['name' => 'expired_hold_lookback_hours', 'label' => 'Expired hold lookback', 'unit' => 'hours', 'min' => 1, 'max' => 168, 'help' => 'Only expired holds inside this period count toward the limit.'])
                                    @include('system-settings.partials.number-field', ['name' => 'expired_hold_cooldown_minutes', 'label' => 'Online booking cooldown', 'unit' => 'minutes', 'min' => 15, 'max' => 1440, 'help' => 'How long online booking remains paused after the limit is reached.'])
                                </div>
                            </article>

                            <article class="ls-section">
                                <header class="ls-section-head">
                                    <span class="ls-section-icon contact" aria-hidden="true"><i class="bi bi-database-check"></i></span>
                                    <div>
                                        <h2 class="ls-section-title">Backup retention</h2>
                                        <p class="ls-section-desc">Controls automatic cleanup of older Google Drive backups.</p>
                                    </div>
                                </header>
                                <div class="ls-fields system-settings-fields">
                                    @include('system-settings.partials.number-field', ['name' => 'backup_retention_days', 'label' => 'Keep backups for', 'unit' => 'days', 'min' => 1, 'max' => 365, 'help' => 'Backups older than this are removed after a successful Google Drive backup.'])
                                </div>
                            </article>
                        </div>

                        <div class="ls-actions">
                            <button type="submit" class="ls-btn ls-btn-primary">
                                <i class="bi bi-check2-circle" aria-hidden="true"></i>
                                Save system settings
                            </button>
                            <p class="system-settings-note">Existing appointments are not rewritten. The current values are applied when eligibility, payment expiration, or attendance is evaluated.</p>
                        </div>
                    </form>
                </section>
            </main>
        </div>
    </div>

    @include('partials.logout-confirm-modal')
</body>
</html>
