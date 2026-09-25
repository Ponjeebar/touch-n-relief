<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    @include('partials.theme-head')
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Admin dashboard overview">
    <title>Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0"></script>
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
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
                @include('partials.sidebar-nav', ['active' => 'dashboard', 'spaDashboard' => true])
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
                                <span class="topbar-notif-badge" data-page-notif-badge data-staff-notif-badge id="dashboard-notif-badge">0</span>
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

                <nav class="mobile-dashboard-actions" aria-label="Dashboard quick actions">
                    <a href="{{ route('appointments.index') }}"><i class="bi bi-calendar-plus" aria-hidden="true"></i>Appointments</a>
                    <a href="{{ route('client-records.index') }}"><i class="bi bi-folder2-open" aria-hidden="true"></i>Client records</a>
                    <a href="{{ route('reporting.index') }}"><i class="bi bi-bar-chart-line" aria-hidden="true"></i>Reports</a>
                </nav>

                <section class="analytics">
                    <div class="metric-grid dashboard-block">
                        <article class="metric-card">
                            <div class="metric-label">Total Users</div>
                            <div class="metric-value">{{ $totalUsers ?? 128 }}</div>
                            <div class="metric-sub">Registered accounts</div>
                            <div class="metric-graph">
                                <canvas id="usersMiniChart" aria-label="Total users mini trend chart"></canvas>
                            </div>
                        </article>
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
                            <div class="metric-value" data-upcoming-appointments-count>{{ $upcomingAppointments ?? 0 }}</div>
                            <div class="metric-sub">Scheduled bookings</div>
                        </article>
                    </div>

                    <div class="chart-grid dashboard-block">
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

                    <div class="table-grid dashboard-block">
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

                    <article class="data-card users-panel hidden-section" id="users-view">
                        <div class="card-head users-head">
                            <h3>Receptionist Users</h3>
                            <button class="add-user-btn" type="button" id="toggle-add-receptionist"><i class="bi bi-plus-circle"></i> Add Receptionist</button>
                        </div>

                        <div class="user-list">
                            @forelse ($receptionists as $receptionist)
                                <div class="user-row">
                                    <div class="user-profile">
                                        @if ($receptionist->profile_picture)
                                            <img class="user-avatar-img" src="{{ public_storage_url($receptionist->profile_picture) }}" alt="{{ $receptionist->username }}">
                                        @else
                                            <div class="user-avatar">{{ strtoupper(substr($receptionist->username, 0, 2)) }}</div>
                                        @endif
                                        <div class="user-main">
                                            <strong>{{ $receptionist->full_name ?? $receptionist->username }}</strong>
                                            <span>Receptionist</span>
                                            <span class="user-id">Receptionist ID: {{ $receptionist->receptionist_id }}</span>
                                        </div>
                                    </div>
                                    <div class="user-info-grid">
                                        <div class="info-group">
                                            <span class="info-label">Email</span>
                                            <span class="info-value">{{ $receptionist->email }}</span>
                                        </div>
                                        <div class="info-group">
                                            <span class="info-label">Shift</span>
                                            <span class="info-value">{{ $receptionist->shift }}</span>
                                        </div>
                                    </div>
                                    <div class="user-meta">
                                        <button
                                            class="user-action view-profile-btn"
                                            type="button"
                                            data-full-name="{{ $receptionist->full_name ?? $receptionist->username }}"
                                            data-username="{{ $receptionist->username }}"
                                            data-receptionist-id="{{ $receptionist->receptionist_id }}"
                                            data-email="{{ $receptionist->email }}"
                                            data-shift="{{ $receptionist->shift }}"
                                            data-profile-picture="{{ public_storage_url($receptionist->profile_picture) ?? '' }}"
                                        >
                                            View Profile
                                        </button>
                                        <button
                                            class="user-action edit edit-receptionist-btn"
                                            type="button"
                                            data-update-url="{{ route('dashboard.receptionists.update', $receptionist) }}"
                                            data-full-name="{{ $receptionist->full_name ?? '' }}"
                                            data-username="{{ $receptionist->username }}"
                                            data-email="{{ $receptionist->email }}"
                                            data-shift="{{ $receptionist->shift }}"
                                            data-receptionist-id="{{ $receptionist->receptionist_id }}"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            class="user-action delete delete-receptionist-btn"
                                            type="button"
                                            data-delete-url="{{ route('dashboard.receptionists.destroy', $receptionist) }}"
                                            data-receptionist-name="{{ $receptionist->full_name ?? $receptionist->username }}"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </div>
                            @empty
                                <div class="empty-users">No receptionist users yet. Add one above.</div>
                            @endforelse
                        </div>
                    </article>

                    <div class="profile-modal hidden-section" id="profile-modal" role="dialog" aria-modal="true" aria-labelledby="profile-modal-title">
                        <div class="profile-modal-backdrop" data-close-modal="true"></div>
                        <div class="profile-modal-content">
                            <button class="profile-modal-close" type="button" id="close-profile-modal" aria-label="Close">&times;</button>
                            <h3 class="profile-modal-title" id="profile-modal-title">Receptionist Profile</h3>
                            <div class="profile-modal-grid">
                                <div class="profile-photo-wrap">
                                    <img id="modal-profile-image" class="profile-photo hidden-section" src="" alt="Receptionist profile photo">
                                    <div id="modal-profile-fallback" class="profile-photo-fallback">RP</div>
                                </div>
                                <div class="profile-info-wrap">
                                    <div class="profile-field">
                                        <label>Full name</label>
                                        <input id="modal-full-name" type="text" readonly>
                                    </div>
                                    <div class="profile-field two-col">
                                        <div>
                                            <label>Username</label>
                                            <input id="modal-username" type="text" readonly>
                                        </div>
                                        <div>
                                            <label>Receptionist ID</label>
                                            <input id="modal-receptionist-id" type="text" readonly>
                                        </div>
                                    </div>
                                    <div class="profile-field two-col">
                                        <div>
                                            <label>Email</label>
                                            <input id="modal-email" type="text" readonly>
                                        </div>
                                        <div>
                                            <label>Shift</label>
                                            <input id="modal-shift" type="text" readonly>
                                        </div>
                                    </div>
                                    <div class="profile-modal-actions">
                                        <button type="button" class="user-action" id="profile-modal-cancel">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="profile-modal hidden-section" id="add-receptionist-modal" role="dialog" aria-modal="true" aria-labelledby="add-receptionist-title">
                        <div class="profile-modal-backdrop" data-close-add-modal="true"></div>
                        <div class="profile-modal-content add-modal-content">
                            <button class="profile-modal-close" type="button" id="close-add-receptionist-modal" aria-label="Close">&times;</button>
                            <h3 class="profile-modal-title" id="add-receptionist-title">Add Receptionist</h3>
                            <form class="add-receptionist-modal-form" method="POST" action="{{ route('dashboard.receptionists.store', ['section' => 'users']) }}" enctype="multipart/form-data">
                                @csrf
                                <div class="add-modal-grid">
                                    <div class="profile-field">
                                        <label>Full Name</label>
                                        <input type="text" name="full_name" value="{{ old('full_name') }}" required>
                                    </div>
                                    <div class="profile-field">
                                        <label>Username</label>
                                        <input type="text" name="username" value="{{ old('username') }}" required>
                                    </div>
                                    <div class="profile-field">
                                        <label>Email</label>
                                        <input type="email" name="email" value="{{ old('email') }}" required>
                                    </div>
                                    <div class="profile-field">
                                        <label>Shift</label>
                                        <input type="text" name="shift" value="{{ old('shift') }}" placeholder="8:00 AM - 5:00 PM" required>
                                    </div>
                                    <div class="profile-field full-row">
                                        <label>Profile Picture</label>
                                        <input type="file" name="profile_picture" accept="image/*">
                                    </div>
                                </div>
                                <div class="profile-modal-actions">
                                    <button type="button" class="user-action" id="add-modal-cancel">Cancel</button>
                                    <button type="submit" class="user-action add">Save Receptionist</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="profile-modal hidden-section" id="edit-receptionist-modal" role="dialog" aria-modal="true" aria-labelledby="edit-receptionist-title">
                        <div class="profile-modal-backdrop" data-close-edit-modal="true"></div>
                        <div class="profile-modal-content add-modal-content">
                            <button class="profile-modal-close" type="button" id="close-edit-receptionist-modal" aria-label="Close">&times;</button>
                            <h3 class="profile-modal-title" id="edit-receptionist-title">Edit Receptionist</h3>
                            <form class="add-receptionist-modal-form" id="edit-receptionist-form" method="POST" action="" enctype="multipart/form-data">
                                @csrf
                                @method('PUT')
                                <div class="add-modal-grid">
                                    <div class="profile-field">
                                        <label>Receptionist ID</label>
                                        <input type="text" id="edit-modal-receptionist-id" readonly>
                                    </div>
                                    <div class="profile-field">
                                        <label>Full Name</label>
                                        <input type="text" name="full_name" id="edit-modal-full-name" required>
                                    </div>
                                    <div class="profile-field">
                                        <label>Username</label>
                                        <input type="text" name="username" id="edit-modal-username" required>
                                    </div>
                                    <div class="profile-field">
                                        <label>Email</label>
                                        <input type="email" name="email" id="edit-modal-email" required>
                                    </div>
                                    <div class="profile-field">
                                        <label>Shift</label>
                                        <input type="text" name="shift" id="edit-modal-shift" required>
                                    </div>
                                    <div class="profile-field full-row">
                                        <label>Profile Picture</label>
                                        <input type="file" name="profile_picture" accept="image/*">
                                    </div>
                                </div>
                                <div class="profile-modal-actions">
                                    <button type="button" class="user-action" id="edit-modal-cancel">Cancel</button>
                                    <button type="submit" class="user-action edit">Update Receptionist</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="profile-modal hidden-section" id="delete-receptionist-modal" role="dialog" aria-modal="true" aria-labelledby="delete-receptionist-title">
                        <div class="profile-modal-backdrop" data-close-delete-modal="true"></div>
                        <div class="profile-modal-content delete-confirm-content">
                            <button class="profile-modal-close" type="button" aria-label="Close" data-close-delete-modal="true">&times;</button>
                            <div class="delete-confirm-head">
                                <div class="delete-confirm-icon" aria-hidden="true"><i class="bi bi-trash3"></i></div>
                                <div>
                                    <h3 class="profile-modal-title" id="delete-receptionist-title">Delete Receptionist</h3>
                                    <p class="delete-confirm-sub">This will permanently remove this account from the system.</p>
                                </div>
                            </div>
                            <p class="delete-confirm-copy">
                                Are you sure you want to delete <strong id="delete-receptionist-name">this receptionist</strong>? This action cannot be undone.
                            </p>
                            <div class="profile-modal-actions delete-confirm-actions">
                                <button type="button" class="user-action" data-close-delete-modal="true">Cancel</button>
                                <button type="button" class="user-action delete" id="delete-receptionist-confirm">
                                    <i class="bi bi-trash3" aria-hidden="true"></i>
                                    Delete Receptionist
                                </button>
                            </div>
                        </div>
                    </div>

                    <form id="delete-receptionist-form" method="POST" action="" class="hidden-section">
                        @csrf
                        @method('DELETE')
                    </form>
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

            const usersMiniCtx = document.getElementById('usersMiniChart');
            if (usersMiniCtx) {
                new Chart(usersMiniCtx, {
                    type: 'line',
                    data: {
                        labels: ['W1', 'W2', 'W3', 'W4', 'W5', 'W6'],
                        datasets: [{
                            data: [88, 96, 104, 112, 121, 128],
                            borderColor: '#2f8f83',
                            backgroundColor: 'rgba(47, 143, 131, 0.14)',
                            tension: 0.4,
                            fill: true,
                            borderWidth: 2,
                            pointRadius: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false }, tooltip: { enabled: false } },
                        scales: {
                            x: { display: false },
                            y: { display: false }
                        },
                        elements: {
                            line: { capBezierPoints: true }
                        }
                    }
                });
            }
        }

        const dashboardNavLink = document.getElementById('dashboard-nav-link');
        const usersNavLink = document.getElementById('users-nav-link');
        const dashboardBlocks = document.querySelectorAll('.dashboard-block');
        const usersView = document.getElementById('users-view');
        const pageTitle = document.querySelector('.welcome h1');
        const toggleAddReceptionist = document.getElementById('toggle-add-receptionist');
        const profileModal = document.getElementById('profile-modal');
        const closeProfileModal = document.getElementById('close-profile-modal');
        const profileModalCancel = document.getElementById('profile-modal-cancel');
        const addReceptionistModal = document.getElementById('add-receptionist-modal');
        const closeAddReceptionistModal = document.getElementById('close-add-receptionist-modal');
        const addModalCancel = document.getElementById('add-modal-cancel');
        const editReceptionistModal = document.getElementById('edit-receptionist-modal');
        const closeEditReceptionistModal = document.getElementById('close-edit-receptionist-modal');
        const editModalCancel = document.getElementById('edit-modal-cancel');
        const editReceptionistForm = document.getElementById('edit-receptionist-form');
        const editModalReceptionistId = document.getElementById('edit-modal-receptionist-id');
        const editModalFullName = document.getElementById('edit-modal-full-name');
        const editModalUsername = document.getElementById('edit-modal-username');
        const editModalEmail = document.getElementById('edit-modal-email');
        const editModalShift = document.getElementById('edit-modal-shift');
        const modalProfileImage = document.getElementById('modal-profile-image');
        const modalProfileFallback = document.getElementById('modal-profile-fallback');
        const modalFullName = document.getElementById('modal-full-name');
        const modalUsername = document.getElementById('modal-username');
        const modalReceptionistId = document.getElementById('modal-receptionist-id');
        const modalEmail = document.getElementById('modal-email');
        const modalShift = document.getElementById('modal-shift');
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

        function showDashboardView() {
            dashboardBlocks.forEach((block) => block.classList.remove('hidden-section'));
            usersView?.classList.add('hidden-section');
            dashboardNavLink?.classList.add('active');
            usersNavLink?.classList.remove('active');
            if (pageTitle) pageTitle.textContent = 'Dashboard';
        }

        function showUsersView() {
            dashboardBlocks.forEach((block) => block.classList.add('hidden-section'));
            usersView?.classList.remove('hidden-section');
            usersView?.classList.add('users-only-view');
            dashboardNavLink?.classList.remove('active');
            usersNavLink?.classList.add('active');
            if (pageTitle) pageTitle.textContent = 'Users';
        }

        dashboardNavLink?.addEventListener('click', (event) => {
            event.preventDefault();
            showDashboardView();
        });

        usersNavLink?.addEventListener('click', (event) => {
            event.preventDefault();
            showUsersView();
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

        function openAddReceptionistModal() {
            addReceptionistModal?.classList.remove('hidden-section');
            document.body.classList.add('modal-open');
        }

        function hideAddReceptionistModal() {
            addReceptionistModal?.classList.add('hidden-section');
            document.body.classList.remove('modal-open');
        }

        toggleAddReceptionist?.addEventListener('click', openAddReceptionistModal);
        closeAddReceptionistModal?.addEventListener('click', hideAddReceptionistModal);
        addModalCancel?.addEventListener('click', hideAddReceptionistModal);
        addReceptionistModal?.addEventListener('click', (event) => {
            const target = event.target;
            if (target instanceof HTMLElement && target.dataset.closeAddModal === 'true') {
                hideAddReceptionistModal();
            }
        });

        function openEditReceptionistModal(button) {
            const updateUrl = button.getAttribute('data-update-url') ?? '';
            if (editReceptionistForm && updateUrl) {
                editReceptionistForm.action = updateUrl;
            }

            if (editModalReceptionistId) editModalReceptionistId.value = button.getAttribute('data-receptionist-id') ?? '';
            if (editModalFullName) editModalFullName.value = button.getAttribute('data-full-name') ?? '';
            if (editModalUsername) editModalUsername.value = button.getAttribute('data-username') ?? '';
            if (editModalEmail) editModalEmail.value = button.getAttribute('data-email') ?? '';
            if (editModalShift) editModalShift.value = button.getAttribute('data-shift') ?? '';

            editReceptionistModal?.classList.remove('hidden-section');
            document.body.classList.add('modal-open');
        }

        function hideEditReceptionistModal() {
            editReceptionistModal?.classList.add('hidden-section');
            document.body.classList.remove('modal-open');
        }

        document.querySelectorAll('.edit-receptionist-btn').forEach((button) => {
            button.addEventListener('click', () => openEditReceptionistModal(button));
        });
        closeEditReceptionistModal?.addEventListener('click', hideEditReceptionistModal);
        editModalCancel?.addEventListener('click', hideEditReceptionistModal);
        editReceptionistModal?.addEventListener('click', (event) => {
            const target = event.target;
            if (target instanceof HTMLElement && target.dataset.closeEditModal === 'true') {
                hideEditReceptionistModal();
            }
        });

        const deleteReceptionistModal = document.getElementById('delete-receptionist-modal');
        const deleteReceptionistForm = document.getElementById('delete-receptionist-form');
        const deleteReceptionistName = document.getElementById('delete-receptionist-name');
        const deleteReceptionistConfirm = document.getElementById('delete-receptionist-confirm');

        function openDeleteReceptionistModal(button) {
            const url = button.getAttribute('data-delete-url') ?? '';
            const name = button.getAttribute('data-receptionist-name') ?? 'this receptionist';
            if (deleteReceptionistForm && url) deleteReceptionistForm.action = url;
            if (deleteReceptionistName) deleteReceptionistName.textContent = name;
            deleteReceptionistModal?.classList.remove('hidden-section');
            document.body.classList.add('modal-open');
            deleteReceptionistConfirm?.focus();
        }

        function closeDeleteReceptionistModal() {
            deleteReceptionistModal?.classList.add('hidden-section');
            document.body.classList.remove('modal-open');
            if (deleteReceptionistForm) deleteReceptionistForm.action = '';
        }

        document.querySelectorAll('.delete-receptionist-btn').forEach((button) => {
            button.addEventListener('click', () => openDeleteReceptionistModal(button));
        });
        deleteReceptionistModal?.querySelectorAll('[data-close-delete-modal]').forEach((el) => {
            el.addEventListener('click', closeDeleteReceptionistModal);
        });
        deleteReceptionistConfirm?.addEventListener('click', () => deleteReceptionistForm?.submit());

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && deleteReceptionistModal && !deleteReceptionistModal.classList.contains('hidden-section')) {
                closeDeleteReceptionistModal();
            }
        });

        function openProfileModal(button) {
            const fullName = button.getAttribute('data-full-name') ?? '';
            const username = button.getAttribute('data-username') ?? '';
            const receptionistId = button.getAttribute('data-receptionist-id') ?? '';
            const email = button.getAttribute('data-email') ?? '';
            const shift = button.getAttribute('data-shift') ?? '';
            const profilePicture = button.getAttribute('data-profile-picture') ?? '';

            if (modalFullName) modalFullName.value = fullName;
            if (modalUsername) modalUsername.value = username;
            if (modalReceptionistId) modalReceptionistId.value = receptionistId;
            if (modalEmail) modalEmail.value = email;
            if (modalShift) modalShift.value = shift;

            if (profilePicture && modalProfileImage && modalProfileFallback) {
                modalProfileImage.src = profilePicture;
                modalProfileImage.classList.remove('hidden-section');
                modalProfileFallback.classList.add('hidden-section');
            } else if (modalProfileFallback && modalProfileImage) {
                modalProfileFallback.textContent = (username || 'RP').substring(0, 2).toUpperCase();
                modalProfileFallback.classList.remove('hidden-section');
                modalProfileImage.classList.add('hidden-section');
            }

            profileModal?.classList.remove('hidden-section');
            document.body.classList.add('modal-open');
        }

        function hideProfileModal() {
            profileModal?.classList.add('hidden-section');
            document.body.classList.remove('modal-open');
        }

        document.querySelectorAll('.view-profile-btn').forEach((button) => {
            button.addEventListener('click', () => openProfileModal(button));
        });
        closeProfileModal?.addEventListener('click', hideProfileModal);
        profileModalCancel?.addEventListener('click', hideProfileModal);
        profileModal?.addEventListener('click', (event) => {
            const target = event.target;
            if (target instanceof HTMLElement && target.dataset.closeModal === 'true') {
                hideProfileModal();
            }
        });

        const params = new URLSearchParams(window.location.search);
        if (params.get('section') === 'users' || params.has('profile') || params.has('edit')) {
            showUsersView();
        }
        @if ($errors->any())
            showUsersView();
            openAddReceptionistModal();
        @endif

        const statusToast = document.getElementById('status-toast');
        if (statusToast) {
            const closeBtn = statusToast.querySelector('.toast-close');
            const hideToast = () => statusToast.classList.add('hidden');
            closeBtn?.addEventListener('click', hideToast);
            window.setTimeout(hideToast, 3500);
        }
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
