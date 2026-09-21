<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    @include('partials.theme-head')
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Client records list">
    <title>Client Records</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/client-records.css') }}">
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
                @include('partials.sidebar-nav', ['active' => 'client-records'])
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
                        <h1>Client Records</h1>
                        <div class="subtitle">Select a customer to view their record</div>
                    </div>
                    <div class="right">
                        <div class="actions" aria-label="Client records actions">
                            <div class="search">
                                <i class="bi bi-search" aria-hidden="true"></i>
                                <input id="client-records-search" type="search" placeholder="Search client name..." aria-label="Search client name">
                            </div>
                            @include('partials.topbar-notifications')
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

                @if ($errors->any())
                    <div class="cr-alert cr-alert-error" role="alert">
                        @foreach ($errors->all() as $err)
                            <div>{{ $err }}</div>
                        @endforeach
                    </div>
                @endif

                <article class="data-card">
                    <div class="card-head users-head">
                        <div class="cr-list-head">
                            <h3>Customers</h3>
                            <div class="cr-filter-badges" aria-label="Customer filters">
                                <a class="cr-filter-badge @if(($sort ?? 'all') === 'all') is-active @endif" href="{{ route('client-records.index', ['sort' => 'all']) }}">All</a>
                                <a class="cr-filter-badge @if(($sort ?? 'all') === 'active') is-active @endif" href="{{ route('client-records.index', ['sort' => 'active']) }}">Active</a>
                                <a class="cr-filter-badge @if(($sort ?? 'all') === 'inactive') is-active @endif" href="{{ route('client-records.index', ['sort' => 'inactive']) }}">Inactive</a>
                                <a class="cr-filter-badge @if(($sort ?? 'all') === 'new_user') is-active @endif" href="{{ route('client-records.index', ['sort' => 'new_user']) }}">New User</a>
                            </div>
                        </div>
                        <div class="cr-list-tools">
                        </div>
                    </div>
                    <div class="user-list" id="client-records-list">
                        @forelse ($customers as $customer)
                            <div
                                class="cr-customer-row"
                                data-search="{{ strtolower($customer->full_name) }}"
                            >
                                <a class="cr-customer-row-link" href="{{ route('client-records.show', $customer) }}">
                                    <div class="cr-customer-main">
                                        <div class="cr-customer-avatar">
                                            @if (!empty($customer->profile_photo_url))
                                                <img src="{{ $customer->profile_photo_url }}" alt="{{ $customer->full_name }} profile photo">
                                            @else
                                                {{ strtoupper(substr($customer->full_name, 0, 2)) }}
                                            @endif
                                        </div>
                                        <div class="cr-customer-text">
                                            <strong>{{ $customer->full_name }}</strong>
                                            <span>{{ $customer->email }}</span>
                                        </div>
                                    </div>
                                    <div class="cr-customer-meta">
                                        @if (($customer->is_active ?? false) === false && !empty($customer->inactivity_duration))
                                            <span class="pill pill-inactive">{{ $customer->inactivity_duration }}</span>
                                        @endif
                                        <span class="pill">{{ $customer->number ?: 'No number' }}</span>
                                        <span class="go"><i class="bi bi-chevron-right"></i></span>
                                    </div>
                                </a>
                                @if (auth()->user()->isAdmin())
                                    <div class="cr-customer-actions">
                                        <button
                                            type="button"
                                            class="user-action archive archive-customer-btn"
                                            data-archive-url="{{ route('dashboard.customers.destroy', $customer) }}"
                                            data-customer-name="{{ $customer->full_name }}"
                                            data-return-to="client-records.index"
                                        >
                                            <i class="bi bi-archive" aria-hidden="true"></i> Archive
                                        </button>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="empty-users">
                                No customers yet. Add one in Users → Customers.
                            </div>
                        @endforelse
                    </div>
                    @if ($customers->hasPages())
                        <div class="cr-list-pagination">
                            {{ $customers->links() }}
                        </div>
                    @endif
                </article>
            </main>
        </div>
    </div>

    <div class="profile-modal hidden-section" id="archive-customer-modal" role="dialog" aria-modal="true" aria-labelledby="archive-customer-title">
        <div class="profile-modal-backdrop" data-close-archive-customer="true"></div>
        <div class="profile-modal-content archive-confirm-content">
            <button class="profile-modal-close" type="button" aria-label="Close" data-close-archive-customer="true">&times;</button>
            <div class="archive-confirm-head">
                <div class="archive-confirm-icon" aria-hidden="true"><i class="bi bi-archive"></i></div>
                <div>
                    <h3 class="profile-modal-title" id="archive-customer-title">Archive Client Record</h3>
                    <p class="archive-confirm-sub">This hides the client from active lists while preserving bookings and spa history.</p>
                </div>
            </div>
            <p class="archive-confirm-copy">
                Archive <strong id="archive-customer-name">this customer</strong>? Their record stays in the system but will no longer appear here.
            </p>
            <div class="profile-modal-actions archive-confirm-actions">
                <button type="button" class="user-action" data-close-archive-customer="true">Cancel</button>
                <button type="button" class="user-action archive archive-confirm-btn" id="archive-customer-confirm">
                    <i class="bi bi-archive" aria-hidden="true"></i>
                    Archive Client
                </button>
            </div>
        </div>
    </div>

    <form id="archive-customer-form" method="POST" action="" class="hidden-section">
        @csrf
        @method('DELETE')
        <input type="hidden" name="return_to" id="archive-customer-return-to" value="">
    </form>

    <script>
        const search = document.getElementById('client-records-search');
        const list = document.getElementById('client-records-list');
        const rows = list ? Array.from(list.querySelectorAll('[data-search]')) : [];

        search?.addEventListener('input', () => {
            const q = (search.value || '').trim().toLowerCase();
            rows.forEach((row) => {
                const hay = row.getAttribute('data-search') || '';
                row.style.display = hay.includes(q) ? '' : 'none';
            });
        });

        const statusToast = document.getElementById('status-toast');
        if (statusToast) {
            const closeBtn = statusToast.querySelector('.toast-close');
            const hideToast = () => statusToast.classList.add('hidden');
            closeBtn?.addEventListener('click', hideToast);
            window.setTimeout(hideToast, 3500);
        }

        const archiveCustomerModal = document.getElementById('archive-customer-modal');
        const archiveCustomerForm = document.getElementById('archive-customer-form');
        const archiveCustomerName = document.getElementById('archive-customer-name');
        const archiveCustomerReturnTo = document.getElementById('archive-customer-return-to');
        const archiveCustomerConfirm = document.getElementById('archive-customer-confirm');

        function openArchiveCustomerModal(button) {
            const url = button.getAttribute('data-archive-url') ?? '';
            const name = button.getAttribute('data-customer-name') ?? 'this customer';
            const returnTo = button.getAttribute('data-return-to') ?? '';
            if (archiveCustomerForm && url) archiveCustomerForm.action = url;
            if (archiveCustomerName) archiveCustomerName.textContent = name;
            if (archiveCustomerReturnTo) archiveCustomerReturnTo.value = returnTo;
            archiveCustomerModal?.classList.remove('hidden-section');
            document.body.classList.add('modal-open');
            archiveCustomerConfirm?.focus();
        }

        function closeArchiveCustomerModal() {
            archiveCustomerModal?.classList.add('hidden-section');
            document.body.classList.remove('modal-open');
            if (archiveCustomerForm) archiveCustomerForm.action = '';
            if (archiveCustomerReturnTo) archiveCustomerReturnTo.value = '';
        }

        document.querySelectorAll('.archive-customer-btn').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                openArchiveCustomerModal(button);
            });
        });
        archiveCustomerModal?.querySelectorAll('[data-close-archive-customer]').forEach((el) => {
            el.addEventListener('click', closeArchiveCustomerModal);
        });
        archiveCustomerConfirm?.addEventListener('click', () => archiveCustomerForm?.submit());

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && archiveCustomerModal && !archiveCustomerModal.classList.contains('hidden-section')) {
                closeArchiveCustomerModal();
            }
        });
    </script>
</body>
</html>
