<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    @include('partials.theme-head')
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Completed transactions">
    <title>Completed Transactions</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/completed-sessions.css') }}">
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}">
    @include('partials.staff-mobile-style')
</head>
<body>
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
                @include('partials.sidebar-nav', ['active' => 'completed'])
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
                        <h1>Completed Transactions</h1>
                        <div class="subtitle">View and manage completed massage sessions (Daily)</div>
                    </div>
                    <div class="right">
                        @include('partials.topbar-notifications')
                        @include('partials.topbar-settings')
                        @include('partials.topbar-profile')
                    </div>
                </div>

                <section class="completed-wrap">
                    @if (session('status'))
                        <p class="completed-flash">{{ session('status') }}</p>
                    @endif

                    <div class="summary-grid">
                        <article class="summary-card">
                            <span>Total Sales ({{ $scope === 'all' ? 'All' : 'Daily' }})</span>
                            <strong>₱{{ $totalSales }}</strong>
                        </article>
                        <article class="summary-card">
                            <span>Total Service Logged</span>
                            <strong>{{ $totalServiceLogged }}</strong>
                            <small>Completed sessions ({{ $scope === 'all' ? 'all records' : 'daily' }})</small>
                            <div class="mini-bars" aria-hidden="true">
                                <span style="height: 32%"></span>
                                <span style="height: 48%"></span>
                                <span style="height: 62%"></span>
                                <span style="height: 76%"></span>
                                <span style="height: 58%"></span>
                                <span style="height: 84%"></span>
                            </div>
                        </article>
                        <article class="summary-card">
                            <span>Total Hours</span>
                            <strong>{{ $totalHours }} hrs</strong>
                            <small>Therapy time delivered ({{ $scope === 'all' ? 'all records' : 'daily' }})</small>
                            <div class="mini-line" aria-hidden="true">
                                <svg viewBox="0 0 220 60" preserveAspectRatio="none">
                                    <path d="M0,44 L35,38 L70,40 L105,30 L140,34 L175,24 L220,18" />
                                </svg>
                            </div>
                        </article>
                    </div>

                    <article class="history-card">
                        <div class="history-head">
                            <div class="history-head-left">
                                <h3>Transaction History ({{ $scopeLabel }})</h3>
                                @if ($scope === 'all')
                                    <div class="history-actions-row">
                                        <a href="{{ route('completed-sessions.index', array_filter(['search' => $search ?? ''])) }}" class="history-scope-btn">Back to Daily</a>
                                        <form method="GET" action="{{ route('completed-sessions.index') }}" class="history-range-form">
                                            <input type="hidden" name="scope" value="all">
                                            <input type="hidden" name="search" id="completed-range-search" value="{{ $search ?? '' }}">
                                            <label>
                                                <span>From</span>
                                                <input type="date" name="date_from" value="{{ $dateFrom }}" min="{{ $minDate }}" max="{{ $maxDate }}">
                                            </label>
                                            <label>
                                                <span>To</span>
                                                <input type="date" name="date_to" value="{{ $dateTo }}" min="{{ $minDate }}" max="{{ $maxDate }}">
                                            </label>
                                            <button type="submit" class="history-scope-btn">Sort Range</button>
                                            <a href="{{ route('completed-sessions.index', ['scope' => 'all']) }}" class="history-clear-btn">Reset</a>
                                        </form>
                                    </div>
                                @else
                                    <a href="{{ route('completed-sessions.index', array_filter(['scope' => 'all', 'search' => $search ?? ''])) }}" class="history-scope-btn">Past Transaction History</a>
                                @endif
                            </div>
                            <div class="search-box">
                                <i class="bi bi-search" aria-hidden="true"></i>
                                <input
                                    id="completed-txn-search"
                                    type="search"
                                    placeholder="Search by client, service, or therapist..."
                                    aria-label="Search completed transactions"
                                    value="{{ $search ?? '' }}"
                                >
                            </div>
                        </div>
                        <div class="table-wrap">
                            <table class="history-table">
                                <thead>
                                    <tr>
                                        <th>Transaction ID</th>
                                        <th>Client</th>
                                        <th>Source</th>
                                        <th>Therapist</th>
                                        <th>Service</th>
                                        <th>Date &amp; Time</th>
                                        <th>Duration</th>
                                        <th>Amount</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody id="completed-txn-tbody">
                                    @forelse ($transactions as $txn)
                                        <tr
                                            data-completed-search-row="true"
                                            data-search="{{ strtolower(implode(' ', [
                                                $txn['transaction_id'] ?? '',
                                                $txn['client'] ?? '',
                                                $txn['client_source_label'] ?? '',
                                                $txn['therapist'] ?? '',
                                                $txn['service'] ?? '',
                                                $txn['date_time'] ?? '',
                                                $txn['amount'] ?? '',
                                            ])) }}"
                                        >
                                            <td>{{ $txn['transaction_id'] }}</td>
                                            <td>{{ $txn['client'] }}</td>
                                            <td>
                                                @include('partials.client-source-pill', [
                                                    'clientSource' => $txn['client_source'] ?? 'online',
                                                    'clientSourceLabel' => $txn['client_source_label'] ?? 'Online Appointment',
                                                ])
                                            </td>
                                            <td>{{ $txn['therapist'] }}</td>
                                            <td>{{ $txn['service'] }}</td>
                                            <td>{{ $txn['date_time'] }}</td>
                                            <td>{{ $txn['duration_minutes'] }} min</td>
                                            <td class="amount">₱{{ $txn['amount'] }}</td>
                                            <td>
                                                <button
                                                    type="button"
                                                    class="notes-btn"
                                                    data-transaction-id="{{ $txn['transaction_id'] }}"
                                                    data-notes="{{ e($txn['notes']) }}"
                                                >
                                                    View Notes
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr data-completed-empty="true">
                                            <td colspan="9" class="history-empty">No completed sessions yet.</td>
                                        </tr>
                                    @endforelse
                                    <tr id="completed-search-empty" hidden>
                                        <td colspan="9" class="history-empty">No transactions match your search.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </article>
                </section>

                <div class="profile-modal hidden-section" id="notes-modal" role="dialog" aria-modal="true" aria-labelledby="notes-modal-title">
                    <div class="profile-modal-backdrop" data-close-notes="true"></div>
                    <div class="profile-modal-content notes-modal-content">
                        <button class="profile-modal-close" type="button" id="close-notes-modal" aria-label="Close">&times;</button>
                        <h3 class="profile-modal-title" id="notes-modal-title">Session Notes</h3>
                        <div class="notes-meta" id="notes-modal-transaction"></div>
                        <div class="notes-body" id="notes-modal-body"></div>
                        <div class="profile-modal-actions">
                            <button class="user-action" type="button" id="notes-modal-close-btn">Close</button>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script>
        const notesModal = document.getElementById('notes-modal');
        const closeNotesModal = document.getElementById('close-notes-modal');
        const notesModalCloseBtn = document.getElementById('notes-modal-close-btn');
        const notesTxn = document.getElementById('notes-modal-transaction');
        const notesBody = document.getElementById('notes-modal-body');

        function openNotesModal(transactionId, notes) {
            if (notesTxn) notesTxn.textContent = `Transaction: ${transactionId}`;
            if (notesBody) notesBody.textContent = notes;
            notesModal?.classList.remove('hidden-section');
            document.body.classList.add('modal-open');
        }

        function hideNotesModal() {
            notesModal?.classList.add('hidden-section');
            document.body.classList.remove('modal-open');
        }

        document.querySelectorAll('.notes-btn').forEach((button) => {
            button.addEventListener('click', () => {
                const transactionId = button.getAttribute('data-transaction-id') ?? '';
                const notes = button.getAttribute('data-notes') ?? '';
                openNotesModal(transactionId, notes);
            });
        });

        closeNotesModal?.addEventListener('click', hideNotesModal);
        notesModalCloseBtn?.addEventListener('click', hideNotesModal);
        notesModal?.addEventListener('click', (event) => {
            const target = event.target;
            if (target instanceof HTMLElement && target.dataset.closeNotes === 'true') {
                hideNotesModal();
            }
        });

        const completedSearchInput = document.getElementById('completed-txn-search');
        const completedRangeSearchInput = document.getElementById('completed-range-search');
        const completedSearchRows = Array.from(document.querySelectorAll('[data-completed-search-row="true"]'));
        const completedSearchEmptyRow = document.getElementById('completed-search-empty');
        let completedSearchDebounceTimer = null;

        function syncCompletedSearchUrl(query) {
            const url = new URL(window.location.href);
            const trimmed = query.trim();

            if (trimmed) {
                url.searchParams.set('search', trimmed);
            } else {
                url.searchParams.delete('search');
            }

            window.history.replaceState({}, '', url.toString());
        }

        function applyCompletedSearch(query) {
            const needle = query.trim().toLowerCase();
            let visibleCount = 0;

            completedSearchRows.forEach((row) => {
                if (!(row instanceof HTMLElement)) {
                    return;
                }

                const haystack = row.getAttribute('data-search') || '';
                const matches = needle === '' || haystack.includes(needle);
                row.hidden = !matches;

                if (matches) {
                    visibleCount += 1;
                }
            });

            if (completedSearchEmptyRow instanceof HTMLElement) {
                completedSearchEmptyRow.hidden = needle === '' || visibleCount > 0 || completedSearchRows.length === 0;
            }

            if (completedRangeSearchInput instanceof HTMLInputElement) {
                completedRangeSearchInput.value = query.trim();
            }

            syncCompletedSearchUrl(query);
        }

        if (completedSearchInput instanceof HTMLInputElement) {
            completedSearchInput.addEventListener('input', () => {
                window.clearTimeout(completedSearchDebounceTimer);
                completedSearchDebounceTimer = window.setTimeout(() => {
                    applyCompletedSearch(completedSearchInput.value);
                }, 200);
            });

            completedSearchInput.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter') {
                    return;
                }

                event.preventDefault();
                window.clearTimeout(completedSearchDebounceTimer);
                applyCompletedSearch(completedSearchInput.value);
            });

            if (completedSearchInput.value.trim() !== '') {
                applyCompletedSearch(completedSearchInput.value);
            }
        }
    </script>
</body>
</html>
