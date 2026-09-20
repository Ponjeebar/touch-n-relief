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
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0"></script>
    <link rel="stylesheet" href="{{ asset('css/receptionist-dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}">
</head>
<body data-staff-feed-poll-url="{{ route('staff-feed.poll') }}">
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
                                            class="dashboard-note dashboard-note-{{ $type }} note-unread"
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

                <section class="analytics">
                    <div class="metric-grid receptionist-metric-grid">
                        <article class="metric-card">
                            <div class="metric-label">Ongoing Sessions</div>
                            <div class="metric-value" data-ongoing-sessions-count>{{ $ongoingSessions ?? 0 }}</div>
                            <div class="metric-sub">Active right now</div>
                        </article>
                        <article class="metric-card">
                            <div class="metric-label">Today's Sales</div>
                            <div class="metric-value">₱{{ $todaySales ?? 0 }}</div>
                            <div class="metric-sub">{{ $todayTransactions ?? 0 }} transactions</div>
                        </article>
                        <article class="metric-card">
                            <div class="metric-label">Active Therapists</div>
                            <div class="metric-value">{{ $activeTherapists ?? 0 }}</div>
                            <div class="metric-sub">of {{ $therapistCount ?? 0 }} total</div>
                        </article>
                        <article class="metric-card">
                            <div class="metric-label">Upcoming Appointments</div>
                            <div class="metric-value" data-appointments-today-count>{{ $appointmentsToday ?? 0 }}</div>
                            <div class="metric-sub">Scheduled bookings</div>
                        </article>
                    </div>

                    <div class="chart-grid">
                        <article class="data-card">
                            <div class="card-head">
                                <h3>Sessions Trend</h3>
                            </div>
                            <canvas id="sessionsChart" aria-label="Sessions trend chart"></canvas>
                        </article>
                        <article class="data-card">
                            <div class="card-head">
                                <h3>Sales by Service</h3>
                            </div>
                            <canvas id="salesChart" aria-label="Sales by service chart"></canvas>
                        </article>
                    </div>

                    <div class="table-grid">
                        <article class="data-card">
                            <div class="card-head">
                                <h3>Current Sessions</h3>
                            </div>
                            <div class="session-list">
                                @forelse ($currentSessions ?? [] as $session)
                                    <div class="session-item">
                                        <div class="session-main">
                                            <strong>{{ $session['client'] }}</strong>
                                            <span>{{ $session['service'] }} · {{ $session['therapist'] ?? '' }}</span>
                                        </div>
                                        <div class="session-meta">
                                            <span class="meta-chip progress">{{ $session['progress'] }}</span>
                                            <span class="meta-chip {{ $session['phase_class'] }}">{{ $session['phase'] }}</span>
                                        </div>
                                    </div>
                                @empty
                                    <p class="session-list-empty">No ongoing sessions right now.</p>
                                @endforelse
                            </div>
                        </article>

                        <article class="data-card">
                            <div class="card-head">
                                <h3>Today's Appointments</h3>
                            </div>
                            <div class="session-list" data-dashboard-today-appointments>
                                @forelse ($todayAppointments ?? [] as $appointment)
                                    <div class="session-item">
                                        <div class="session-main">
                                            <strong>{{ $appointment['client'] }}</strong>
                                            <span>{{ $appointment['service'] }} · {{ $appointment['therapist'] ?? '' }}</span>
                                            @if (!empty($appointment['payment_summary']) && $appointment['payment_summary'] !== '—')
                                                <span class="session-payment">{{ $appointment['payment_summary'] }}</span>
                                            @endif
                                        </div>
                                        <div class="session-meta">
                                            <span class="meta-chip time">{{ $appointment['time'] }}</span>
                                            <span class="meta-chip {{ $appointment['status_class'] }}">{{ $appointment['status'] }}</span>
                                        </div>
                                    </div>
                                @empty
                                    <p class="session-list-empty">No appointments scheduled for today.</p>
                                @endforelse
                            </div>
                        </article>
                    </div>
                </section>
            </main>
        </div>
    </div>
    <script>
        if (window.Chart) {
            const sessionsCtx = document.getElementById('sessionsChart');
            if (sessionsCtx) {
                new Chart(sessionsCtx, {
                    type: 'line',
                    data: {
                        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                        datasets: [{
                            label: 'Sessions',
                            data: [8, 11, 10, 14, 12, 9, 13],
                            borderColor: '#4f6d8c',
                            backgroundColor: 'rgba(79, 109, 140, 0.18)',
                            tension: 0.35,
                            fill: true,
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { grid: { display: false } },
                            y: { beginAtZero: true, ticks: { stepSize: 5 } }
                        }
                    }
                });
            }

            const salesCtx = document.getElementById('salesChart');
            if (salesCtx) {
                new Chart(salesCtx, {
                    type: 'doughnut',
                    data: {
                        labels: [
                            'Deep Tissue',
                            'Swedish Massage',
                            'Aromatherapy',
                            'Sports Massage',
                            'Hot Stone',
                            'Foot Reflexology',
                            'Prenatal Massage'
                        ],
                        datasets: [{
                            data: [35, 24, 21, 20, 15, 12, 9],
                            backgroundColor: ['#4f6d8c', '#2f8f83', '#2f9d62', '#c95a7b', '#f0a74d', '#7f8c8d', '#8e44ad'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    boxWidth: 14,
                                    boxHeight: 14,
                                    font: {
                                        size: 12
                                    }
                                }
                            },
                            datalabels: {
                                color: '#ffffff',
                                formatter: (value) => value,
                                font: {
                                    weight: '700',
                                    size: 14
                                }
                            }
                        },
                        cutout: '64%'
                    },
                    plugins: [ChartDataLabels]
                });
            }
        }

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

        const NOTIF_ALL_READ_KEY = 'tnrNotificationsAllReadAtV1';
        const NOTIF_READ_KEYS_KEY = 'tnrNotificationsReadKeysV1';

        function updatePageBadge() {
            if (!pageBadge) return;
            const unreadCount = document.querySelectorAll('.dashboard-note.note-unread').length;
            pageBadge.textContent = String(unreadCount);

            if (markDashboardReadBtn) {
                markDashboardReadBtn.disabled = unreadCount === 0;
            }
        }

        function getReadKeys() {
            try {
                const raw = localStorage.getItem(NOTIF_READ_KEYS_KEY);
                const parsed = raw ? JSON.parse(raw) : [];
                return new Set(Array.isArray(parsed) ? parsed.map(String) : []);
            } catch (e) {
                return new Set();
            }
        }

        function saveReadKeys(keys) {
            try {
                localStorage.setItem(NOTIF_READ_KEYS_KEY, JSON.stringify(Array.from(keys)));
            } catch (e) {}
        }

        function markNotificationReadByElement(el) {
            if (!(el instanceof HTMLElement)) return;
            const key = el.dataset.notifKey ?? '';
            if (!key) return;
            const keys = getReadKeys();
            keys.add(key);
            saveReadKeys(keys);
        }

        function applyReadStateFromLocalStorage() {
            const readKeys = getReadKeys();
            const raw = (() => {
                try {
                    return localStorage.getItem(NOTIF_ALL_READ_KEY);
                } catch (e) {
                    return null;
                }
            })();

            if (!raw && readKeys.size === 0) {
                updatePageBadge();
                return;
            }

            const readAtMs = raw ? new Date(raw).getTime() : Number.NaN;

            document.querySelectorAll('[data-notif-at]').forEach((el) => {
                const atRaw = el instanceof HTMLElement ? el.dataset.notifAt : '';
                const key = el instanceof HTMLElement ? (el.dataset.notifKey ?? '') : '';
                const atMs = atRaw ? new Date(atRaw).getTime() : Number.NaN;
                const byTime = Number.isFinite(readAtMs) && Number.isFinite(atMs) && atMs <= readAtMs;

                if (readKeys.has(key) || byTime) {
                    el.classList.remove('note-unread');
                    el.classList.add('note-read');
                } else {
                    el.classList.remove('note-read');
                    el.classList.add('note-unread');
                }
            });

            updatePageBadge();
        }

        window.addEventListener('storage', (event) => {
            if (event.key !== NOTIF_ALL_READ_KEY) return;
            applyReadStateFromLocalStorage();
        });

        applyReadStateFromLocalStorage();

        dashboardNotificationsPanel?.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) return;
            const note = target.closest('[data-notif-key]');
            if (!(note instanceof HTMLElement)) return;
            markNotificationReadByElement(note);
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
            try {
                localStorage.setItem(NOTIF_ALL_READ_KEY, new Date().toISOString());
            } catch (e) {}

            applyReadStateFromLocalStorage();
            markDashboardReadBtn.disabled = true;
            markDashboardReadBtn.setAttribute('aria-label', 'All notifications read');
            markDashboardReadBtn.title = 'All notifications read';
        });

    </script>
    <script src="{{ asset('js/staff-feed-poll.js') }}"></script>
    <script src="{{ asset('js/system-clock.js') }}"></script>
    <script>
        window.initSystemClock({
            serverNowIso: @json($serverNowIso ?? now()->toIso8601String()),
        });
    </script>
</body>
</html>
