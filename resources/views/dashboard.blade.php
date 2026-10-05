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

                <section class="analytics staff-overview staff-overview--admin">
                    <header class="staff-overview-heading">
                        <div>
                            <p class="staff-overview-kicker">Business overview</p>
                            <h2>Today at TouchNRelief</h2>
                            <p>Review collections and current operations before opening the detailed reports.</p>
                        </div>
                        <div class="staff-overview-actions">
                            <a class="staff-action-link staff-action-link--primary" href="{{ route('reporting.index') }}">Open sales report</a>
                            <a class="staff-action-link" href="{{ route('appointments.index') }}">Manage appointments</a>
                        </div>
                    </header>

                    <div class="staff-summary" aria-label="Administrator summary">
                        <dl class="staff-summary-item staff-summary-item--primary">
                            <dt>Completed service value today</dt>
                            <dd>&#8369;{{ $todaySales ?? 0 }}</dd>
                            <span>{{ $todayTransactions ?? 0 }} completed transactions</span>
                        </dl>
                        <dl class="staff-summary-item">
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
                            <dt>Available therapists</dt>
                            <dd>{{ $activeTherapists ?? 0 }}<small>/{{ $therapistCount ?? 0 }}</small></dd>
                            <span>Available staff</span>
                        </dl>
                        <dl class="staff-summary-item">
                            <dt>Receptionist accounts</dt>
                            <dd>{{ $totalUsers ?? 0 }}</dd>
                            <span>Active accounts</span>
                        </dl>
                    </div>

                    <figure class="staff-sales-trend" aria-labelledby="dashboard-sales-trend-title">
                        <figcaption class="staff-sales-trend-head">
                            <div>
                                <h3 id="dashboard-sales-trend-title">7-day net sales</h3>
                                <p>Collected payments and memberships, less refunds &middot; {{ $salesTrend['rangeLabel'] }}</p>
                            </div>
                            <a href="{{ route('reporting.index', ['period' => 'weekly']) }}">View detailed report</a>
                        </figcaption>

                        @if ($salesTrend['hasActivity'])
                            <div class="staff-sales-bars" role="img" aria-label="Net sales for the last seven days">
                                @foreach ($salesTrend['days'] as $day)
                                    @php($barSize = abs($day['amount']) / $salesTrend['maxAbsolute'] * 46)
                                    @php($barClass = $day['amount'] < 0 ? ' is-negative' : ($day['amount'] == 0 ? ' is-zero' : ''))
                                    <div class="staff-sales-day" data-sales-date="{{ $day['date'] }}" data-sales-amount="{{ number_format($day['amount'], 2, '.', '') }}">
                                        <div class="staff-sales-plot" aria-hidden="true">
                                            <span class="staff-sales-bar{{ $barClass }}" style="--bar-size: {{ number_format($barSize, 2, '.', '') }}%;"></span>
                                        </div>
                                        <strong>{{ $day['label'] }}</strong>
                                        <span>{{ $day['amount'] < 0 ? '-' : '' }}&#8369;{{ number_format(abs($day['amount']), 0) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="staff-sales-empty">No collected payments or refunds were recorded during this seven-day period.</p>
                        @endif
                    </figure>

                    <div class="staff-workspace-grid">
                        <section class="staff-workspace staff-workspace--primary" aria-labelledby="admin-today-title">
                            <div class="staff-workspace-head">
                                <div>
                                    <h3 id="admin-today-title">Today's appointments</h3>
                                    <p>Client, service, therapist, payment, and current status.</p>
                                </div>
                                <a href="{{ route('appointments.index') }}">View full schedule</a>
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

                        <div class="staff-workspace-side">
                            <section class="staff-workspace" aria-labelledby="admin-sessions-title">
                                <div class="staff-workspace-head">
                                    <div>
                                        <h3 id="admin-sessions-title">Current sessions</h3>
                                        <p>Sessions presently in progress.</p>
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

                            <nav class="staff-management-links" aria-label="Administrator management links">
                                <a href="{{ route('reporting.index') }}"><span>Sales monitoring</span><small>Ledger, reconciliation, balances</small><i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                                <a href="{{ route('users.index') }}"><span>User accounts</span><small>Receptionists and customers</small><i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                                <a href="{{ route('therapist-tracking.index', ['manage' => 1]) }}"><span>Therapist management</span><small>Availability and service hours</small><i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                            </nav>
                        </div>
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
