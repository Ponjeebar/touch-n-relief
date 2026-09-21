<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    @include('partials.theme-head')
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Staff activity log">
    <title>Activity Log</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/activity-log.css') }}">
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}">
    @include('partials.staff-mobile-style')
</head>
<body
    data-activity-log-page
    data-poll-url="{{ route('activity-logs.poll') }}"
    data-latest-id="{{ $logs->first()?->id ?? 0 }}"
>
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
                @include('partials.sidebar-nav', ['active' => 'activity-log'])
                <div class="sidebar-footer">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="logout" type="submit"><span class="nav-icon"><i class="bi bi-box-arrow-right"></i></span><span class="nav-text">Logout</span></button>
                    </form>
                </div>
            </aside>

            <main class="main activity-log-main">
                <div class="topbar">
                    <div class="welcome">
                        @include('partials.topbar-panel-badge')
                        <h1>Activity Log</h1>
                        <div class="subtitle">Live feed from the database — staff and customer booking actions</div>
                    </div>
                    <div class="right">
                        @include('partials.topbar-notifications')
                        @include('partials.topbar-settings')
                        @include('partials.topbar-profile')
                    </div>
                </div>

                @include('partials.status-toast')

                <section class="activity-stats" aria-label="Activity summary">
                    <article class="activity-stat-card activity-stat-card--blue">
                        <p class="activity-stat-label">Total entries</p>
                        <p class="activity-stat-value" data-activity-stat="total">{{ number_format($stats['total']) }}</p>
                        <p class="activity-stat-sub">All time</p>
                    </article>
                    <article class="activity-stat-card activity-stat-card--teal">
                        <p class="activity-stat-label">Today</p>
                        <p class="activity-stat-value" data-activity-stat="today">{{ number_format($stats['today']) }}</p>
                        <p class="activity-stat-sub">All roles</p>
                    </article>
                    <article class="activity-stat-card activity-stat-card--green">
                        <p class="activity-stat-label">Receptionist today</p>
                        <p class="activity-stat-value" data-activity-stat="receptionist_today">{{ number_format($stats['receptionist_today']) }}</p>
                        <p class="activity-stat-sub">Receptionist activity</p>
                    </article>
                    <article class="activity-stat-card activity-stat-card--rose">
                        <p class="activity-stat-label">Admin today</p>
                        <p class="activity-stat-value" data-activity-stat="admin_today">{{ number_format($stats['admin_today']) }}</p>
                        <p class="activity-stat-sub">Admin actions</p>
                    </article>
                    <article class="activity-stat-card activity-stat-card--purple">
                        <p class="activity-stat-label">Customer today</p>
                        <p class="activity-stat-value" data-activity-stat="customer_today">{{ number_format($stats['customer_today']) }}</p>
                        <p class="activity-stat-sub">Bookings &amp; changes</p>
                    </article>
                </section>

                <article class="data-card activity-log-panel">
                    <div class="card-head activity-log-head">
                        <h3>Recent activity</h3>
                        <form method="GET" action="{{ route('activity-logs.index') }}" class="activity-filters">
                            <div class="search activity-search">
                                <i class="bi bi-search" aria-hidden="true"></i>
                                <input
                                    type="search"
                                    name="q"
                                    value="{{ $search }}"
                                    placeholder="Search name, action, or details..."
                                    aria-label="Search activity log"
                                >
                            </div>
                            <label class="activity-date-field">
                                <span class="sr-only">From date</span>
                                <input
                                    type="date"
                                    name="date_from"
                                    value="{{ $dateFrom }}"
                                    aria-label="From date"
                                    max="{{ $dateTo ?: now()->format('Y-m-d') }}"
                                >
                            </label>
                            <label class="activity-date-field">
                                <span class="sr-only">To date</span>
                                <input
                                    type="date"
                                    name="date_to"
                                    value="{{ $dateTo }}"
                                    aria-label="To date"
                                    max="{{ now()->format('Y-m-d') }}"
                                >
                            </label>
                            <select name="date_sort" aria-label="Sort by date">
                                <option value="desc" @selected($dateSort === 'desc')>Newest first</option>
                                <option value="asc" @selected($dateSort === 'asc')>Oldest first</option>
                            </select>
                            <select name="role" aria-label="Filter by role">
                                <option value="">All roles</option>
                                <option value="admin" @selected($role === 'admin')>Admin</option>
                                <option value="receptionist" @selected($role === 'receptionist')>Receptionist</option>
                                <option value="user" @selected($role === 'user')>Customer</option>
                            </select>
                            <select name="action" aria-label="Filter by action">
                                <option value="">All actions</option>
                                @foreach ($actions as $actionOption)
                                    <option value="{{ $actionOption }}" @selected($action === $actionOption)>
                                        {{ str_replace(['.', '_'], ' ', ucfirst($actionOption)) }}
                                    </option>
                                @endforeach
                            </select>
                            <button type="submit" class="activity-filter-btn">Apply</button>
                            @if ($role || $action || $search || $dateFrom || $dateTo || $dateSort !== 'desc')
                                <a href="{{ route('activity-logs.index') }}" class="activity-clear-link">Clear</a>
                            @endif
                        </form>
                    </div>

                    <p class="activity-live-hint" aria-live="polite">
                        <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
                        Updates automatically every 30 seconds
                    </p>

                    <div class="activity-empty" data-activity-log-empty @if ($logs->isNotEmpty()) hidden @endif>
                        No activity matches your filters. Actions by admins, receptionists, and customers are stored in the database and will appear here.
                    </div>

                    <div class="activity-table-wrap" data-activity-log-table-wrap @if ($logs->isEmpty()) hidden @endif>
                        <table class="activity-table">
                            <thead>
                                <tr>
                                    <th>
                                        <a
                                            href="{{ request()->fullUrlWithQuery(['date_sort' => $dateSort === 'desc' ? 'asc' : 'desc', 'page' => 1]) }}"
                                            class="activity-sort-link"
                                            aria-label="Sort by date {{ $dateSort === 'desc' ? 'oldest first' : 'newest first' }}"
                                        >
                                            When
                                            <i class="bi bi-sort-{{ $dateSort === 'desc' ? 'down' : 'up' }}" aria-hidden="true"></i>
                                        </a>
                                    </th>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Action</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody data-activity-log-tbody>
                                @foreach ($logs as $log)
                                    <tr data-activity-id="{{ $log->id }}">
                                        <td class="activity-when">
                                            <span class="activity-date">{{ $log->created_at->format('M j, Y') }}</span>
                                            <span class="activity-time">{{ $log->created_at->format('g:i A') }}</span>
                                        </td>
                                        <td>{{ $log->user_name }}</td>
                                        <td class="activity-role-cell">
                                            <span class="activity-role activity-role-{{ $log->user_role }}">
                                                {{ $log->roleLabel() }}
                                            </span>
                                        </td>
                                        <td>{{ $log->actionLabel() }}</td>
                                        <td class="activity-desc">{{ $log->description }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($logs->hasPages())
                        <div class="activity-pagination">
                            {{ $logs->links() }}
                        </div>
                    @endif
                </article>
            </main>
        </div>
    </div>
    <script src="{{ asset('js/activity-log-poll.js') }}"></script>
</body>
</html>
