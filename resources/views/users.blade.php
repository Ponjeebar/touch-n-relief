<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    @include('partials.theme-head')
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Admin user management">
    <title>Manage User</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}">
</head>
<body>
    @php
        $activeTab = $activeTab ?? request('tab', 'receptionists');
        $navActive = $activeTab === 'customers' ? 'users-customers' : 'users-receptionists';
        $totalUsers = ($receptionistCount ?? $receptionists->count()) + ($customerTotal ?? $customers->total());
    @endphp
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
                @include('partials.sidebar-nav', ['active' => $navActive])
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
                        <h1>Manage User</h1>
                        <div class="subtitle">Welcome, {{ auth()->user()->name ?? auth()->user()->email }}</div>
                    </div>
                    <div class="right">
                        <div class="actions" aria-label="User management actions">
                            <div class="search">
                                <i class="bi bi-search" aria-hidden="true"></i>
                                <input
                                    id="users-search"
                                    type="search"
                                    placeholder="{{ $activeTab === 'customers' ? 'Search customers...' : 'Search receptionists...' }}"
                                    aria-label="Search users"
                                >
                            </div>
                            @include('partials.topbar-notifications')
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

                <section class="users-hero" aria-label="Directory overview">
                    <div class="users-hero-copy">
                        <span class="users-hero-kicker"><i class="bi bi-shield-check" aria-hidden="true"></i> Admin directory</span>
                        <p>Keep receptionist accounts and customer profiles organized. Archiving hides someone from active lists without removing their booking history.</p>
                    </div>
                    <div class="users-hero-note">
                        <i class="bi bi-info-circle" aria-hidden="true"></i>
                        <span>Archived accounts cannot sign in, but records stay in the system.</span>
                    </div>
                </section>

                <section class="users-stats-grid" aria-label="User statistics">
                    <article class="users-stat-card">
                        <span class="users-stat-icon total" aria-hidden="true"><i class="bi bi-people-fill"></i></span>
                        <div class="users-stat-body">
                            <span class="label">Total Users</span>
                            <span class="value">{{ number_format($totalUsers) }}</span>
                        </div>
                    </article>
                    <article class="users-stat-card">
                        <span class="users-stat-icon receptionists" aria-hidden="true"><i class="bi bi-person-badge"></i></span>
                        <div class="users-stat-body">
                            <span class="label">Active Staff</span>
                            <span class="value">{{ number_format($receptionistCount ?? $receptionists->count()) }}</span>
                        </div>
                    </article>
                    <article class="users-stat-card">
                        <span class="users-stat-icon customers" aria-hidden="true"><i class="bi bi-person-lines-fill"></i></span>
                        <div class="users-stat-body">
                            <span class="label">Registered Clients</span>
                            <span class="value">{{ number_format($customerTotal ?? $customers->total()) }}</span>
                        </div>
                    </article>
                </section>

                <article class="data-card users-panel">
                    <div class="card-head users-head">
                        <div class="users-head-left">
                            <h3 id="users-section-title">{{ $activeTab === 'customers' ? 'Customers' : 'Receptionists' }}</h3>
                            <nav class="users-switch" aria-label="User type">
                                <a
                                    href="{{ route('users.index', ['tab' => 'receptionists']) }}"
                                    class="users-switch-btn {{ $activeTab === 'receptionists' ? 'active' : '' }}"
                                    data-tab="receptionists"
                                >
                                    <i class="bi bi-person-badge" aria-hidden="true"></i>
                                    Receptionists
                                </a>
                                <a
                                    href="{{ route('users.index', ['tab' => 'customers']) }}"
                                    class="users-switch-btn {{ $activeTab === 'customers' ? 'active' : '' }}"
                                    data-tab="customers"
                                >
                                    <i class="bi bi-person-lines-fill" aria-hidden="true"></i>
                                    Customers
                                </a>
                            </nav>
                        </div>
                        <div class="users-panel-head-actions">
                            <button
                                class="add-user-btn {{ $activeTab === 'receptionists' ? '' : 'hidden-section' }}"
                                type="button"
                                id="toggle-add-receptionist"
                            >
                                <i class="bi bi-plus-circle"></i> Add Receptionist
                            </button>
                        </div>
                    </div>

                    <section id="panel-receptionists" class="{{ $activeTab === 'customers' ? 'hidden-section' : '' }}">
                        <div class="user-list" id="receptionists-list">
                            @forelse ($receptionists as $receptionist)
                                <div
                                    class="user-row"
                                    data-search="{{ strtolower(($receptionist->full_name ?? $receptionist->username).' '.$receptionist->email.' '.$receptionist->receptionist_id) }}"
                                >
                                    <div class="user-profile">
                                        @php
                                            $avatarUrl = $receptionist->profile_photo_url ?? null;
                                        @endphp
                                        @if ($avatarUrl)
                                            <img class="user-avatar-img" src="{{ $avatarUrl }}" alt="{{ $receptionist->username }}">
                                        @else
                                            <div class="user-avatar">{{ strtoupper(substr($receptionist->username, 0, 2)) }}</div>
                                        @endif
                                        <div class="user-main">
                                            <strong>{{ $receptionist->full_name ?? $receptionist->username }}</strong>
                                            <span class="role-badge role-badge-receptionist">Receptionist</span>
                                            <span class="user-id">ID: {{ $receptionist->receptionist_id }}</span>
                                        </div>
                                    </div>
                                    <div class="user-info-grid">
                                        <div class="info-group">
                                            <span class="info-label">Email</span>
                                            <span class="info-value">{{ $receptionist->email }}</span>
                                        </div>
                                        <div class="info-group">
                                            <span class="info-label">Phone</span>
                                            <span class="info-value">{{ $receptionist->phone_number ?: '—' }}</span>
                                        </div>
                                        <div class="info-group">
                                            <span class="info-label">Member Since</span>
                                            <span class="info-value">{{ optional($receptionist->created_at)->format('M j, Y') ?: '—' }}</span>
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
                                            data-address="{{ $receptionist->address ?? '' }}"
                                            data-phone-number="{{ $receptionist->phone_number ?? '' }}"
                                            data-birthday="{{ optional($receptionist->birthday)->format('Y-m-d') }}"
                                            data-profile-picture="{{ $receptionist->profile_photo_url ?? '' }}"
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
                                            data-address="{{ $receptionist->address ?? '' }}"
                                            data-phone-number="{{ $receptionist->phone_number ?? '' }}"
                                            data-birthday="{{ optional($receptionist->birthday)->format('Y-m-d') }}"
                                            data-receptionist-id="{{ $receptionist->receptionist_id }}"
                                        >
                                            <i class="bi bi-pencil-square" aria-hidden="true"></i> Edit
                                        </button>
                                        <button
                                            class="user-action archive archive-receptionist-btn"
                                            type="button"
                                            data-archive-url="{{ route('dashboard.receptionists.destroy', $receptionist) }}"
                                            data-receptionist-name="{{ $receptionist->full_name ?? $receptionist->username }}"
                                        >
                                            <i class="bi bi-archive" aria-hidden="true"></i> Archive
                                        </button>
                                    </div>
                                </div>
                            @empty
                                <div class="empty-users">No receptionist users yet. Add one above.</div>
                            @endforelse
                        </div>
                    </section>

                    <section id="panel-customers" class="{{ $activeTab === 'customers' ? '' : 'hidden-section' }}">
                        <div class="user-list users-customer-list" id="customers-list">
                            @forelse ($customers as $customer)
                                <div
                                    class="user-row"
                                    data-search="{{ strtolower($customer->full_name.' '.$customer->email.' '.$customer->customer_id) }}"
                                >
                                    <div class="user-profile">
                                        @if (!empty($customer->profile_photo_url))
                                            <img class="user-avatar-img" src="{{ $customer->profile_photo_url }}" alt="{{ $customer->full_name }}">
                                        @else
                                            <div class="user-avatar">{{ $customer->initials ?? strtoupper(substr($customer->full_name, 0, 2)) }}</div>
                                        @endif
                                        <div class="user-main">
                                            <strong>{{ $customer->full_name }}</strong>
                                            <span class="role-badge role-badge-customer">Customer</span>
                                            <span class="user-id">ID: {{ $customer->customer_id }}</span>
                                        </div>
                                    </div>
                                    <div class="user-info-grid">
                                        <div class="info-group">
                                            <span class="info-label">Email</span>
                                            <span class="info-value">{{ $customer->email }}</span>
                                        </div>
                                        <div class="info-group">
                                            <span class="info-label">Phone</span>
                                            <span class="info-value">{{ $customer->number ?: '—' }}</span>
                                        </div>
                                        <div class="info-group">
                                            <span class="info-label">Member Since</span>
                                            <span class="info-value">{{ optional($customer->created_at)->format('M j, Y') ?: '—' }}</span>
                                        </div>
                                    </div>
                                    <div class="user-meta">
                                        <button
                                            class="user-action edit cr-edit-customer-btn"
                                            type="button"
                                            data-update-url="{{ route('dashboard.customers.update', $customer) }}"
                                            data-full-name="{{ $customer->full_name }}"
                                            data-email="{{ $customer->email }}"
                                            data-birthday="{{ optional($customer->birthday)->format('Y-m-d') }}"
                                            data-number="{{ $customer->number ?? '' }}"
                                        >
                                            <i class="bi bi-pencil-square" aria-hidden="true"></i> Edit
                                        </button>
                                        <button
                                            class="user-action archive archive-customer-btn"
                                            type="button"
                                            data-archive-url="{{ route('dashboard.customers.destroy', $customer) }}"
                                            data-customer-name="{{ $customer->full_name }}"
                                            data-return-to="users.index"
                                        >
                                            <i class="bi bi-archive" aria-hidden="true"></i> Archive
                                        </button>
                                    </div>
                                </div>
                            @empty
                                <div class="empty-users">No customers found.</div>
                            @endforelse
                        </div>
                        @if ($customers->hasPages())
                            <div class="users-customer-pagination">
                                {{ $customers->appends(['tab' => 'customers'])->links() }}
                            </div>
                        @endif
                    </section>
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
                                </div>
                                <div class="profile-field two-col">
                                    <div>
                                        <label>Phone number</label>
                                        <input id="modal-phone-number" type="text" readonly>
                                    </div>
                                    <div>
                                        <label>Birthday</label>
                                        <input id="modal-birthday" type="text" readonly>
                                    </div>
                                </div>
                                <div class="profile-field two-col">
                                    <div>
                                        <label>Age</label>
                                        <input id="modal-age" type="text" readonly>
                                    </div>
                                    <div>
                                        <label>Address</label>
                                        <input id="modal-address" type="text" readonly>
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
                        <form class="add-receptionist-modal-form" method="POST" action="{{ route('dashboard.receptionists.store') }}" enctype="multipart/form-data">
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
                                    <label>Phone Number</label>
                                    <input type="text" name="phone_number" value="{{ old('phone_number') }}" maxlength="11" pattern="^09\d{9}$" title="Use 09XXXXXXXXX (11 digits)." inputmode="numeric" placeholder="09171234567">
                                </div>
                                <div class="profile-field">
                                    <label>Birthday</label>
                                    <input type="date" name="birthday" value="{{ old('birthday') }}">
                                </div>
                                <div class="profile-field full-row">
                                    <label>Address</label>
                                    <input type="text" name="address" value="{{ old('address') }}" placeholder="Street, Barangay, City">
                                </div>
                                <div class="profile-field">
                                    <label>Password</label>
                                    <div class="password-input-wrap">
                                        <input
                                            id="add-receptionist-password"
                                            type="password"
                                            name="password"
                                            required
                                            minlength="8"
                                            placeholder="Min 8 characters"
                                        >
                                        <button
                                            type="button"
                                            class="password-toggle-btn"
                                            id="add-receptionist-password-toggle"
                                            data-pw-toggle
                                            aria-label="Show password"
                                            aria-pressed="false"
                                        >
                                            <i class="bi bi-eye" aria-hidden="true"></i>
                                        </button>
                                    </div>
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
                                    <label>Phone Number</label>
                                    <input type="text" name="phone_number" id="edit-modal-phone-number" maxlength="11" pattern="^09\d{9}$" title="Use 09XXXXXXXXX (11 digits)." inputmode="numeric">
                                </div>
                                <div class="profile-field">
                                    <label>Birthday</label>
                                    <input type="date" name="birthday" id="edit-modal-birthday">
                                </div>
                                <div class="profile-field full-row">
                                    <label>Address</label>
                                    <input type="text" name="address" id="edit-modal-address">
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

                <div class="profile-modal hidden-section" id="archive-receptionist-modal" role="dialog" aria-modal="true" aria-labelledby="archive-receptionist-title">
                    <div class="profile-modal-backdrop" data-close-archive-modal="true"></div>
                    <div class="profile-modal-content archive-confirm-content">
                        <button class="profile-modal-close" type="button" aria-label="Close" data-close-archive-modal="true">&times;</button>
                        <div class="archive-confirm-head">
                            <div class="archive-confirm-icon" aria-hidden="true"><i class="bi bi-archive"></i></div>
                            <div>
                                <h3 class="profile-modal-title" id="archive-receptionist-title">Archive Receptionist</h3>
                                <p class="archive-confirm-sub">This disables their login but keeps their profile and activity history.</p>
                            </div>
                        </div>
                        <p class="archive-confirm-copy">
                            Archive <strong id="archive-receptionist-name">this receptionist</strong>? They will no longer appear in the active staff list or be able to sign in.
                        </p>
                        <div class="profile-modal-actions archive-confirm-actions">
                            <button type="button" class="user-action" data-close-archive-modal="true">Cancel</button>
                            <button type="button" class="user-action archive" id="archive-receptionist-confirm">
                                <i class="bi bi-archive" aria-hidden="true"></i>
                                Archive Receptionist
                            </button>
                        </div>
                    </div>
                </div>

                <form id="archive-receptionist-form" method="POST" action="" class="hidden-section">
                    @csrf
                    @method('DELETE')
                </form>

                <div class="profile-modal hidden-section" id="archive-customer-modal" role="dialog" aria-modal="true" aria-labelledby="archive-customer-title">
                    <div class="profile-modal-backdrop" data-close-archive-customer="true"></div>
                    <div class="profile-modal-content archive-confirm-content">
                        <button class="profile-modal-close" type="button" aria-label="Close" data-close-archive-customer="true">&times;</button>
                        <div class="archive-confirm-head">
                            <div class="archive-confirm-icon" aria-hidden="true"><i class="bi bi-archive"></i></div>
                            <div>
                                <h3 class="profile-modal-title" id="archive-customer-title">Archive Customer</h3>
                                <p class="archive-confirm-sub">This hides the client from active lists while preserving bookings and records.</p>
                            </div>
                        </div>
                        <p class="archive-confirm-copy">
                            Archive <strong id="archive-customer-name">this customer</strong>? Their client record and spa history will remain in the system.
                        </p>
                        <div class="profile-modal-actions archive-confirm-actions">
                            <button type="button" class="user-action" data-close-archive-customer="true">Cancel</button>
                            <button type="button" class="user-action archive" id="archive-customer-confirm">
                                <i class="bi bi-archive" aria-hidden="true"></i>
                                Archive Customer
                            </button>
                        </div>
                    </div>
                </div>

                <form id="archive-customer-form" method="POST" action="" class="hidden-section">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="return_to" id="archive-customer-return-to" value="users.index">
                </form>

                @include('partials.customer-client-modals', ['showAdd' => false])
            </main>
        </div>
    </div>
    <script>
        const activeTab = @json($activeTab);
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
        const editModalPhoneNumber = document.getElementById('edit-modal-phone-number');
        const editModalBirthday = document.getElementById('edit-modal-birthday');
        const editModalAddress = document.getElementById('edit-modal-address');
        const modalProfileImage = document.getElementById('modal-profile-image');
        const modalProfileFallback = document.getElementById('modal-profile-fallback');
        const modalFullName = document.getElementById('modal-full-name');
        const modalUsername = document.getElementById('modal-username');
        const modalReceptionistId = document.getElementById('modal-receptionist-id');
        const modalEmail = document.getElementById('modal-email');
        const modalPhoneNumber = document.getElementById('modal-phone-number');
        const modalBirthday = document.getElementById('modal-birthday');
        const modalAge = document.getElementById('modal-age');
        const modalAddress = document.getElementById('modal-address');
        const usersSearch = document.getElementById('users-search');
        const activeList = document.getElementById(activeTab === 'customers' ? 'customers-list' : 'receptionists-list');

        function filterUserRows() {
            if (!usersSearch || !activeList) return;
            const q = (usersSearch.value || '').trim().toLowerCase();
            activeList.querySelectorAll('[data-search]').forEach((row) => {
                const hay = row.getAttribute('data-search') || '';
                row.style.display = hay.includes(q) ? '' : 'none';
            });
        }

        usersSearch?.addEventListener('input', filterUserRows);

        function calculateAgeFromBirthday(birthdayIso) {
            if (!birthdayIso) return '—';
            const birthDate = new Date(birthdayIso);
            if (Number.isNaN(birthDate.getTime())) return '—';
            const now = new Date();
            let age = now.getFullYear() - birthDate.getFullYear();
            const monthDiff = now.getMonth() - birthDate.getMonth();
            if (monthDiff < 0 || (monthDiff === 0 && now.getDate() < birthDate.getDate())) {
                age -= 1;
            }
            return age >= 0 ? String(age) : '—';
        }

        function openAddReceptionistModal() {
            addReceptionistModal?.classList.remove('hidden-section');
            document.body.classList.add('modal-open');
        }
        function hideAddReceptionistModal() {
            addReceptionistModal?.classList.add('hidden-section');
            document.body.classList.remove('modal-open');
        }
        document.getElementById('toggle-add-receptionist')?.addEventListener('click', openAddReceptionistModal);
        closeAddReceptionistModal?.addEventListener('click', hideAddReceptionistModal);
        addModalCancel?.addEventListener('click', hideAddReceptionistModal);
        addReceptionistModal?.addEventListener('click', (event) => {
            const target = event.target;
            if (target instanceof HTMLElement && target.dataset.closeAddModal === 'true') hideAddReceptionistModal();
        });

        function openProfileModal(button) {
            const fullName = button.getAttribute('data-full-name') ?? '';
            const username = button.getAttribute('data-username') ?? '';
            const receptionistId = button.getAttribute('data-receptionist-id') ?? '';
            const email = button.getAttribute('data-email') ?? '';
            const phoneNumber = button.getAttribute('data-phone-number') ?? '';
            const birthday = button.getAttribute('data-birthday') ?? '';
            const address = button.getAttribute('data-address') ?? '';
            const profilePicture = button.getAttribute('data-profile-picture') ?? '';

            if (modalFullName) modalFullName.value = fullName;
            if (modalUsername) modalUsername.value = username;
            if (modalReceptionistId) modalReceptionistId.value = receptionistId;
            if (modalEmail) modalEmail.value = email;
            if (modalPhoneNumber) modalPhoneNumber.value = phoneNumber || '—';
            if (modalBirthday) modalBirthday.value = birthday || '—';
            if (modalAge) modalAge.value = calculateAgeFromBirthday(birthday);
            if (modalAddress) modalAddress.value = address || '—';

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
            if (target instanceof HTMLElement && target.dataset.closeModal === 'true') hideProfileModal();
        });

        function openEditReceptionistModal(button) {
            const updateUrl = button.getAttribute('data-update-url') ?? '';
            if (editReceptionistForm && updateUrl) editReceptionistForm.action = updateUrl;
            if (editModalReceptionistId) editModalReceptionistId.value = button.getAttribute('data-receptionist-id') ?? '';
            if (editModalFullName) editModalFullName.value = button.getAttribute('data-full-name') ?? '';
            if (editModalUsername) editModalUsername.value = button.getAttribute('data-username') ?? '';
            if (editModalEmail) editModalEmail.value = button.getAttribute('data-email') ?? '';
            if (editModalPhoneNumber) editModalPhoneNumber.value = button.getAttribute('data-phone-number') ?? '';
            if (editModalBirthday) editModalBirthday.value = button.getAttribute('data-birthday') ?? '';
            if (editModalAddress) editModalAddress.value = button.getAttribute('data-address') ?? '';
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
            if (target instanceof HTMLElement && target.dataset.closeEditModal === 'true') hideEditReceptionistModal();
        });

        const archiveReceptionistModal = document.getElementById('archive-receptionist-modal');
        const archiveReceptionistForm = document.getElementById('archive-receptionist-form');
        const archiveReceptionistName = document.getElementById('archive-receptionist-name');
        const archiveReceptionistConfirm = document.getElementById('archive-receptionist-confirm');

        function openArchiveReceptionistModal(button) {
            const url = button.getAttribute('data-archive-url') ?? '';
            const name = button.getAttribute('data-receptionist-name') ?? 'this receptionist';
            if (archiveReceptionistForm && url) archiveReceptionistForm.action = url;
            if (archiveReceptionistName) archiveReceptionistName.textContent = name;
            archiveReceptionistModal?.classList.remove('hidden-section');
            document.body.classList.add('modal-open');
            archiveReceptionistConfirm?.focus();
        }

        function closeArchiveReceptionistModal() {
            archiveReceptionistModal?.classList.add('hidden-section');
            document.body.classList.remove('modal-open');
            if (archiveReceptionistForm) archiveReceptionistForm.action = '';
        }

        document.querySelectorAll('.archive-receptionist-btn').forEach((button) => {
            button.addEventListener('click', () => openArchiveReceptionistModal(button));
        });
        archiveReceptionistModal?.querySelectorAll('[data-close-archive-modal]').forEach((el) => {
            el.addEventListener('click', closeArchiveReceptionistModal);
        });
        archiveReceptionistConfirm?.addEventListener('click', () => archiveReceptionistForm?.submit());

        const archiveCustomerModal = document.getElementById('archive-customer-modal');
        const archiveCustomerForm = document.getElementById('archive-customer-form');
        const archiveCustomerName = document.getElementById('archive-customer-name');
        const archiveCustomerReturnTo = document.getElementById('archive-customer-return-to');
        const archiveCustomerConfirm = document.getElementById('archive-customer-confirm');

        function openArchiveCustomerModal(button) {
            const url = button.getAttribute('data-archive-url') ?? '';
            const name = button.getAttribute('data-customer-name') ?? 'this customer';
            const returnTo = button.getAttribute('data-return-to') ?? 'users.index';
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
        }

        document.querySelectorAll('.archive-customer-btn').forEach((button) => {
            button.addEventListener('click', () => openArchiveCustomerModal(button));
        });
        archiveCustomerModal?.querySelectorAll('[data-close-archive-customer]').forEach((el) => {
            el.addEventListener('click', closeArchiveCustomerModal);
        });
        archiveCustomerConfirm?.addEventListener('click', () => archiveCustomerForm?.submit());

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;
            if (archiveReceptionistModal && !archiveReceptionistModal.classList.contains('hidden-section')) closeArchiveReceptionistModal();
            if (archiveCustomerModal && !archiveCustomerModal.classList.contains('hidden-section')) closeArchiveCustomerModal();
        });

        const statusToast = document.getElementById('status-toast');
        if (statusToast) {
            const closeBtn = statusToast.querySelector('.toast-close');
            const hideToast = () => statusToast.classList.add('hidden');
            closeBtn?.addEventListener('click', hideToast);
            window.setTimeout(hideToast, 3500);
        }

        function normalizePhoneInput(raw) {
            let value = String(raw || '').replace(/\D/g, '');
            if (value === '') return '';

            const digits = value;
            if (digits.startsWith('09')) {
                return '09' + digits.slice(2, 11);
            }

            if (digits.startsWith('9')) {
                return '09' + digits.slice(1, 10);
            }

            if (digits.startsWith('0')) {
                return '09' + digits.slice(1, 10);
            }

            return '';
        }

        function enforcePhonePrefix(input) {
            if (!(input instanceof HTMLInputElement)) return;
            const normalized = normalizePhoneInput(input.value);
            if (input.value !== normalized) {
                input.value = normalized;
            }

            const valid = input.value === '' || /^09\d{9}$/.test(input.value);
            input.setCustomValidity(valid ? '' : 'Phone number must start with 09.');
        }

        document.querySelectorAll('input[name="phone_number"]').forEach((input) => {
            if (!(input instanceof HTMLInputElement)) return;
            input.addEventListener('focus', () => {
                if (!input.value) {
                    input.value = '09';
                }
            });
            input.addEventListener('input', () => enforcePhonePrefix(input));
            input.addEventListener('blur', () => enforcePhonePrefix(input));
            enforcePhonePrefix(input);
        });

        @if ($errors->any() && old('return_to') !== 'users.index')
            openAddReceptionistModal();
        @endif
    </script>
</body>
</html>
