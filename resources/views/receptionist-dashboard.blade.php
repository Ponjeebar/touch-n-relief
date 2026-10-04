<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    @include('partials.theme-head')
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Receptionist dashboard overview">
    <title>Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/receptionist-dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}">
    @include('partials.staff-mobile-style')
</head>
<body data-staff-feed-poll-url="{{ route('staff-feed.poll') }}" data-staff-notifications-mark-all-url="{{ route('staff-notifications.mark-all-read') }}" data-staff-notification-read-url-template="{{ route('staff-notifications.read', ['staffNotification' => '__ID__']) }}">
    <div class="app-shell">
        <div class="dashboard">
            <aside class="sidebar">
                <div class="brand">
                    <span class="brand-logo" aria-hidden="false">
                        <img src="{{ asset('images/dashboard/logo.png') }}" alt="TOUCHnRELIEF logo" class="brand-logo-img">
                    </span>
                    <span class="brand-copy">
                        <span class="brand-text">TOUCHnRELIEF</span>
                        <span class="brand-subtext">Appointment and Record Management System</span>
                    </span>
                </div>
                @include('partials.sidebar-nav', ['active' => 'receptionist'])
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
                        <h1>Dashboard</h1>
                        <div class="subtitle">
                            Welcome, {{ auth()->user()->name ?? auth()->user()->email }}
                            <span class="subtitle-divider">·</span>
                            @include('partials.live-system-clock')
                        </div>
                    </div>

                    <div class="right">
                        <div class="actions" aria-label="Dashboard actions">
                            <div class="topbar-notifications" data-topbar-notif-sync>
                                <button
                                    class="icon-btn"
                                    type="button"
                                    aria-label="Notifications"
                                    aria-haspopup="true"
                                    aria-expanded="false"
                                    data-open-dashboard-notifications="true"
                                >
                                    <i class="bi bi-bell"></i>
                                </button>
                                <span class="topbar-notif-badge" data-page-notif-badge data-staff-notif-badge id="receptionist-notif-badge">0</span>
                            </div>
                            <aside class="dashboard-notifications hidden-section" id="dashboard-notifications" aria-label="Notifications panel">
                                <div class="dashboard-notifications-head">
                                    <h3>Notifications</h3>
                                    <button class="icon-btn slim" type="button" aria-label="Mark all as read" data-mark-dashboard-read="true" title="Mark all as read">
                                        <i class="bi bi-check2-all"></i>
                                    </button>
                                </div>
                                <div class="dashboard-notifications-list" data-staff-notifications-list>
                                    @forelse (collect($notifications ?? [])->take(6) as $note)
                                        @php($type = $note['type'] ?? 'system')
                                        <a
                                            class="dashboard-note dashboard-note-{{ $type }} {{ ($note['is_read'] ?? false) ? 'note-read' : 'note-unread' }}"
                                            data-notification-id="{{ $note['id'] ?? '' }}"
                                            data-notif-at="{{ $note['notification_at'] ?? '' }}"
                                            data-notif-key="{{ $note['notification_key'] ?? '' }}"
                                            href="{{ $note['url'] ?? route('appointments.index') }}"
                                        >
                                            <div class="dashboard-note-icon">
                                                @if ($type === 'confirmed')
                                                    <i class="bi bi-check-circle"></i>
                                                @elseif ($type === 'pending')
                                                    <i class="bi bi-hourglass-split"></i>
                                                @elseif ($type === 'cancelled')
                                                    <i class="bi bi-x-circle"></i>
                                                @elseif ($type === 'rescheduled')
                                                    <i class="bi bi-calendar2-event"></i>
                                                @else
                                                    <i class="bi bi-bell"></i>
                                                @endif
                                            </div>
                                            <div class="dashboard-note-body">
                                                <div class="dashboard-note-title">{{ $note['title'] ?? 'Notification' }}</div>
                                                <div class="dashboard-note-message">{{ $note['message'] ?? '' }}</div>
                                            </div>
                                        </a>
                                    @empty
                                        <div class="appointments-notif-empty">No notifications.</div>
                                    @endforelse
                                </div>
                            </aside>
                            @include('partials.topbar-settings')
                        </div>

                        @include('partials.topbar-profile')
                    </div>
                </div>

                @if (session('status'))
                    <div class="toast-success" id="status-toast" role="status" aria-live="polite">
                        <i class="bi bi-check-circle-fill"></i>
                        <span>{{ session('status') }}</span>
                        <button type="button" class="toast-close" aria-label="Close">&times;</button>
                    </div>
                @endif

                <section class="analytics staff-overview staff-overview--receptionist">
                    <header class="staff-overview-heading">
                        <div>
                            <p class="staff-overview-kicker">Front desk</p>
                            <h2>Today's service desk</h2>
                            <p>Keep arrivals, payments, therapists, and active sessions moving.</p>
                        </div>
                        <div class="staff-overview-actions">
                            <a class="staff-action-link staff-action-link--primary" href="{{ route('appointments.index') }}">Create appointment</a>
                            <a class="staff-action-link" href="{{ route('client-records.index') }}">Find client</a>
                        </div>
                    </header>

                    <div class="staff-summary" aria-label="Receptionist summary">
                        <dl class="staff-summary-item staff-summary-item--primary">
                            <dt>Today's appointments</dt>
                            <dd>{{ $appointmentsToday ?? 0 }}</dd>
                            <span>Still active today</span>
                        </dl>
                        <dl class="staff-summary-item">
                            <dt>Ongoing sessions</dt>
                            <dd data-ongoing-sessions-count>{{ $ongoingSessions ?? 0 }}</dd>
                            <span>Active right now</span>
                        </dl>
                        <dl class="staff-summary-item">
                            <dt>Upcoming appointments</dt>
                            <dd data-upcoming-appointments-count>{{ $upcomingAppointments ?? 0 }}</dd>
                            <span>From now onward</span>
                        </dl>
                        <dl class="staff-summary-item">
                            <dt>Active therapists</dt>
                            <dd>{{ $activeTherapists ?? 0 }}<small>/{{ $therapistCount ?? 0 }}</small></dd>
                            <span>Available staff</span>
                        </dl>
                    </div>

                    <div class="staff-workspace-grid staff-workspace-grid--frontdesk">
                        <section class="staff-workspace staff-workspace--primary" aria-labelledby="frontdesk-today-title">
                            <div class="staff-workspace-head">
                                <div>
                                    <h3 id="frontdesk-today-title">Today's appointments</h3>
                                    <p>Collect balances, reschedule, start sessions, or confirm no-shows from the full schedule.</p>
                                </div>
                                <a href="{{ route('appointments.index') }}">Open schedule</a>
                            </div>
                            <div class="session-list staff-operation-list" data-dashboard-today-appointments>
                                @forelse ($todayAppointments ?? [] as $appointment)
                                    <div class="session-item">
                                        <div class="session-main">
                                            <strong>{{ $appointment['client'] }}</strong>
                                            <span>{{ $appointment['service'] }} &middot; {{ $appointment['therapist'] ?? '' }}</span>
                                            @if (!empty($appointment['payment_summary']))
                                                <span class="session-payment">{{ $appointment['payment_summary'] }}</span>
                                            @endif
                                        </div>
                                        <div class="session-meta">
                                            <span class="meta-chip time">{{ $appointment['time'] }}</span>
                                            <span class="meta-chip {{ $appointment['status_class'] }}">{{ $appointment['status'] }}</span>
                                        </div>
                                    </div>
                                @empty
                                    <p class="staff-empty-state">No appointments scheduled for today.</p>
                                @endforelse
                            </div>
                        </section>

                        <section class="staff-workspace" aria-labelledby="frontdesk-sessions-title">
                            <div class="staff-workspace-head">
                                <div>
                                    <h3 id="frontdesk-sessions-title">Ongoing sessions</h3>
                                    <p>Current progress by customer and therapist.</p>
                                </div>
                                <a href="{{ route('ongoing-sessions.index') }}">Monitor</a>
                            </div>
                            <div class="session-list staff-operation-list">
                                @forelse ($currentSessions ?? [] as $session)
                                    <div class="session-item">
                                        <div class="session-main">
                                            <strong>{{ $session['client'] }}</strong>
                                            <span>{{ $session['service'] }} &middot; {{ $session['therapist'] ?? '' }}</span>
                                        </div>
                                        <div class="session-meta">
                                            <span class="meta-chip progress">{{ $session['progress'] }}</span>
                                            <span class="meta-chip {{ $session['phase_class'] }}">{{ $session['phase'] }}</span>
                                        </div>
                                    </div>
                                @empty
                                    <p class="staff-empty-state">No ongoing sessions right now.</p>
                                @endforelse
                            </div>
                        </section>
                    </div>
                </section>
            </main>
        </div>
    </div>
    <script>
        const statusToast = document.getElementById('status-toast');
        if (statusToast) {
            const closeBtn = statusToast.querySelector('.toast-close');
            const hideToast = () => statusToast.classList.add('hidden');
            closeBtn?.addEventListener('click', hideToast);
            window.setTimeout(hideToast, 3500);
        }

        const openDashboardNotificationsBtn = document.querySelector('[data-open-dashboard-notifications="true"]');
        const dashboardNotificationsPanel = document.getElementById('dashboard-notifications');
        const markDashboardReadBtn = document.querySelector('[data-mark-dashboard-read="true"]');
        const dashboardNoteItems = dashboardNotificationsPanel?.querySelectorAll('.dashboard-note');

        const pageBadge = document.querySelector('[data-page-notif-badge="true"], [data-page-notif-badge]');

        function updatePageBadge() {
            if (!pageBadge) return;
            const unreadCount = document.querySelectorAll('.dashboard-note.note-unread').length;
            pageBadge.textContent = String(unreadCount);

            if (markDashboardReadBtn) {
                markDashboardReadBtn.disabled = unreadCount === 0;
            }
        }

        updatePageBadge();

        dashboardNotificationsPanel?.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) return;
            const note = target.closest('[data-notif-key]');
            if (!(note instanceof HTMLElement)) return;
            note.classList.remove('note-unread');
            note.classList.add('note-read');
            updatePageBadge();
        });

        openDashboardNotificationsBtn?.addEventListener('click', (event) => {
            event.stopPropagation();
            if (!dashboardNotificationsPanel) return;
            const willOpen = dashboardNotificationsPanel.classList.contains('hidden-section');
            dashboardNotificationsPanel.classList.toggle('hidden-section');
            openDashboardNotificationsBtn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });

        document.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) return;
            if (!dashboardNotificationsPanel || dashboardNotificationsPanel.classList.contains('hidden-section')) return;

            const clickedBell = target.closest('[data-open-dashboard-notifications="true"]');
            const clickedPanel = target.closest('#dashboard-notifications');
            if (!clickedBell && !clickedPanel) {
                dashboardNotificationsPanel.classList.add('hidden-section');
                openDashboardNotificationsBtn?.setAttribute('aria-expanded', 'false');
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;
            dashboardNotificationsPanel?.classList.add('hidden-section');
            openDashboardNotificationsBtn?.setAttribute('aria-expanded', 'false');
        });

        markDashboardReadBtn?.addEventListener('click', () => {
            dashboardNotificationsPanel?.querySelectorAll('.dashboard-note').forEach((note) => {
                note.classList.remove('note-unread');
                note.classList.add('note-read');
            });
            updatePageBadge();
            markDashboardReadBtn.disabled = true;
            markDashboardReadBtn.setAttribute('aria-label', 'All notifications read');
            markDashboardReadBtn.title = 'All notifications read';
        });

    </script>
    <script src="{{ asset('js/staff-feed-poll.js') }}?v={{ filemtime(public_path('js/staff-feed-poll.js')) }}"></script>
    <script src="{{ asset('js/system-clock.js') }}"></script>
    <script>
        window.initSystemClock({
            serverNowIso: @json($serverNowIso ?? now()->toIso8601String()),
        });
    </script>
</body>
</html>
