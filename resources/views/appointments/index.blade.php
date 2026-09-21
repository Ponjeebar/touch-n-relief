<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    @include('partials.theme-head')
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="Appointments management">
    <title>Appointments</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/appointments.css') }}">
    <link rel="stylesheet" href="{{ asset('css/payment-receipt.css') }}">
    <link rel="stylesheet" href="{{ asset('css/password-toggle.css') }}">
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}">
    @include('partials.staff-mobile-style')
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
                @include('partials.sidebar-nav', ['active' => 'appointments'])
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
                        <h1>Appointments</h1>
                        <div class="subtitle">
                            Manage bookings, approval, and daily activity
                            <span class="subtitle-divider">·</span>
                            @include('partials.live-system-clock')
                        </div>
                    </div>
                    <div class="right">
                        <div class="actions" aria-label="Appointments actions">
                            <div class="search">
                                <i class="bi bi-search" aria-hidden="true"></i>
                                <input
                                    type="search"
                                    id="appointments-search"
                                    placeholder="Search sessions, clients, appointments..."
                                    aria-label="Search appointments"
                                    value="{{ $search ?? '' }}"
                                    autocomplete="off"
                                >
                            </div>
                            <div class="topbar-notifications" data-topbar-notif-sync>
                                <button
                                    class="icon-btn"
                                    type="button"
                                    aria-label="Notifications"
                                    aria-haspopup="true"
                                    aria-expanded="false"
                                    data-open-notifications="true"
                                >
                                    <i class="bi bi-bell"></i>
                                </button>
                                <span class="topbar-notif-badge" data-page-notif-badge data-staff-notif-badge id="appointments-notif-badge">0</span>
                            </div>
                            <aside class="dashboard-notifications hidden-section" id="appointments-notif-dropdown" aria-label="Notifications panel">
                                <div class="dashboard-notifications-head">
                                    <h3>Notifications</h3>
                                    <button class="icon-btn slim" type="button" aria-label="Mark all as read" data-mark-appointments-dropdown-read="true" title="Mark all as read">
                                        <i class="bi bi-check2-all"></i>
                                    </button>
                                </div>
                                <div class="dashboard-notifications-list" data-staff-notifications-list>
                                    @forelse (collect($notifications ?? [])->take(4) as $note)
                                        @php($type = $note['type'] ?? 'system')
                                        <a
                                            class="dashboard-note dashboard-note-{{ $type }} note-unread"
                                            role="menuitem"
                                            data-notif-at="{{ $note['notification_at'] ?? '' }}"
                                            data-notif-key="{{ $note['notification_key'] ?? '' }}"
                                            href="{{ $note['url'] ?? route('appointments.index') }}"
                                        >
                                            <div class="dashboard-note-icon">
                                                @if ($type === 'confirmed')
                                                    <i class="bi bi-check-circle"></i>
                                                @elseif ($type === 'cancelled')
                                                    <i class="bi bi-x-circle"></i>
                                                @elseif ($type === 'pending')
                                                    <i class="bi bi-clock"></i>
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

                @include('partials.status-toast')

                @if ($isStaff ?? false)
                    <button class="mobile-appointment-create" type="button" data-mobile-add-appointment>
                        <i class="bi bi-plus-lg" aria-hidden="true"></i> Add appointment
                    </button>
                @endif

                <section class="analytics">
                    <div class="metric-grid metric-grid-appointments">
                        <article class="metric-card">
                            <div class="metric-label">Active clients</div>
                            <div class="metric-value">{{ $stats['active_clients'] ?? 0 }}</div>
                            <div class="metric-sub">This month</div>
                        </article>
                        <article class="metric-card">
                            <div
                                class="metric-label"
                                data-appt-day-label="true"
                                data-today-iso="{{ now()->format('Y-m-d') }}"
                            >Today</div>
                            <div class="metric-value" data-appt-day-total="true">{{ $stats['total'] ?? 0 }}</div>
                            <div class="metric-sub" data-appt-day-breakdown="true">Number of Appointments</div>
                        </article>
                    </div>

                    <div class="appointments-grid">
                        <article class="data-card appointments-card">
                            <div class="card-head appointments-head">
                                <div class="appointments-title">
                                    <h3>Clients</h3>
                                    <span class="appointments-sub" data-pill-group-current="true" @if(($isPastDay ?? false) === true) style="display:none" @endif>
                                        <button class="pill pending pill-filter" type="button" data-pill-pending="true" data-status-filter="pending">{{ $stats['pending'] ?? 0 }} Pending</button>
                                        <button class="pill rescheduled pill-filter" type="button" data-pill-rescheduled="true" data-status-filter="rescheduled">{{ $stats['rescheduled'] ?? 0 }} Rescheduled</button>
                                        <button class="pill confirmed pill-filter" type="button" data-pill-confirmed="true" data-status-filter="confirmed">{{ $stats['confirmed'] ?? 0 }} Confirmed</button>
                                        <button class="pill completed pill-filter" type="button" data-pill-completed="true" data-status-filter="completed">{{ $stats['completed'] ?? 0 }} Completed</button>
                                        <button class="pill cancelled pill-filter" type="button" data-pill-cancelled="true" data-status-filter="cancelled">{{ $stats['cancelled'] ?? 0 }} Cancelled</button>
                                        <button class="pill no-show pill-filter" type="button" data-pill-no-show="true" data-status-filter="no-show">{{ $stats['no_show'] ?? 0 }} No Show</button>
                                    </span>
                                    <span class="appointments-sub" data-pill-group-past="true" @if(($isPastDay ?? false) !== true) style="display:none" @endif>
                                        <button class="pill completed pill-filter" type="button" data-pill-completed="true" data-status-filter="completed">{{ $stats['completed'] ?? 0 }} Completed</button>
                                        <button class="pill cancelled pill-filter" type="button" data-pill-cancelled="true" data-status-filter="cancelled">{{ $stats['cancelled'] ?? 0 }} Cancelled</button>
                                        <button class="pill no-show pill-filter" type="button" data-pill-no-show="true" data-status-filter="no-show">{{ $stats['no_show'] ?? 0 }} No Show</button>
                                    </span>
                                </div>
                                <div class="appointments-actions">
                                    @if ($isStaff ?? false)
                                        <button class="appt-add-btn clients-add-appointment-btn" type="button" aria-label="Add appointment">
                                            <span class="clients-add-appointment-icon" aria-hidden="true"><i class="bi bi-plus-lg"></i></span>
                                            Add Appointment
                                        </button>
                                    @endif
                                    <a
                                        class="icon-btn slim"
                                        href="#"
                                        data-appt-sort-status="true"
                                        data-status-sort-current="{{ $statusSort }}"
                                        data-status-sort-next="{{ $nextStatusSort }}"
                                        aria-label="Sort by status"
                                        title="Sort by status"
                                    >
                                        <i class="bi bi-list-check"></i>
                                    </a>
                                    <button class="icon-btn slim" type="button" aria-label="Export">
                                        <i class="bi bi-download"></i>
                                    </button>
                                </div>
                            </div>

                            <div id="appointments-list-container">
                                @include('appointments.partials.list', ['appointments' => $appointments])
                            </div>
                        </article>

                        @include('appointments.partials.side', [
                            'notifications' => $notifications,
                            'calendarMonth' => $calendarMonth ?? null,
                            'calendarYear' => $calendarYear ?? null,
                            'selectedDateIso' => $selectedDateIso ?? null,
                        ])
                    </div>
                </section>
            </main>
        </div>
    </div>

    @if ($isStaff ?? false)
    <div class="profile-modal hidden-section" id="appointment-client-type-modal" role="dialog" aria-modal="true" aria-labelledby="appointment-client-type-title">
        <div class="profile-modal-backdrop" data-close-client-type="true"></div>
        <div class="profile-modal-content client-type-modal-content">
            <button class="profile-modal-close" type="button" id="close-client-type-modal" aria-label="Close">&times;</button>
            <h3 class="profile-modal-title" id="appointment-client-type-title">Add Appointment</h3>
            <div class="reschedule-subtitle">Who is this appointment for?</div>
            <div class="client-type-choices">
                <button type="button" class="client-type-card client-type-card--new" data-pick-client-type="walk_in">
                    <span class="client-type-icon" aria-hidden="true"><i class="bi bi-person-plus"></i></span>
                    <strong>New Client</strong>
                    <span>Walk-in guest — staff sets email, password, and books the appointment</span>
                </button>
                <button type="button" class="client-type-card client-type-card--existing" data-pick-client-type="registered">
                    <span class="client-type-icon" aria-hidden="true"><i class="bi bi-person-check"></i></span>
                    <strong>Existing Client</strong>
                    <span>Returning client — search by name to auto-fill details</span>
                </button>
            </div>
        </div>
    </div>

    <div class="profile-modal hidden-section" id="add-appointment-modal" role="dialog" aria-modal="true" aria-labelledby="add-appointment-modal-title">
        <div class="profile-modal-backdrop" data-close-add-appointment="true"></div>
        <div class="profile-modal-content add-appointment-modal-content">
            <button class="profile-modal-close" type="button" id="close-add-appointment-modal" aria-label="Close">&times;</button>
            <div class="add-appointment-modal-inner">
            <button type="button" class="add-appointment-back" id="add-appointment-back-btn" aria-label="Back to client type">
                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                <span>Back</span>
            </button>
            <div class="add-appointment-head">
                <div>
                    <h3 class="profile-modal-title" id="add-appointment-modal-title">Add Appointment</h3>
                    <div class="reschedule-subtitle" id="add-appointment-subtitle">Create a booking for an existing client.</div>
                </div>
            </div>

            @if ($errors->appointment->any())
                <div class="add-appointment-errors" role="alert">
                    <strong>Please fix the following:</strong>
                    <ul>
                        @foreach ($errors->appointment->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form class="add-appointment-form" action="{{ $appointmentStoreUrl }}" method="POST" id="add-appointment-form">
                @csrf
                <input type="hidden" name="client_type" id="add-client-type" value="{{ old('client_type') }}">
                <input type="hidden" name="client_user_id" id="add-client-user-id" value="{{ old('client_user_id') }}">
                @if ($hasPaymentFields ?? false)
                    <input type="hidden" name="payment_method" id="add-payment-method" value="{{ old('payment_method') }}">
                    <input type="hidden" name="payment_type" id="add-payment-type" value="{{ old('payment_type', 'downpayment') }}">
                @endif

                <div class="add-appointment-form-scroll">
                <div id="add-walk-in-panel" class="add-client-panel add-client-panel-card hidden-section">
                    <div class="add-client-panel-head">
                        <span class="add-client-panel-icon" aria-hidden="true"><i class="bi bi-person-plus"></i></span>
                        <div>
                            <p class="add-client-panel-label">New walk-in client</p>
                            <p class="add-client-panel-desc">Create login credentials — client completes wellness profile on first sign-in.</p>
                        </div>
                    </div>
                    <div class="reschedule-grid add-client-fields">
                        <div class="profile-field">
                            <label for="add-walk-in-name">Full name</label>
                            <input id="add-walk-in-name" name="client_name" type="text" placeholder="e.g., Sarah Johnson" value="{{ old('client_type') === 'walk_in' ? old('client_name') : '' }}" autocomplete="name" required>
                        </div>
                        <div class="profile-field">
                            <label for="add-walk-in-phone">Phone</label>
                            <input id="add-walk-in-phone" name="client_phone" type="tel" placeholder="09XXXXXXXXX" value="{{ old('client_type') === 'walk_in' ? old('client_phone') : '' }}" pattern="09[0-9]{9}" maxlength="11" inputmode="numeric" required>
                        </div>
                        <div class="profile-field">
                            <label for="add-walk-in-email">Email</label>
                            <input id="add-walk-in-email" name="client_email" type="email" placeholder="client@email.com" value="{{ old('client_type') === 'walk_in' ? old('client_email') : '' }}" required>
                        </div>
                        <div class="profile-field">
                            <label for="add-walk-in-birthday">Birthday <span class="field-optional">(optional)</span></label>
                            <input id="add-walk-in-birthday" name="client_birthday" type="date" max="{{ $minimumBirthday ?? now()->subYears(15)->toDateString() }}" value="{{ old('client_type') === 'walk_in' ? old('client_birthday') : '' }}">
                        </div>
                        <div class="profile-field">
                            <label for="add-walk-in-password">Password</label>
                            <div class="profile-password-wrap">
                                <input id="add-walk-in-password" name="client_password" type="password" autocomplete="new-password" placeholder="Min. 8 characters" required>
                                <button type="button" class="profile-password-toggle" data-pw-toggle aria-label="Show password" aria-pressed="false"><i class="bi bi-eye"></i></button>
                            </div>
                        </div>
                        <div class="profile-field">
                            <label for="add-walk-in-password-confirm">Confirm password</label>
                            <div class="profile-password-wrap">
                                <input id="add-walk-in-password-confirm" name="client_password_confirmation" type="password" autocomplete="new-password" placeholder="Re-enter password" required>
                                <button type="button" class="profile-password-toggle" data-pw-toggle aria-label="Show password" aria-pressed="false"><i class="bi bi-eye"></i></button>
                            </div>
                        </div>
                        <div class="profile-field reschedule-full">
                            <span class="profile-field-label">Sex <span class="field-optional">(optional)</span></span>
                            <div class="add-sex-options">
                                <label class="add-sex-option">
                                    <input type="radio" name="client_sex" value="male" @checked(old('client_type') === 'walk_in' && old('client_sex') === 'male')>
                                    Male
                                </label>
                                <label class="add-sex-option">
                                    <input type="radio" name="client_sex" value="female" @checked(old('client_type') === 'walk_in' && old('client_sex') === 'female')>
                                    Female
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="add-registered-panel" class="add-client-panel add-client-panel-card hidden-section">
                    <div class="add-client-panel-head">
                        <span class="add-client-panel-icon add-client-panel-icon--existing" aria-hidden="true"><i class="bi bi-search"></i></span>
                        <div>
                            <p class="add-client-panel-label">Existing client lookup</p>
                            <p class="add-client-panel-desc">Search by name — contact details auto-fill when you select a match.</p>
                        </div>
                    </div>
                    <div class="reschedule-grid add-client-fields">
                        <div class="profile-field reschedule-full client-search-field">
                            <label for="add-registered-name">Client name</label>
                            <div class="client-search-input-wrap">
                                <i class="bi bi-search client-search-input-icon" aria-hidden="true"></i>
                                <input id="add-registered-name" type="text" class="client-search-input" placeholder="Type at least 2 letters to search..." value="{{ old('client_type') === 'registered' ? old('client_name') : '' }}" autocomplete="off" spellcheck="false">
                            </div>
                            <input type="hidden" id="add-registered-name-hidden" name="client_name" value="{{ old('client_type') === 'registered' ? old('client_name') : '' }}" disabled>
                            <div class="client-search-results hidden-section" id="add-client-search-results" role="listbox" aria-label="Matching clients"></div>
                            <p class="field-hint client-search-hint" id="add-registered-hint">Select a name from the list to fill in the client details.</p>
                        </div>
                        <div class="profile-field client-detail-field">
                            <label for="add-registered-email">Email</label>
                            <div class="field-auto-filled-wrap">
                                <i class="bi bi-envelope field-auto-icon" aria-hidden="true"></i>
                                <input id="add-registered-email" class="field-auto-filled" name="client_email" type="email" placeholder="Auto-filled from client record" value="{{ old('client_type') === 'registered' ? old('client_email') : '' }}" readonly tabindex="-1">
                            </div>
                        </div>
                        <div class="profile-field client-detail-field">
                            <label for="add-registered-phone">Phone</label>
                            <div class="field-auto-filled-wrap">
                                <i class="bi bi-telephone field-auto-icon" aria-hidden="true"></i>
                                <input id="add-registered-phone" class="field-auto-filled" name="client_phone" type="tel" placeholder="Auto-filled from client record" value="{{ old('client_type') === 'registered' ? old('client_phone') : '' }}" readonly tabindex="-1">
                            </div>
                        </div>
                    </div>
                </div>


                <div class="add-booking-section">
                    <p class="add-booking-section-label">Appointment details</p>
                <div class="reschedule-grid add-booking-grid">
                    <div class="profile-field">
                        <label for="add-service">Service</label>
                        <select id="add-service" name="service" class="reschedule-select" required>
                            <option value="" disabled {{ old('service') ? '' : 'selected' }}>Select a service</option>
                            @foreach ($serviceOptions as $serviceName)
                                <option value="{{ $serviceName }}" @selected(old('service') === $serviceName)>{{ $serviceName }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="profile-field">
                        <label for="add-date">Date</label>
                        <input id="add-date" name="booking_date" type="date" min="{{ $today }}" value="{{ old('booking_date', $selectedDateIso ?? $today) }}" required>
                    </div>

                    <div class="profile-field">
                        <label for="add-therapist">Therapist</label>
                        <select id="add-therapist" name="therapist" class="reschedule-select">
                            <option value="" @selected(old('therapist') === null || old('therapist') === '')>Auto assign</option>
                            @foreach ($therapistOptions as $therapistName)
                                <option value="{{ $therapistName }}" @selected(old('therapist') === $therapistName)>{{ $therapistName }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="profile-field reschedule-full">
                        <label>Available time slots</label>
                        <p class="appt-slots-hint" id="add-slots-hint">Select service and date to load available times.</p>
                        <div class="appt-time-slots add-time-slots-scroll" id="add-time-slots"></div>
                        <input type="hidden" name="time_slot" id="add-time-slot" value="{{ old('time_slot') }}">
                    </div>

                    <div class="profile-field reschedule-full">
                        <label for="add-notes">Notes</label>
                        <textarea id="add-notes" name="notes" class="reschedule-textarea add-notes-compact" rows="2" placeholder="Optional notes...">{{ old('notes') }}</textarea>
                    </div>
                </div>
                </div>

                @if ($hasPaymentFields ?? false)
                <div class="add-booking-section add-payment-section">
                    <p class="add-booking-section-label">Payment</p>
                    <div class="appt-payment-layout">
                        <div class="appt-payment-left">
                            <div class="appt-payment-summary">
                                <div class="appt-payment-summary-row">
                                    <span class="appt-payment-summary-key">Service price</span>
                                    <span class="appt-payment-summary-val" id="add-payment-service-price">—</span>
                                </div>
                                <div class="appt-payment-summary-row">
                                    <span class="appt-payment-summary-key">Amount due</span>
                                    <span class="appt-payment-summary-val appt-payment-summary-val--amount" id="add-payment-amount-due">—</span>
                                </div>
                            </div>

                            <div class="appt-payment-block">
                                <p class="appt-payment-label">Payment type</p>
                                <div class="appt-payment-type-group" role="radiogroup" aria-label="Payment type">
                                    <button type="button" class="appt-payment-type" data-add-payment-type="downpayment">Downpayment (50%)</button>
                                    <button type="button" class="appt-payment-type" data-add-payment-type="full">Full payment</button>
                                </div>
                            </div>

                            <div class="appt-payment-block">
                                <p class="appt-payment-label">Payment method</p>
                                <div class="appt-payment-methods" role="group" aria-label="Payment method">
                                    @foreach ($paymentMethods as $key => $method)
                                        <button
                                            type="button"
                                            class="appt-payment-method"
                                            data-add-payment-method="{{ $key }}"
                                        >{{ $method['label'] }}</button>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="appt-payment-right">
                            <div class="appt-payment-info-panel">
                                <p class="appt-payment-label">How payment works</p>
                                <p class="appt-payment-hint" id="add-payment-transaction-hint">Choose PayMongo to open secure checkout after creating the appointment. The payment is marked paid when PayMongo confirms it.</p>
                                <p class="appt-payment-hint hidden-section" id="add-payment-cash-hint">Collect the amount due at the counter. The booking will be marked paid and a receipt will be shown.</p>
                                <p class="appt-payment-error hidden-section" id="add-payment-transaction-error" role="alert"></p>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                </div>

                <div class="profile-modal-actions add-appointment-modal-actions">
                    <button type="button" class="user-action" id="add-appointment-cancel-btn">Cancel</button>
                    <button type="submit" class="user-action add" id="add-appointment-save-btn">Confirm booking</button>
                </div>
            </form>
            </div>
        </div>
    </div>
    @endif

    @if ($isStaff ?? false)
    <div class="profile-modal hidden-section" id="reschedule-appointment-modal" role="dialog" aria-modal="true" aria-labelledby="reschedule-appointment-modal-title">
        <div class="profile-modal-backdrop" data-close-reschedule="true"></div>
        <div class="profile-modal-content reschedule-modal-content">
            <button class="profile-modal-close" type="button" id="close-reschedule-modal" aria-label="Close">&times;</button>
            <h3 class="profile-modal-title" id="reschedule-appointment-modal-title">Reschedule Appointment</h3>
            <div class="reschedule-subtitle">Pick a new date and time. Availability is checked in real time.</div>

            <div class="reschedule-grid">
                <div class="profile-field">
                    <label>Client</label>
                    <input id="reschedule-client" type="text" readonly>
                </div>
                <div class="profile-field">
                    <label>Service</label>
                    <input id="reschedule-service" type="text" readonly>
                </div>
                <div class="profile-field">
                    <label>Therapist</label>
                    <input id="reschedule-therapist" type="text" readonly>
                </div>
                <div class="profile-field">
                    <label>Current schedule</label>
                    <input id="reschedule-current" type="text" readonly>
                </div>
                <div class="profile-field">
                    <label for="reschedule-date">New date</label>
                    <input id="reschedule-date" type="date" min="{{ $today ?? now()->toDateString() }}">
                </div>
                <div class="profile-field reschedule-full">
                    <label>New time</label>
                    <p class="appt-slots-hint" id="reschedule-slots-hint">Select a date to load available times.</p>
                    <div class="appt-time-slots" id="reschedule-time-slots"></div>
                    <input type="hidden" id="reschedule-time-slot" value="">
                </div>
            </div>

            <p class="add-appointment-errors reschedule-inline-error hidden-section" id="reschedule-error" role="alert"></p>

            <div class="profile-modal-actions">
                <button type="button" class="user-action" id="reschedule-cancel-btn">Cancel</button>
                <button type="button" class="user-action add" id="reschedule-save-btn" disabled>Save new schedule</button>
            </div>
        </div>
    </div>
    @endif

    @if ($isStaff ?? false)
    <div class="profile-modal hidden-section" id="cancel-appointment-modal" role="dialog" aria-modal="true" aria-labelledby="cancel-appointment-modal-title">
        <div class="profile-modal-backdrop" data-close-cancel="true"></div>
        <div class="profile-modal-content reschedule-modal-content">
            <button class="profile-modal-close" type="button" id="close-cancel-modal" aria-label="Close">&times;</button>
            <h3 class="profile-modal-title" id="cancel-appointment-modal-title">Cancel Appointment</h3>
            <div class="reschedule-subtitle">This will mark the booking as cancelled for walk-in and existing clients.</div>

            <div class="reschedule-grid">
                <div class="profile-field">
                    <label>Client</label>
                    <input id="cancel-client" type="text" readonly>
                </div>
                <div class="profile-field">
                    <label>Service</label>
                    <input id="cancel-service" type="text" readonly>
                </div>
                <div class="profile-field">
                    <label>Therapist</label>
                    <input id="cancel-therapist" type="text" readonly>
                </div>
                <div class="profile-field">
                    <label>Schedule</label>
                    <input id="cancel-schedule" type="text" readonly>
                </div>
                <div class="profile-field reschedule-full">
                    <label for="cancel-note">Reason (optional)</label>
                    <textarea id="cancel-note" class="reschedule-textarea" rows="3" maxlength="500" placeholder="e.g. Client requested cancellation"></textarea>
                </div>
            </div>

            <p class="add-appointment-errors reschedule-inline-error hidden-section" id="cancel-error" role="alert"></p>

            <div class="profile-modal-actions">
                <button type="button" class="user-action" id="cancel-dismiss-btn">Keep appointment</button>
                <button type="button" class="user-action delete" id="cancel-confirm-btn">Confirm cancellation</button>
            </div>
        </div>
    </div>
    @endif

    <div class="profile-modal hidden-section" id="view-appointment-modal" role="dialog" aria-modal="true" aria-labelledby="view-appointment-modal-title">
        <div class="profile-modal-backdrop" data-close-view="true"></div>
        <div class="profile-modal-content view-modal-content">
            <button class="profile-modal-close" type="button" id="close-view-modal" aria-label="Close">&times;</button>
            <h3 class="profile-modal-title" id="view-appointment-modal-title">Appointment Details</h3>
            <div class="reschedule-subtitle" id="view-modal-subtitle">Review the booking details.</div>

            <div class="reschedule-grid">
                <div class="profile-field">
                    <label>Client</label>
                    <input id="view-client" type="text" readonly>
                </div>
                <div class="profile-field">
                    <label>Status</label>
                    <input id="view-status" type="text" readonly>
                </div>
                <div class="profile-field">
                    <label>Service</label>
                    <input id="view-service" type="text" readonly>
                </div>
                <div class="profile-field">
                    <label>Therapist</label>
                    <input id="view-therapist" type="text" readonly>
                </div>
                <div class="profile-field">
                    <label>Date</label>
                    <input id="view-date" type="text" readonly>
                </div>
                <div class="profile-field">
                    <label>Time</label>
                    <input id="view-time" type="text" readonly>
                </div>
                <div class="profile-field reschedule-full">
                    <label>Notes</label>
                    <textarea id="view-notes" class="reschedule-textarea" rows="3" readonly></textarea>
                </div>
                <div class="profile-field" id="view-payment-method-wrap">
                    <label>Payment method</label>
                    <input id="view-payment-method" type="text" readonly>
                </div>
                <div class="profile-field" id="view-payment-type-wrap">
                    <label>Payment type</label>
                    <input id="view-payment-type" type="text" readonly>
                </div>
                <div class="profile-field" id="view-payment-amount-wrap">
                    <label>Payment amount</label>
                    <input id="view-payment-amount" type="text" readonly>
                </div>
                <div class="profile-field" id="view-payment-status-wrap">
                    <label>Payment status</label>
                    <input id="view-payment-status" type="text" readonly>
                </div>
                <div class="profile-field" id="view-service-amount-wrap">
                    <label>Service price</label>
                    <input id="view-service-amount" type="text" readonly>
                </div>
                <div class="profile-field" id="view-payment-transaction-wrap">
                    <label>Transaction ID</label>
                    <input id="view-payment-transaction" type="text" readonly>
                </div>
                <div class="profile-field reschedule-full" id="view-payment-proof-wrap">
                    <label>Payment proof</label>
                    <div class="view-payment-proof-shell">
                        <a id="view-payment-proof-link" href="#" target="_blank" rel="noopener noreferrer" class="view-payment-proof-link">View screenshot</a>
                        <span id="view-payment-proof-empty" class="view-payment-proof-empty">No proof uploaded.</span>
                    </div>
                </div>
                <div class="profile-field reschedule-full hidden-section" id="view-refund-wrap">
                    <label>Refund</label>
                    <textarea id="view-refund-status" class="reschedule-textarea" rows="2" readonly></textarea>
                    <p class="field-hint" id="view-refund-note"></p>
                </div>
            </div>

            <div class="profile-modal-actions view-modal-actions">
                <a class="user-action add hidden-section" id="view-retry-paymongo-link" href="#">Resume PayMongo checkout</a>
                <button type="button" class="user-action hidden-section" id="view-complete-refund-btn">Mark refund complete</button>
                <button type="button" class="user-action" id="view-close-btn">Close</button>
            </div>
        </div>
    </div>

    <script>
        const addAppointmentBtn = document.querySelector('.appt-add-btn');
        const clientTypeModal = document.getElementById('appointment-client-type-modal');
        const closeClientTypeModalBtn = document.getElementById('close-client-type-modal');
        const addAppointmentModal = document.getElementById('add-appointment-modal');
        const closeAddAppointmentModalBtn = document.getElementById('close-add-appointment-modal');
        const addAppointmentBackBtn = document.getElementById('add-appointment-back-btn');
        const addAppointmentCancelBtn = document.getElementById('add-appointment-cancel-btn');
        const addAppointmentForm = document.querySelector('.add-appointment-form');
        const addAppointmentSubtitle = document.getElementById('add-appointment-subtitle');
        const addClientTypeInput = document.getElementById('add-client-type');
        const addClientUserIdInput = document.getElementById('add-client-user-id');
        const addWalkInPanel = document.getElementById('add-walk-in-panel');
        const addRegisteredPanel = document.getElementById('add-registered-panel');
        const addWalkInName = document.getElementById('add-walk-in-name');
        const addWalkInPhone = document.getElementById('add-walk-in-phone');
        const addWalkInEmail = document.getElementById('add-walk-in-email');
        const addWalkInBirthday = document.getElementById('add-walk-in-birthday');
        const addWalkInPassword = document.getElementById('add-walk-in-password');
        const addWalkInPasswordConfirm = document.getElementById('add-walk-in-password-confirm');
        const addRegisteredName = document.getElementById('add-registered-name');
        const addRegisteredNameHidden = document.getElementById('add-registered-name-hidden');
        const addRegisteredEmail = document.getElementById('add-registered-email');
        const addRegisteredPhone = document.getElementById('add-registered-phone');
        const addRegisteredHint = document.getElementById('add-registered-hint');
        const addClientSearchResults = document.getElementById('add-client-search-results');
        const staffAvailabilityUrl = @json($staffAvailabilityUrl ?? '');
        const clientSearchUrl = @json($clientSearchUrl ?? route('appointments.clients.search'));
        const addServiceSelect = document.getElementById('add-service');
        const addDateInput = document.getElementById('add-date');
        const addTherapistSelect = document.getElementById('add-therapist');
        const addSlotsWrap = document.getElementById('add-time-slots');
        const addSlotsHint = document.getElementById('add-slots-hint');
        const addHiddenSlot = document.getElementById('add-time-slot');
        const shouldOpenAddAppointment = @json($openAddAppointment ?? false);
        const initialClientType = @json(old('client_type'));
        const addServicePriceMap = @json($servicePriceMap ?? []);
        const addHasPaymentFields = @json($hasPaymentFields ?? false);
        let addAvailabilityTimer = null;
        let addSlotsLoading = false;
        let clientSearchTimer = null;
        let clientSearchRequest = null;
        let activeClientType = '';

        const addPaymentMethodInput = document.getElementById('add-payment-method');
        const addPaymentTypeInput = document.getElementById('add-payment-type');
        const addPaymentTransactionError = document.getElementById('add-payment-transaction-error');
        const addPaymentServicePrice = document.getElementById('add-payment-service-price');
        const addPaymentAmountDue = document.getElementById('add-payment-amount-due');
        const addPaymentTypeButtons = Array.from(document.querySelectorAll('[data-add-payment-type]'));
        const addPaymentMethodButtons = Array.from(document.querySelectorAll('[data-add-payment-method]'));
        const addAppointmentSaveButton = document.getElementById('add-appointment-save-btn');
        const addPaymentTransactionHint = document.getElementById('add-payment-transaction-hint');
        const addPaymentCashHint = document.getElementById('add-payment-cash-hint');
        const CASH_COUNTER_METHOD = 'cash_counter';
        let selectedAddPaymentType = addPaymentTypeInput instanceof HTMLInputElement ? (addPaymentTypeInput.value || 'downpayment') : 'downpayment';
        let selectedAddPaymentMethod = addPaymentMethodInput instanceof HTMLInputElement ? (addPaymentMethodInput.value || '') : '';

        function isCashCounterSelected() {
            return selectedAddPaymentMethod === CASH_COUNTER_METHOD;
        }

        function syncAddPaymentTransactionPanel() {
            if (!addHasPaymentFields) return;
            const cash = isCashCounterSelected();
            if (addPaymentTransactionHint) addPaymentTransactionHint.classList.toggle('hidden-section', cash);
            if (addPaymentCashHint) addPaymentCashHint.classList.toggle('hidden-section', !cash);
            if (addAppointmentSaveButton) addAppointmentSaveButton.textContent = cash ? 'Confirm booking' : 'Continue to PayMongo';
        }

        function formatAddCurrency(amount) {
            return '₱' + Number(amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function addServicePriceFor(name) {
            if (!name || !addServicePriceMap) return 0;
            return Number(addServicePriceMap[name] || 0);
        }

        function addPaymentAmountFor(serviceName, type) {
            const price = addServicePriceFor(serviceName);
            if (type === 'full') return price;
            return Math.round(price * 0.5 * 100) / 100;
        }

        function updateAddPaymentSummary() {
            if (!addHasPaymentFields) return;
            const serviceName = addServiceSelect instanceof HTMLSelectElement ? addServiceSelect.value : '';
            const price = addServicePriceFor(serviceName);
            const due = addPaymentAmountFor(serviceName, selectedAddPaymentType);
            if (addPaymentServicePrice) addPaymentServicePrice.textContent = serviceName ? formatAddCurrency(price) : '—';
            if (addPaymentAmountDue) addPaymentAmountDue.textContent = serviceName ? formatAddCurrency(due) : '—';
        }

        function paintAddPaymentTypeButtons() {
            addPaymentTypeButtons.forEach((btn) => {
                const type = btn.getAttribute('data-add-payment-type') || '';
                btn.classList.toggle('is-active', type === selectedAddPaymentType);
            });
            if (addPaymentTypeInput instanceof HTMLInputElement) addPaymentTypeInput.value = selectedAddPaymentType;
        }

        function paintAddPaymentMethodButtons() {
            addPaymentMethodButtons.forEach((btn) => {
                const method = btn.getAttribute('data-add-payment-method') || '';
                btn.classList.toggle('is-active', method === selectedAddPaymentMethod);
            });
            if (addPaymentMethodInput instanceof HTMLInputElement) addPaymentMethodInput.value = selectedAddPaymentMethod;
        }

        function hideAddPaymentTransactionError() {
            if (!addPaymentTransactionError) return;
            addPaymentTransactionError.textContent = '';
            addPaymentTransactionError.classList.add('hidden-section');
        }

        function showAddPaymentTransactionError(message) {
            if (!addPaymentTransactionError) return;
            addPaymentTransactionError.textContent = message;
            addPaymentTransactionError.classList.remove('hidden-section');
        }

        function hideAllAddPaymentErrors() {
            hideAddPaymentTransactionError();
        }

        function syncAddPaymentClientMode() {
            if (!addHasPaymentFields) return;
            syncAddPaymentTransactionPanel();
            hideAllAddPaymentErrors();
        }

        function resetAddPaymentFields() {
            if (!addHasPaymentFields) return;
            selectedAddPaymentType = addPaymentTypeInput instanceof HTMLInputElement ? (addPaymentTypeInput.value || 'downpayment') : 'downpayment';
            selectedAddPaymentMethod = addPaymentMethodInput instanceof HTMLInputElement ? (addPaymentMethodInput.value || '') : '';
            paintAddPaymentTypeButtons();
            paintAddPaymentMethodButtons();
            updateAddPaymentSummary();
            hideAllAddPaymentErrors();
            syncAddPaymentClientMode();
        }

        addPaymentTypeButtons.forEach((btn) => {
            btn.addEventListener('click', () => {
                selectedAddPaymentType = btn.getAttribute('data-add-payment-type') || 'downpayment';
                paintAddPaymentTypeButtons();
                updateAddPaymentSummary();
            });
        });

        addPaymentMethodButtons.forEach((btn) => {
            btn.addEventListener('click', () => {
                selectedAddPaymentMethod = btn.getAttribute('data-add-payment-method') || '';
                paintAddPaymentMethodButtons();
                syncAddPaymentTransactionPanel();
                hideAllAddPaymentErrors();
            });
        });

        function setWalkInFieldsEnabled(enabled) {
            [addWalkInName, addWalkInPhone, addWalkInEmail, addWalkInBirthday, addWalkInPassword, addWalkInPasswordConfirm].forEach((field) => {
                if (field instanceof HTMLInputElement) field.disabled = !enabled;
            });
            addWalkInPanel?.querySelectorAll('input[name="client_sex"]').forEach((input) => {
                if (input instanceof HTMLInputElement) input.disabled = !enabled;
            });
        }

        function setRegisteredFieldsEnabled(enabled) {
            [addRegisteredNameHidden, addRegisteredEmail, addRegisteredPhone].forEach((field) => {
                if (field instanceof HTMLInputElement) field.disabled = !enabled;
            });
        }

        function clearRegisteredClientSelection() {
            if (addClientUserIdInput instanceof HTMLInputElement) addClientUserIdInput.value = '';
            if (addRegisteredNameHidden instanceof HTMLInputElement) addRegisteredNameHidden.value = '';
            if (addRegisteredEmail instanceof HTMLInputElement) addRegisteredEmail.value = '';
            if (addRegisteredPhone instanceof HTMLInputElement) addRegisteredPhone.value = '';
            addRegisteredName?.classList.remove('is-client-selected');
            if (addRegisteredHint) {
                addRegisteredHint.textContent = 'Select a name from the list to fill in the client details.';
                addRegisteredHint.classList.remove('is-success');
            }
        }

        function hideClientSearchResults() {
            addClientSearchResults?.classList.add('hidden-section');
            if (addClientSearchResults) addClientSearchResults.innerHTML = '';
        }

        function applyClientType(type, preserveFields = false) {
            activeClientType = type;
            if (addClientTypeInput instanceof HTMLInputElement) addClientTypeInput.value = type;

            const isWalkIn = type === 'walk_in';
            addWalkInPanel?.classList.toggle('hidden-section', !isWalkIn);
            addRegisteredPanel?.classList.toggle('hidden-section', isWalkIn);

            setWalkInFieldsEnabled(isWalkIn);
            setRegisteredFieldsEnabled(!isWalkIn);

            if (addAppointmentSubtitle) {
                addAppointmentSubtitle.textContent = isWalkIn
                    ? 'Register a new walk-in client and book their appointment.'
                    : 'Search for an existing client — details auto-fill when you select a name.';
            }

            if (!isWalkIn && !preserveFields) {
                clearRegisteredClientSelection();
            } else if (isWalkIn && addClientUserIdInput instanceof HTMLInputElement) {
                addClientUserIdInput.value = '';
            }

            if (addWalkInName instanceof HTMLInputElement) addWalkInName.required = isWalkIn;
            if (addWalkInPhone instanceof HTMLInputElement) addWalkInPhone.required = isWalkIn;
            if (addWalkInEmail instanceof HTMLInputElement) addWalkInEmail.required = isWalkIn;
            if (addWalkInPassword instanceof HTMLInputElement) addWalkInPassword.required = isWalkIn;
            if (addWalkInPasswordConfirm instanceof HTMLInputElement) addWalkInPasswordConfirm.required = isWalkIn;

            hideClientSearchResults();
            scheduleAddAvailabilityRefresh();
            syncAddPaymentClientMode();
        }

        function openClientTypeModal() {
            clientTypeModal?.classList.remove('hidden-section');
            document.body.classList.add('modal-open');
        }

        function hideClientTypeModal() {
            clientTypeModal?.classList.add('hidden-section');
            document.body.classList.remove('modal-open');
        }

        function openAddAppointmentModal(type = '', preserveFields = false) {
            hideClientTypeModal();
            if (type) applyClientType(type, preserveFields);
            addAppointmentModal?.classList.remove('hidden-section');
            document.body.classList.add('modal-open');
            refreshAddAppointmentSlots();
            resetAddPaymentFields();
            if (type === 'walk_in' && typeof window.tnrResetPasswordVisibility === 'function') {
                window.tnrResetPasswordVisibility(addWalkInPanel);
            }
            (type === 'registered' ? addRegisteredName : addWalkInName)?.focus();
        }

        function hideAddAppointmentModal() {
            addAppointmentModal?.classList.add('hidden-section');
            document.body.classList.remove('modal-open');
            hideClientSearchResults();
        }

        function paintClientSearchResults(clients) {
            if (!addClientSearchResults) return;
            addClientSearchResults.innerHTML = '';

            if (!Array.isArray(clients) || clients.length === 0) {
                const empty = document.createElement('p');
                empty.className = 'client-search-empty';
                empty.textContent = 'No existing clients matched that name.';
                addClientSearchResults.appendChild(empty);
                addClientSearchResults.classList.remove('hidden-section');
                return;
            }

            clients.forEach((client) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'client-search-option';
                button.setAttribute('role', 'option');
                button.dataset.userId = String(client.user_id ?? '');
                button.dataset.name = client.name ?? '';
                button.dataset.email = client.email ?? '';
                button.dataset.phone = client.phone ?? '';
                button.innerHTML = `<strong>${client.name ?? ''}</strong><span>${client.email ?? ''}${client.phone ? ' · ' + client.phone : ''}</span>`;
                addClientSearchResults.appendChild(button);
            });

            addClientSearchResults.classList.remove('hidden-section');
        }

        async function searchRegisteredClients(query) {
            if (!clientSearchUrl || query.trim().length < 2) {
                hideClientSearchResults();
                return;
            }

            if (clientSearchRequest) clientSearchRequest.abort();
            clientSearchRequest = new AbortController();

            try {
                const url = new URL(clientSearchUrl, window.location.origin);
                url.searchParams.set('q', query.trim());
                const res = await fetch(url.toString(), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    signal: clientSearchRequest.signal,
                });
                if (!res.ok) throw new Error('search failed');
                const payload = await res.json();
                paintClientSearchResults(payload.clients ?? []);
            } catch (error) {
                if (error?.name === 'AbortError') return;
                hideClientSearchResults();
            } finally {
                clientSearchRequest = null;
            }
        }

        function selectRegisteredClient(button) {
            if (!(button instanceof HTMLElement)) return;

            const userId = button.dataset.userId ?? '';
            const name = button.dataset.name ?? '';
            const email = button.dataset.email ?? '';
            const phone = button.dataset.phone ?? '';

            if (addRegisteredName instanceof HTMLInputElement) addRegisteredName.value = name;
            addRegisteredName?.classList.add('is-client-selected');
            if (addRegisteredNameHidden instanceof HTMLInputElement) addRegisteredNameHidden.value = name;
            if (addRegisteredEmail instanceof HTMLInputElement) addRegisteredEmail.value = email;
            if (addRegisteredPhone instanceof HTMLInputElement) addRegisteredPhone.value = phone;
            if (addClientUserIdInput instanceof HTMLInputElement) addClientUserIdInput.value = userId;

            if (addRegisteredHint) {
                addRegisteredHint.textContent = userId
                    ? 'Client details loaded. You can continue booking below.'
                    : 'Client selected, but no linked account was found.';
                addRegisteredHint.classList.toggle('is-success', !!userId);
            }

            hideClientSearchResults();
            scheduleAddAvailabilityRefresh();
        }

        function isSelectedTherapistOffDuty(payload, therapistName) {
            if (!therapistName) return false;
            const schedule = payload?.therapist_schedule;
            return !!(schedule && schedule.bookable === false);
        }

        function therapistOffDutyHint(payload, therapistName) {
            const schedule = payload?.therapist_schedule || {};
            const label = schedule.label || 'Off duty';
            const name = therapistName || 'Selected therapist';
            return `${name} is ${label} on the selected date. Choose another therapist or date.`;
        }

        function paintAddAppointmentSlots(payload) {
            if (!addSlotsWrap || !addHiddenSlot) return;

            const offered = Array.isArray(payload?.offered_slots) ? payload.offered_slots : [];
            const fullyBooked = new Set(Array.isArray(payload?.fully_booked_slots) ? payload.fully_booked_slots : []);
            const therapistBusy = new Set(Array.isArray(payload?.booked_slots) ? payload.booked_slots : []);
            const userConflicts = payload?.user_conflicts && typeof payload.user_conflicts === 'object' ? payload.user_conflicts : {};
            const storeClosed = payload?.store_closed === true;
            const selected = addHiddenSlot.value || '';
            const therapist = addTherapistSelect instanceof HTMLSelectElement ? addTherapistSelect.value.trim() : '';
            const therapistOffDuty = isSelectedTherapistOffDuty(payload, therapist);

            addSlotsWrap.innerHTML = '';

            if (storeClosed) {
                if (addSlotsHint) addSlotsHint.textContent = 'The spa is closed on this date.';
                addHiddenSlot.value = '';
                return;
            }

            if (!offered.length) {
                if (addSlotsHint) addSlotsHint.textContent = 'No time slots are offered for this service on the selected date.';
                addHiddenSlot.value = '';
                return;
            }

            if (therapistOffDuty) {
                if (addSlotsHint) addSlotsHint.textContent = therapistOffDutyHint(payload, therapist);
                offered.forEach((slot) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'appt-time-slot unavailable therapist-busy';
                    btn.textContent = slot;
                    btn.disabled = true;
                    btn.title = therapistOffDutyHint(payload, therapist);
                    addSlotsWrap.appendChild(btn);
                });
                addHiddenSlot.value = '';
                return;
            }

            if (addSlotsHint) {
                addSlotsHint.textContent = therapist
                    ? 'Green slots are open for the selected therapist.'
                    : 'Green slots have at least one available therapist (auto assign).';
            }

            offered.forEach((slot) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'appt-time-slot';
                btn.textContent = slot;

                const isFullyBooked = fullyBooked.has(slot);
                const isTherapistBusy = therapist !== '' && therapistBusy.has(slot);
                const hasUserConflict = Object.prototype.hasOwnProperty.call(userConflicts, slot);

                if (isFullyBooked) {
                    btn.classList.add('unavailable', 'fully-booked');
                    btn.disabled = true;
                    btn.title = 'All therapists are booked';
                } else if (isTherapistBusy) {
                    btn.classList.add('therapist-busy');
                    btn.disabled = true;
                    btn.title = 'Selected therapist is busy';
                } else if (hasUserConflict) {
                    btn.classList.add('user-conflict');
                    btn.disabled = true;
                    btn.title = 'Client already has an appointment at this time';
                }

                if (slot === selected && !btn.disabled) {
                    btn.classList.add('active');
                }

                btn.addEventListener('click', () => {
                    if (btn.disabled || btn.classList.contains('unavailable') || btn.classList.contains('therapist-busy')) return;
                    addHiddenSlot.value = slot;
                    addSlotsWrap.querySelectorAll('.appt-time-slot.active').forEach((el) => el.classList.remove('active'));
                    btn.classList.add('active');
                });

                addSlotsWrap.appendChild(btn);
            });

            if (selected && !offered.includes(selected)) {
                addHiddenSlot.value = '';
            }
        }

        const phonePattern = /^09\d{9}$/;

        async function refreshAddAppointmentSlots() {
            if (!staffAvailabilityUrl || !addServiceSelect || !addDateInput) return;

            const service = addServiceSelect instanceof HTMLSelectElement ? addServiceSelect.value : '';
            const bookingDate = addDateInput instanceof HTMLInputElement ? addDateInput.value : '';
            const therapist = addTherapistSelect instanceof HTMLSelectElement ? addTherapistSelect.value.trim() : '';
            const rawEmail = activeClientType === 'walk_in'
                ? (addWalkInEmail instanceof HTMLInputElement ? addWalkInEmail.value.trim() : '')
                : (addRegisteredEmail instanceof HTMLInputElement ? addRegisteredEmail.value.trim() : '');
            const rawPhone = activeClientType === 'walk_in'
                ? (addWalkInPhone instanceof HTMLInputElement ? addWalkInPhone.value.trim() : '')
                : (addRegisteredPhone instanceof HTMLInputElement ? addRegisteredPhone.value.trim() : '');
            const clientEmail = rawEmail.includes('@') ? rawEmail : '';
            const clientPhone = phonePattern.test(rawPhone) ? rawPhone : '';
            const clientName = activeClientType === 'walk_in'
                ? (addWalkInName instanceof HTMLInputElement ? addWalkInName.value.trim() : '')
                : (addRegisteredNameHidden instanceof HTMLInputElement ? addRegisteredNameHidden.value.trim() : '');
            const clientUserId = addClientUserIdInput instanceof HTMLInputElement ? addClientUserIdInput.value.trim() : '';

            if (!service || !bookingDate) {
                if (addSlotsWrap) addSlotsWrap.innerHTML = '';
                if (addSlotsHint) addSlotsHint.textContent = 'Select service and date to load available times.';
                return;
            }

            if (addSlotsLoading) return;
            addSlotsLoading = true;
            if (addSlotsHint) addSlotsHint.textContent = 'Loading availability...';

            try {
                const url = new URL(staffAvailabilityUrl, window.location.origin);
                url.searchParams.set('service', service);
                url.searchParams.set('booking_date', bookingDate);
                if (therapist) url.searchParams.set('therapist', therapist);
                if (clientEmail) url.searchParams.set('client_email', clientEmail);
                if (clientPhone) url.searchParams.set('client_phone', clientPhone);
                if (clientName) url.searchParams.set('client_name', clientName);
                if (clientUserId) url.searchParams.set('client_user_id', clientUserId);

                const res = await fetch(url.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!res.ok) throw new Error('availability failed');
                const payload = await res.json();
                paintAddAppointmentSlots(payload);
            } catch (e) {
                if (addSlotsHint) addSlotsHint.textContent = 'Could not load availability. Please try again.';
            } finally {
                addSlotsLoading = false;
            }
        }

        function scheduleAddAvailabilityRefresh() {
            window.clearTimeout(addAvailabilityTimer);
            addAvailabilityTimer = window.setTimeout(refreshAddAppointmentSlots, 250);
        }

        addAppointmentBtn?.addEventListener('click', (event) => {
            event.preventDefault();
            openClientTypeModal();
        });
        document.querySelector('[data-mobile-add-appointment]')?.addEventListener('click', openClientTypeModal);

        document.querySelectorAll('[data-pick-client-type]').forEach((button) => {
            button.addEventListener('click', () => {
                const type = button instanceof HTMLElement ? (button.dataset.pickClientType ?? '') : '';
                if (type === 'walk_in' || type === 'registered') {
                    openAddAppointmentModal(type);
                }
            });
        });

        closeClientTypeModalBtn?.addEventListener('click', hideClientTypeModal);
        clientTypeModal?.addEventListener('click', (event) => {
            const target = event.target;
            if (target instanceof HTMLElement && target.dataset.closeClientType === 'true') hideClientTypeModal();
        });

        addAppointmentBackBtn?.addEventListener('click', () => {
            hideAddAppointmentModal();
            openClientTypeModal();
        });

        closeAddAppointmentModalBtn?.addEventListener('click', hideAddAppointmentModal);
        addAppointmentCancelBtn?.addEventListener('click', hideAddAppointmentModal);
        addAppointmentModal?.addEventListener('click', (event) => {
            const target = event.target;
            if (target instanceof HTMLElement && target.dataset.closeAddAppointment === 'true') hideAddAppointmentModal();
        });

        addServiceSelect?.addEventListener('change', () => {
            scheduleAddAvailabilityRefresh();
            updateAddPaymentSummary();
        });
        addDateInput?.addEventListener('change', scheduleAddAvailabilityRefresh);
        addTherapistSelect?.addEventListener('change', scheduleAddAvailabilityRefresh);
        addWalkInEmail?.addEventListener('input', scheduleAddAvailabilityRefresh);
        addWalkInPhone?.addEventListener('input', scheduleAddAvailabilityRefresh);
        addWalkInName?.addEventListener('input', scheduleAddAvailabilityRefresh);

        addRegisteredName?.addEventListener('input', () => {
            clearRegisteredClientSelection();
            window.clearTimeout(clientSearchTimer);
            const query = addRegisteredName instanceof HTMLInputElement ? addRegisteredName.value : '';
            clientSearchTimer = window.setTimeout(() => searchRegisteredClients(query), 250);
        });

        addClientSearchResults?.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) return;
            const option = target.closest('.client-search-option');
            if (option instanceof HTMLElement) selectRegisteredClient(option);
        });

        addAppointmentForm?.addEventListener('submit', (event) => {
            if (!addHiddenSlot?.value) {
                event.preventDefault();
                if (addSlotsHint) addSlotsHint.textContent = 'Please select an available time slot.';
                return;
            }

            if (activeClientType === 'registered' && !(addClientUserIdInput instanceof HTMLInputElement && addClientUserIdInput.value.trim())) {
                event.preventDefault();
                if (addRegisteredHint) {
                    addRegisteredHint.textContent = 'Please select an existing client from the matching list.';
                    addRegisteredHint.classList.remove('is-success');
                }
                return;
            }

            if (addHasPaymentFields) {
                hideAllAddPaymentErrors();
                if (!selectedAddPaymentMethod) {
                    event.preventDefault();
                    showAddPaymentTransactionError('Please select a payment method.');
                    return;
                }

                if (addPaymentMethodInput instanceof HTMLInputElement) addPaymentMethodInput.value = selectedAddPaymentMethod;
                if (addPaymentTypeInput instanceof HTMLInputElement) addPaymentTypeInput.value = selectedAddPaymentType;
            }
        });

        if (shouldOpenAddAppointment && (initialClientType === 'walk_in' || initialClientType === 'registered')) {
            openAddAppointmentModal(initialClientType, true);
            if (addHasPaymentFields) {
                paintAddPaymentTypeButtons();
                paintAddPaymentMethodButtons();
                updateAddPaymentSummary();
            }
        } else if (shouldOpenAddAppointment) {
            openClientTypeModal();
        }

        if (addHasPaymentFields) {
            paintAddPaymentTypeButtons();
            paintAddPaymentMethodButtons();
            updateAddPaymentSummary();
            syncAddPaymentClientMode();
        }

        const rescheduleModal = document.getElementById('reschedule-appointment-modal');
        const closeRescheduleModalBtn = document.getElementById('close-reschedule-modal');
        const rescheduleCancelBtn = document.getElementById('reschedule-cancel-btn');
        const rescheduleSaveBtn = document.getElementById('reschedule-save-btn');
        const rescheduleClientInput = document.getElementById('reschedule-client');
        const rescheduleServiceInput = document.getElementById('reschedule-service');
        const rescheduleTherapistInput = document.getElementById('reschedule-therapist');
        const rescheduleCurrentInput = document.getElementById('reschedule-current');
        const rescheduleDateInput = document.getElementById('reschedule-date');
        const rescheduleSlotsWrap = document.getElementById('reschedule-time-slots');
        const rescheduleSlotsHint = document.getElementById('reschedule-slots-hint');
        const rescheduleHiddenSlot = document.getElementById('reschedule-time-slot');
        const rescheduleErrorEl = document.getElementById('reschedule-error');
        const csrfToken = document.querySelector('input[name="_token"]')?.value ?? '';
        let activeRescheduleUrl = '';
        let activeRescheduleAvailabilityUrl = '';
        let activeRescheduleOriginalDate = '';
        let activeRescheduleOriginalSlot = '';
        let rescheduleAvailabilityTimer = null;
        let rescheduleSlotsLoading = false;

        function hideRescheduleError() {
            if (!rescheduleErrorEl) return;
            rescheduleErrorEl.textContent = '';
            rescheduleErrorEl.classList.add('hidden-section');
        }

        function showRescheduleError(message) {
            if (!rescheduleErrorEl) return;
            rescheduleErrorEl.textContent = message;
            rescheduleErrorEl.classList.remove('hidden-section');
        }

        function updateRescheduleSaveState() {
            if (!rescheduleSaveBtn || !rescheduleDateInput || !rescheduleHiddenSlot) return;
            const hasSlot = !!rescheduleHiddenSlot.value;
            const dateChanged = rescheduleDateInput.value !== activeRescheduleOriginalDate;
            const slotChanged = rescheduleHiddenSlot.value !== activeRescheduleOriginalSlot;
            rescheduleSaveBtn.disabled = !(hasSlot && (dateChanged || slotChanged));
        }

        function paintRescheduleSlots(payload) {
            if (!rescheduleSlotsWrap || !rescheduleHiddenSlot) return;

            const offered = Array.isArray(payload?.offered_slots) ? payload.offered_slots : [];
            const fullyBooked = new Set(Array.isArray(payload?.fully_booked_slots) ? payload.fully_booked_slots : []);
            const therapistBusy = new Set(Array.isArray(payload?.booked_slots) ? payload.booked_slots : []);
            const userConflicts = payload?.user_conflicts && typeof payload.user_conflicts === 'object' ? payload.user_conflicts : {};
            const storeClosed = payload?.store_closed === true;
            const selected = rescheduleHiddenSlot.value || '';
            const therapistName = rescheduleTherapistInput instanceof HTMLInputElement ? rescheduleTherapistInput.value.trim() : '';
            const therapistOffDuty = isSelectedTherapistOffDuty(payload, therapistName);

            rescheduleSlotsWrap.innerHTML = '';

            if (storeClosed) {
                if (rescheduleSlotsHint) rescheduleSlotsHint.textContent = 'The spa is closed on this date.';
                rescheduleHiddenSlot.value = '';
                updateRescheduleSaveState();
                return;
            }

            if (!offered.length) {
                if (rescheduleSlotsHint) rescheduleSlotsHint.textContent = 'No time slots are offered for this service on the selected date.';
                rescheduleHiddenSlot.value = '';
                updateRescheduleSaveState();
                return;
            }

            if (therapistOffDuty) {
                if (rescheduleSlotsHint) rescheduleSlotsHint.textContent = therapistOffDutyHint(payload, therapistName);
                offered.forEach((slot) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'appt-time-slot unavailable therapist-busy';
                    btn.textContent = slot;
                    btn.disabled = true;
                    btn.title = therapistOffDutyHint(payload, therapistName);
                    rescheduleSlotsWrap.appendChild(btn);
                });
                rescheduleHiddenSlot.value = '';
                updateRescheduleSaveState();
                return;
            }

            if (rescheduleSlotsHint) rescheduleSlotsHint.textContent = 'Select an available time for the assigned therapist.';

            offered.forEach((slot) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'appt-time-slot';
                btn.textContent = slot;

                const isFullyBooked = fullyBooked.has(slot);
                const isTherapistBusy = therapistBusy.has(slot);
                const hasUserConflict = Object.prototype.hasOwnProperty.call(userConflicts, slot);

                if (isFullyBooked) {
                    btn.classList.add('unavailable', 'fully-booked');
                    btn.disabled = true;
                } else if (isTherapistBusy) {
                    btn.classList.add('therapist-busy');
                    btn.disabled = true;
                } else if (hasUserConflict) {
                    btn.classList.add('user-conflict');
                    btn.disabled = true;
                }

                if (slot === selected && !btn.disabled) btn.classList.add('active');

                btn.addEventListener('click', () => {
                    if (btn.disabled) return;
                    rescheduleHiddenSlot.value = slot;
                    rescheduleSlotsWrap.querySelectorAll('.appt-time-slot.active').forEach((el) => el.classList.remove('active'));
                    btn.classList.add('active');
                    hideRescheduleError();
                    updateRescheduleSaveState();
                });

                rescheduleSlotsWrap.appendChild(btn);
            });

            updateRescheduleSaveState();
        }

        async function refreshRescheduleSlots() {
            if (!activeRescheduleAvailabilityUrl || !rescheduleDateInput) return;

            const bookingDate = rescheduleDateInput.value;
            if (!bookingDate) {
                if (rescheduleSlotsWrap) rescheduleSlotsWrap.innerHTML = '';
                if (rescheduleSlotsHint) rescheduleSlotsHint.textContent = 'Select a date to load available times.';
                if (rescheduleHiddenSlot) rescheduleHiddenSlot.value = '';
                updateRescheduleSaveState();
                return;
            }

            if (rescheduleSlotsLoading) return;
            rescheduleSlotsLoading = true;
            if (rescheduleSlotsHint) rescheduleSlotsHint.textContent = 'Loading availability...';

            try {
                const url = new URL(activeRescheduleAvailabilityUrl, window.location.origin);
                url.searchParams.set('booking_date', bookingDate);

                const res = await fetch(url.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!res.ok) throw new Error('availability failed');
                const payload = await res.json();
                paintRescheduleSlots(payload);
            } catch (e) {
                if (rescheduleSlotsHint) rescheduleSlotsHint.textContent = 'Could not load availability. Please try again.';
            } finally {
                rescheduleSlotsLoading = false;
            }
        }

        function scheduleRescheduleAvailabilityRefresh() {
            window.clearTimeout(rescheduleAvailabilityTimer);
            rescheduleAvailabilityTimer = window.setTimeout(refreshRescheduleSlots, 250);
        }

        function openRescheduleModal(button) {
            if (!rescheduleModal) return;

            activeRescheduleUrl = button.getAttribute('data-reschedule-url') ?? '';
            activeRescheduleAvailabilityUrl = button.getAttribute('data-reschedule-availability-url') ?? '';
            activeRescheduleOriginalDate = button.getAttribute('data-booking-date-iso') ?? '';
            activeRescheduleOriginalSlot = button.getAttribute('data-time-slot') ?? '';

            if (rescheduleClientInput) rescheduleClientInput.value = button.getAttribute('data-client') ?? '';
            if (rescheduleServiceInput) rescheduleServiceInput.value = button.getAttribute('data-service') ?? '';
            if (rescheduleTherapistInput) {
                const therapistLabel = button.getAttribute('data-therapist') ?? '';
                rescheduleTherapistInput.value = therapistLabel !== '' ? therapistLabel : 'Auto assign';
            }
            if (rescheduleCurrentInput) {
                rescheduleCurrentInput.value = `${button.getAttribute('data-date') ?? ''} · ${button.getAttribute('data-time') ?? ''}`;
            }
            if (rescheduleDateInput) rescheduleDateInput.value = activeRescheduleOriginalDate;
            if (rescheduleHiddenSlot) rescheduleHiddenSlot.value = '';
            if (rescheduleSlotsWrap) rescheduleSlotsWrap.innerHTML = '';
            hideRescheduleError();
            updateRescheduleSaveState();

            rescheduleModal.classList.remove('hidden-section');
            document.body.classList.add('modal-open');
            refreshRescheduleSlots();
        }

        function hideRescheduleModal() {
            rescheduleModal?.classList.add('hidden-section');
            document.body.classList.remove('modal-open');
            hideRescheduleError();
        }

        closeRescheduleModalBtn?.addEventListener('click', hideRescheduleModal);
        rescheduleCancelBtn?.addEventListener('click', hideRescheduleModal);
        rescheduleModal?.addEventListener('click', (event) => {
            const target = event.target;
            if (target instanceof HTMLElement && target.dataset.closeReschedule === 'true') hideRescheduleModal();
        });
        rescheduleDateInput?.addEventListener('change', () => {
            if (rescheduleHiddenSlot) rescheduleHiddenSlot.value = '';
            hideRescheduleError();
            scheduleRescheduleAvailabilityRefresh();
        });

        rescheduleSaveBtn?.addEventListener('click', async () => {
            if (!activeRescheduleUrl || !rescheduleDateInput || !rescheduleHiddenSlot?.value) return;
            hideRescheduleError();
            rescheduleSaveBtn.disabled = true;

            try {
                const res = await fetch(activeRescheduleUrl, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        booking_date: rescheduleDateInput.value,
                        time_slot: rescheduleHiddenSlot.value,
                    }),
                });

                const data = await res.json().catch(() => ({}));

                if (!res.ok) {
                    const firstError = data?.errors
                        ? Object.values(data.errors).flat()[0]
                        : (data?.message ?? 'Unable to reschedule this appointment.');
                    showRescheduleError(String(firstError ?? 'Unable to reschedule this appointment.'));
                    updateRescheduleSaveState();
                    return;
                }

                hideRescheduleModal();

                const newDateIso = data?.booking?.booking_date ?? rescheduleDateInput.value;
                if (newDateIso) {
                    currentSelectedDateIso = newDateIso;
                    setSelectedCalendarDayByIso(newDateIso);
                }

                await loadAppointments({
                    dateIso: newDateIso || currentSelectedDateIso,
                    statusSort: currentStatusSort,
                    statusFilter: currentStatusFilter,
                    page: 1,
                });

                const toast = document.getElementById('status-toast');
                if (toast) {
                    toast.querySelector('span').textContent = data.message ?? 'Appointment rescheduled.';
                    toast.classList.remove('hidden');
                } else {
                    window.location.reload();
                }
            } catch (e) {
                showRescheduleError('Unable to reschedule. Please check your connection and try again.');
                updateRescheduleSaveState();
            }
        });

        const cancelModal = document.getElementById('cancel-appointment-modal');
        const closeCancelModalBtn = document.getElementById('close-cancel-modal');
        const cancelDismissBtn = document.getElementById('cancel-dismiss-btn');
        const cancelConfirmBtn = document.getElementById('cancel-confirm-btn');
        const cancelClientInput = document.getElementById('cancel-client');
        const cancelServiceInput = document.getElementById('cancel-service');
        const cancelTherapistInput = document.getElementById('cancel-therapist');
        const cancelScheduleInput = document.getElementById('cancel-schedule');
        const cancelNoteInput = document.getElementById('cancel-note');
        const cancelErrorEl = document.getElementById('cancel-error');
        let activeCancelUrl = '';

        function hideCancelError() {
            if (!cancelErrorEl) return;
            cancelErrorEl.textContent = '';
            cancelErrorEl.classList.add('hidden-section');
        }

        function showCancelError(message) {
            if (!cancelErrorEl) return;
            cancelErrorEl.textContent = message;
            cancelErrorEl.classList.remove('hidden-section');
        }

        function openCancelModal(button) {
            if (!cancelModal) return;

            activeCancelUrl = button.getAttribute('data-cancel-url') ?? '';
            if (cancelClientInput) cancelClientInput.value = button.getAttribute('data-client') ?? '';
            if (cancelServiceInput) cancelServiceInput.value = button.getAttribute('data-service') ?? '';
            if (cancelTherapistInput) cancelTherapistInput.value = button.getAttribute('data-therapist') ?? '';
            if (cancelScheduleInput) {
                cancelScheduleInput.value = `${button.getAttribute('data-date') ?? ''} · ${button.getAttribute('data-time') ?? ''}`;
            }
            if (cancelNoteInput) cancelNoteInput.value = '';
            hideCancelError();
            cancelModal.classList.remove('hidden-section');
        }

        function hideCancelModal() {
            cancelModal?.classList.add('hidden-section');
            activeCancelUrl = '';
            hideCancelError();
        }

        closeCancelModalBtn?.addEventListener('click', hideCancelModal);
        cancelDismissBtn?.addEventListener('click', hideCancelModal);
        cancelModal?.addEventListener('click', (event) => {
            const target = event.target;
            if (target instanceof HTMLElement && target.dataset.closeCancel === 'true') hideCancelModal();
        });

        cancelConfirmBtn?.addEventListener('click', async () => {
            if (!activeCancelUrl) return;
            hideCancelError();
            cancelConfirmBtn.disabled = true;

            try {
                const res = await fetch(activeCancelUrl, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        cancellation_note: cancelNoteInput instanceof HTMLTextAreaElement ? cancelNoteInput.value.trim() : '',
                    }),
                });

                const data = await res.json().catch(() => ({}));

                if (!res.ok) {
                    const firstError = data?.errors
                        ? Object.values(data.errors).flat()[0]
                        : (data?.message ?? 'Unable to cancel this appointment.');
                    showCancelError(String(firstError ?? 'Unable to cancel this appointment.'));
                    cancelConfirmBtn.disabled = false;
                    return;
                }

                hideCancelModal();

                await loadAppointments({
                    dateIso: currentSelectedDateIso,
                    statusSort: currentStatusSort,
                    statusFilter: currentStatusFilter,
                    page: 1,
                });

                const toast = document.getElementById('status-toast');
                if (toast) {
                    toast.querySelector('span').textContent = data.message ?? 'Appointment cancelled.';
                    toast.classList.remove('hidden');
                } else {
                    window.location.reload();
                }
            } catch (e) {
                showCancelError('Unable to cancel. Please check your connection and try again.');
            } finally {
                cancelConfirmBtn.disabled = false;
            }
        });

        const viewModal = document.getElementById('view-appointment-modal');
        const closeViewModalBtn = document.getElementById('close-view-modal');
        const viewCloseBtn = document.getElementById('view-close-btn');
        const viewClient = document.getElementById('view-client');
        const viewService = document.getElementById('view-service');
        const viewTherapist = document.getElementById('view-therapist');
        const viewStatus = document.getElementById('view-status');
        const viewDate = document.getElementById('view-date');
        const viewTime = document.getElementById('view-time');
        const viewNotes = document.getElementById('view-notes');
        const viewPaymentMethod = document.getElementById('view-payment-method');
        const viewPaymentType = document.getElementById('view-payment-type');
        const viewPaymentAmount = document.getElementById('view-payment-amount');
        const viewPaymentStatus = document.getElementById('view-payment-status');
        const viewServiceAmount = document.getElementById('view-service-amount');
        const viewPaymentProofLink = document.getElementById('view-payment-proof-link');
        const viewPaymentProofEmpty = document.getElementById('view-payment-proof-empty');
        const viewPaymentTransaction = document.getElementById('view-payment-transaction');
        const viewPaymentTransactionWrap = document.getElementById('view-payment-transaction-wrap');
        const viewPaymentProofWrap = document.getElementById('view-payment-proof-wrap');
        const viewRefundWrap = document.getElementById('view-refund-wrap');
        const viewRefundStatus = document.getElementById('view-refund-status');
        const viewRefundNote = document.getElementById('view-refund-note');
        const viewCompleteRefundBtn = document.getElementById('view-complete-refund-btn');
        const viewRetryPaymongoLink = document.getElementById('view-retry-paymongo-link');
        let activeRefundUrl = '';
        let activeViewBookingId = '';

        function openViewModal(button) {
            const client = button.getAttribute('data-client') ?? '';
            const service = button.getAttribute('data-service') ?? '';
            const therapist = button.getAttribute('data-therapist') ?? '';
            const status = button.getAttribute('data-status') ?? '';
            const date = button.getAttribute('data-date') ?? '';
            const time = button.getAttribute('data-time') ?? '';
            const notes = button.getAttribute('data-notes') ?? '';
            const paymentMethod = button.getAttribute('data-payment-method') ?? '';
            const paymentRetryUrl = button.getAttribute('data-payment-retry-url') ?? '';
            const paymentType = button.getAttribute('data-payment-type') ?? '';
            const paymentAmount = button.getAttribute('data-payment-amount') ?? '';
            const paymentStatus = button.getAttribute('data-payment-status') ?? '';
            const serviceAmount = button.getAttribute('data-service-amount') ?? '';
            const paymentProof = button.getAttribute('data-payment-proof') ?? '';
            const paymentTransaction = button.getAttribute('data-payment-transaction') ?? '';
            const refundStatus = button.getAttribute('data-refund-status') ?? '';
            const refundStatusLabel = button.getAttribute('data-refund-status-label') ?? '';
            const refundAmount = button.getAttribute('data-refund-amount') ?? '';
            const refundReference = button.getAttribute('data-refund-reference') ?? '';
            const refundNote = button.getAttribute('data-refund-note') ?? '';
            const canCompleteRefund = button.getAttribute('data-can-complete-refund') === '1';
            activeRefundUrl = button.getAttribute('data-refund-url') ?? '';
            activeViewBookingId = button.getAttribute('data-booking-id') ?? '';

            if (viewRetryPaymongoLink) {
                viewRetryPaymongoLink.href = paymentRetryUrl || '#';
                viewRetryPaymongoLink.classList.toggle('hidden-section', !paymentRetryUrl);
            }

            if (viewClient) viewClient.value = client;
            if (viewService) viewService.value = service;
            if (viewTherapist) viewTherapist.value = therapist;
            if (viewStatus) viewStatus.value = status;
            if (viewDate) viewDate.value = date;
            if (viewTime) viewTime.value = time;
            if (viewNotes) viewNotes.value = notes || 'No notes provided.';
            if (viewPaymentMethod) viewPaymentMethod.value = paymentMethod || '—';
            if (viewPaymentType) viewPaymentType.value = paymentType || '—';
            if (viewPaymentAmount) viewPaymentAmount.value = paymentAmount || '—';
            if (viewPaymentStatus) viewPaymentStatus.value = paymentStatus || '—';
            if (viewServiceAmount) viewServiceAmount.value = serviceAmount || '—';
            if (viewPaymentTransaction) viewPaymentTransaction.value = paymentTransaction || '—';
            if (viewPaymentTransactionWrap) {
                viewPaymentTransactionWrap.classList.toggle('hidden-section', !paymentTransaction);
            }

            const hasProof = !!paymentProof;
            if (viewPaymentProofWrap) {
                viewPaymentProofWrap.classList.toggle('hidden-section', !hasProof);
            }
            if (viewPaymentProofLink) {
                viewPaymentProofLink.href = hasProof ? paymentProof : '#';
                viewPaymentProofLink.classList.toggle('is-hidden', !hasProof);
            }
            if (viewPaymentProofEmpty) {
                viewPaymentProofEmpty.classList.toggle('is-hidden', hasProof);
            }

            const hasRefund = refundStatus !== '' && refundStatus !== 'not_applicable';
            if (viewRefundWrap) {
                viewRefundWrap.classList.toggle('hidden-section', !hasRefund);
            }
            if (viewRefundStatus) {
                const refundParts = [refundStatusLabel || '—'];
                if (refundAmount) refundParts.push(refundAmount);
                if (refundReference) refundParts.push('Ref: ' + refundReference);
                viewRefundStatus.value = refundParts.join(' · ');
            }
            if (viewRefundNote) {
                viewRefundNote.textContent = refundNote || '';
                viewRefundNote.classList.toggle('hidden-section', !refundNote);
            }
            if (viewCompleteRefundBtn) {
                viewCompleteRefundBtn.classList.toggle('hidden-section', !canCompleteRefund || !activeRefundUrl);
            }

            viewModal?.classList.remove('hidden-section');
            document.body.classList.add('modal-open');
        }

        function hideViewModal() {
            viewModal?.classList.add('hidden-section');
            document.body.classList.remove('modal-open');
            activeRefundUrl = '';
            activeViewBookingId = '';
        }

        viewCompleteRefundBtn?.addEventListener('click', async () => {
            if (!activeRefundUrl) return;
            if (!window.confirm('Confirm that the refund has been returned to the client?')) return;

            viewCompleteRefundBtn.disabled = true;

            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                    || document.querySelector('input[name="_token"]')?.value
                    || '';
                const response = await fetch(activeRefundUrl, {
                    method: 'PATCH',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({}),
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    const errors = data?.errors;
                    const msg = errors && typeof errors === 'object'
                        ? Object.values(errors).flat().join(' ')
                        : (data?.message || 'Unable to complete refund.');
                    window.alert(msg);
                    return;
                }

                hideViewModal();
                const toast = document.getElementById('status-toast');
                if (toast) {
                    toast.classList.remove('hidden-section');
                    const span = toast.querySelector('span');
                    if (span) span.textContent = data.message || 'Refund marked complete.';
                }

                if (activeViewBookingId) {
                    loadAppointments({
                        dateIso: currentSelectedDateIso,
                        statusSort: currentStatusSort,
                        statusFilter: currentStatusFilter,
                        page: 1,
                        search: currentSearchQuery,
                    }).catch(() => {});
                }
            } catch (error) {
                window.alert('Unable to complete refund. Please check your connection and try again.');
            } finally {
                viewCompleteRefundBtn.disabled = false;
            }
        });

        closeViewModalBtn?.addEventListener('click', hideViewModal);
        viewCloseBtn?.addEventListener('click', hideViewModal);
        viewModal?.addEventListener('click', (event) => {
            const target = event.target;
            if (target instanceof HTMLElement && target.dataset.closeView === 'true') hideViewModal();
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;
            if (clientTypeModal && !clientTypeModal.classList.contains('hidden-section')) hideClientTypeModal();
            if (addAppointmentModal && !addAppointmentModal.classList.contains('hidden-section')) hideAddAppointmentModal();
            if (rescheduleModal && !rescheduleModal.classList.contains('hidden-section')) hideRescheduleModal();
            if (cancelModal && !cancelModal.classList.contains('hidden-section')) hideCancelModal();
            if (viewModal && !viewModal.classList.contains('hidden-section')) hideViewModal();
        });

        const appointmentsGrid = document.querySelector('.appointments-grid');
        const appointmentsClientsCard = document.querySelector('.appointments-card');
        const sideColMq = window.matchMedia('(min-width: 1101px)');
        const openNotificationsBtn = document.querySelector('[data-open-notifications="true"]');
        const notificationsDropdown = document.getElementById('appointments-notif-dropdown');
        const notificationsList = document.querySelector('.notifications');
        const markAllReadBtn = document.querySelector('[data-mark-all-read="true"]');
        const markAppointmentsDropdownReadBtn = document.querySelector('[data-mark-appointments-dropdown-read="true"]');
        const pageBadge = document.querySelector('[data-page-notif-badge="true"], [data-page-notif-badge]');

        const NOTIF_ALL_READ_KEY = 'tnrNotificationsAllReadAtV1';
        const NOTIF_READ_KEYS_KEY = 'tnrNotificationsReadKeysV1';

        function updatePageBadge() {
            if (!pageBadge) return;
            const unreadEls = Array.from(document.querySelectorAll('.note.note-unread[data-notif-at], .dashboard-note.note-unread[data-notif-at]'));
            const unreadAts = new Set(unreadEls.map((el) => (el instanceof HTMLElement ? (el.dataset.notifAt ?? '') : '')));
            const unreadCount = Array.from(unreadAts).filter(Boolean).length;
            pageBadge.textContent = String(unreadCount);

            if (markAllReadBtn) markAllReadBtn.disabled = unreadCount === 0;
            if (markAppointmentsDropdownReadBtn) markAppointmentsDropdownReadBtn.disabled = unreadCount === 0;
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

            // If user never marked anything read, keep server-rendered classes.
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

        function markSingleNotificationAsRead(noteEl) {
            if (!(noteEl instanceof HTMLElement)) return;
            markNotificationReadByElement(noteEl);
            noteEl.classList.remove('note-unread');
            noteEl.classList.add('note-read');
            updatePageBadge();
        }

        // Sync with other tabs/windows (Appointment <-> Dashboard, Admin <-> Receptionist).
        window.addEventListener('storage', (event) => {
            if (event.key !== NOTIF_ALL_READ_KEY) return;
            applyReadStateFromLocalStorage();
        });

        applyReadStateFromLocalStorage();

        const fromNotifParams = new URLSearchParams(window.location.search);
        const fromNotif = (fromNotifParams.get('from_notification') ?? '') === '1';
        const notifKeyFromUrl = (fromNotifParams.get('notif_key') ?? '').trim();
        if (fromNotif) {
            const targetRow = notifKeyFromUrl !== ''
                ? document.querySelector(`.appointments-list .appt-row[data-booking-id="${CSS.escape(notifKeyFromUrl)}"]`)
                : null;
            const rowToHighlight = targetRow ?? document.querySelector('.appointments-list .appt-row');
            rowToHighlight?.classList.add('from-notification');
            window.setTimeout(() => {
                rowToHighlight?.classList.remove('from-notification');
            }, 1800);
            const cleanUrl = new URL(window.location.href);
            cleanUrl.searchParams.delete('from_notification');
            cleanUrl.searchParams.delete('notif_key');
            history.replaceState(null, '', cleanUrl.toString());
        }

        notificationsList?.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) return;
            const note = target.closest('.note');
            if (!(note instanceof HTMLElement)) return;
            markSingleNotificationAsRead(note);
        });

        notificationsDropdown?.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) return;
            const note = target.closest('.dashboard-note');
            if (!(note instanceof HTMLElement)) return;
            markSingleNotificationAsRead(note);
        });

        function syncAppointmentsSideHeight() {
            if (!appointmentsGrid || !appointmentsClientsCard) return;
            if (!sideColMq.matches) {
                appointmentsGrid.style.removeProperty('--appointments-clients-height');
                return;
            }
            const clientsHeight = Math.round(appointmentsClientsCard.getBoundingClientRect().height);

            // Prevent the right column (calendar + notifications) from collapsing
            // when filtering shows fewer appointments on the left.
            const calendarCard = document.querySelector('.side-card-calendar');
            const calendarHeight = Math.round(calendarCard?.getBoundingClientRect().height ?? 0);
            const rightMinHeight = calendarHeight + 14 + 280; // gap + notifications minimum

            appointmentsGrid.style.setProperty(
                '--appointments-clients-height',
                `${Math.max(clientsHeight, rightMinHeight)}px`
            );
        }

        syncAppointmentsSideHeight();
        window.addEventListener('resize', syncAppointmentsSideHeight);
        sideColMq.addEventListener('change', syncAppointmentsSideHeight);
        if (window.ResizeObserver && appointmentsClientsCard) {
            new ResizeObserver(syncAppointmentsSideHeight).observe(appointmentsClientsCard);
        }

        if (openNotificationsBtn && notificationsDropdown) {
            openNotificationsBtn.addEventListener('click', (event) => {
                event.stopPropagation();
                const willShow = notificationsDropdown.classList.contains('hidden-section');
                notificationsDropdown.classList.toggle('hidden-section');
                openNotificationsBtn.setAttribute('aria-expanded', willShow ? 'true' : 'false');
            });

            document.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLElement)) return;
                if (target.closest('#appointments-notif-dropdown')) return;
                if (target.closest('[data-open-notifications="true"]')) return;
                notificationsDropdown.classList.add('hidden-section');
                openNotificationsBtn.setAttribute('aria-expanded', 'false');
            });
        }

        if (markAppointmentsDropdownReadBtn && notificationsDropdown) {
            markAppointmentsDropdownReadBtn.addEventListener('click', () => {
                try {
                    localStorage.setItem(NOTIF_ALL_READ_KEY, new Date().toISOString());
                } catch (e) {}
                applyReadStateFromLocalStorage();
                markAppointmentsDropdownReadBtn.disabled = true;
                markAppointmentsDropdownReadBtn.setAttribute('aria-label', 'All notifications read');
                markAppointmentsDropdownReadBtn.title = 'All notifications read';
            });
        }

        if (markAllReadBtn && notificationsList) {
            markAllReadBtn.addEventListener('click', () => {
                try {
                    localStorage.setItem(NOTIF_ALL_READ_KEY, new Date().toISOString());
                } catch (e) {}
                applyReadStateFromLocalStorage();
                markAllReadBtn.disabled = true;
                markAllReadBtn.setAttribute('aria-label', 'All notifications read');
                markAllReadBtn.title = 'All notifications read';
            });
        }

        // --- Calendar day click + status sort (AJAX, no full page refresh) ---
        const appointmentsListContainer = document.getElementById('appointments-list-container');
        const calendarEl = document.querySelector('.calendar[data-month][data-year][data-selected-date-iso]');
        const statusSortBtn = document.querySelector('[data-appt-sort-status="true"]');
        const statusFilterButtons = document.querySelectorAll('.pill-filter[data-status-filter]');
        const dayLabelEl = document.querySelector('[data-appt-day-label="true"]');
        const dayTotalEl = document.querySelector('[data-appt-day-total="true"]');
        const dayBreakdownEl = document.querySelector('[data-appt-day-breakdown="true"]');

        let currentSelectedDateIso = calendarEl?.dataset.selectedDateIso ?? '';
        let currentStatusSort = statusSortBtn?.dataset.statusSortCurrent ?? 'asc';
        let currentStatusFilter = '';

        const initialParams = new URLSearchParams(window.location.search);
        const initialStatusFilter = (initialParams.get('status_filter') ?? '').toLowerCase();
        if (['confirmed', 'pending', 'rescheduled', 'completed', 'cancelled', 'no-show'].includes(initialStatusFilter)) {
            currentStatusFilter = initialStatusFilter;
        }

        const searchInput = document.getElementById('appointments-search');
        let currentSearchQuery = (initialParams.get('search') ?? searchInput?.value ?? '').trim();
        let searchDebounceTimer = null;

        function applyActiveStatusFilterPill() {
            statusFilterButtons.forEach((btn) => {
                if (!(btn instanceof HTMLElement)) return;
                const value = btn.dataset.statusFilter ?? '';
                btn.classList.toggle('is-active', value === currentStatusFilter && value !== '');
            });
        }

        function setSelectedCalendarDayByIso(iso) {
            if (!calendarEl) return;
            calendarEl.querySelectorAll('span[role="gridcell"].selected').forEach((el) => el.classList.remove('selected'));
            if (!iso) return;
            const cell = calendarEl.querySelector(`span[role="gridcell"][data-date-iso="${CSS.escape(iso)}"]`);
            if (cell) cell.classList.add('selected');
        }

        function formatDateLabelFromIso(iso) {
            if (!iso) return 'Today';
            const parts = iso.split('-').map((part) => parseInt(part, 10));
            if (parts.length !== 3 || parts.some((part) => !Number.isFinite(part))) return 'Today';
            const [year, month, day] = parts;
            const parsed = new Date(year, month - 1, day);
            if (Number.isNaN(parsed.getTime())) return 'Today';
            return parsed.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
        }

        function updateDaySummary(iso, confirmed, completed, cancelled, pending, rescheduled) {
            if (dayLabelEl) {
                const todayIso = dayLabelEl.getAttribute('data-today-iso') ?? '';
                dayLabelEl.textContent = iso && iso !== todayIso ? formatDateLabelFromIso(iso) : 'Today';
            }

            const confirmedNum = parseInt(String(confirmed ?? '0'), 10) || 0;
            const pendingNum = parseInt(String(pending ?? '0'), 10) || 0;
            const rescheduledNum = parseInt(String(rescheduled ?? '0'), 10) || 0;
            const completedNum = parseInt(String(completed ?? '0'), 10) || 0;
            const cancelledNum = parseInt(String(cancelled ?? '0'), 10) || 0;
            const totalNum = confirmedNum + pendingNum + rescheduledNum + completedNum + cancelledNum;

            if (dayTotalEl) {
                dayTotalEl.textContent = String(totalNum);
            }

            if (dayBreakdownEl) {
                dayBreakdownEl.textContent = 'Number of Appointments';
            }
        }

        function syncAppointmentsUrl({ dateIso, statusSort, statusFilter, page, search }) {
            const url = new URL(window.location.href);
            url.searchParams.delete('ajax');

            if (dateIso) {
                url.searchParams.set('date', dateIso);
            } else {
                url.searchParams.delete('date');
            }

            url.searchParams.set('status_sort', statusSort);

            if (statusFilter) {
                url.searchParams.set('status_filter', statusFilter);
            } else {
                url.searchParams.delete('status_filter');
            }

            if (search) {
                url.searchParams.set('search', search);
            } else {
                url.searchParams.delete('search');
            }

            if (page > 1) {
                url.searchParams.set('page', String(page));
            } else {
                url.searchParams.delete('page');
            }

            window.history.replaceState({}, '', url.toString());
        }

        async function loadAppointments({ dateIso, statusSort, statusFilter = '', page = 1, search = currentSearchQuery }) {
            if (!appointmentsListContainer) return;

            const resolvedDateIso = dateIso || calendarEl?.dataset.selectedDateIso || '';
            const resolvedSearch = (search ?? '').trim();
            currentSearchQuery = resolvedSearch;

            const url = new URL(window.location.href);
            url.searchParams.set('ajax', '1');
            if (resolvedDateIso) {
                url.searchParams.set('date', resolvedDateIso);
            } else {
                url.searchParams.delete('date');
            }
            url.searchParams.set('status_sort', statusSort);
            if (statusFilter) {
                url.searchParams.set('status_filter', statusFilter);
            } else {
                url.searchParams.delete('status_filter');
            }
            if (resolvedSearch) {
                url.searchParams.set('search', resolvedSearch);
            } else {
                url.searchParams.delete('search');
            }
            url.searchParams.set('page', String(page));

            const res = await fetch(url.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const html = await res.text();
            appointmentsListContainer.innerHTML = html;

            syncAppointmentsUrl({
                dateIso: resolvedDateIso,
                statusSort,
                statusFilter,
                page,
                search: resolvedSearch,
            });

            const meta = appointmentsListContainer.querySelector('[data-appointments-meta="true"]');
            const confirmed = meta?.getAttribute('data-confirmed') ?? null;
            const pending = meta?.getAttribute('data-pending') ?? null;
            const rescheduled = meta?.getAttribute('data-rescheduled') ?? null;
            const cancelled = meta?.getAttribute('data-cancelled') ?? null;
            const noShow = meta?.getAttribute('data-no-show') ?? null;
            const completed = meta?.getAttribute('data-completed') ?? null;
            const isPastDay = meta?.getAttribute('data-is-past-day') === '1';

            const currentGroup = document.querySelector('[data-pill-group-current="true"]');
            const pastGroup = document.querySelector('[data-pill-group-past="true"]');
            if (currentGroup && pastGroup) {
                currentGroup.style.display = isPastDay ? 'none' : '';
                pastGroup.style.display = isPastDay ? '' : 'none';
            }

            if (confirmed !== null) {
                document.querySelectorAll('[data-pill-confirmed="true"]').forEach((el) => {
                    el.textContent = `${confirmed} Confirmed`;
                });
            }
            if (pending !== null) {
                document.querySelectorAll('[data-pill-pending="true"]').forEach((el) => {
                    el.textContent = `${pending} Pending`;
                });
            }
            if (rescheduled !== null) {
                document.querySelectorAll('[data-pill-rescheduled="true"]').forEach((el) => {
                    el.textContent = `${rescheduled} Rescheduled`;
                });
            }
            if (cancelled !== null) {
                document.querySelectorAll('[data-pill-cancelled="true"]').forEach((el) => {
                    el.textContent = `${cancelled} Cancelled`;
                });
            }
            if (noShow !== null) {
                document.querySelectorAll('[data-pill-no-show="true"]').forEach((el) => {
                    el.textContent = `${noShow} No Show`;
                });
            }
            if (completed !== null) {
                document.querySelectorAll('[data-pill-completed="true"]').forEach((el) => {
                    el.textContent = `${completed} Completed`;
                });
            }

            updateDaySummary(resolvedDateIso, confirmed, completed, cancelled, pending, rescheduled);
        }

        if (appointmentsListContainer) {
            appointmentsListContainer.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLElement)) return;

                const viewBtn = target.closest('[data-open-view="true"]');
                if (viewBtn instanceof HTMLElement) {
                    event.preventDefault();
                    openViewModal(viewBtn);
                    return;
                }

                const rescheduleBtn = target.closest('[data-open-reschedule="true"]');
                if (rescheduleBtn instanceof HTMLElement) {
                    event.preventDefault();
                    openRescheduleModal(rescheduleBtn);
                    return;
                }

                const cancelBtn = target.closest('[data-open-cancel="true"]');
                if (cancelBtn instanceof HTMLElement) {
                    event.preventDefault();
                    openCancelModal(cancelBtn);
                    return;
                }

                const pageLink = target.closest('.appointments-pagination a');
                if (!(pageLink instanceof HTMLAnchorElement)) return;

                event.preventDefault();

                const href = pageLink.getAttribute('href');
                if (!href) return;

                const url = new URL(href, window.location.origin);
                const page = parseInt(url.searchParams.get('page') ?? '1', 10);
                if (!Number.isFinite(page)) return;

                loadAppointments({
                    dateIso: currentSelectedDateIso,
                    statusSort: currentStatusSort,
                    statusFilter: currentStatusFilter,
                    page,
                }).catch(() => {});
            });
        }

        if (calendarEl) {
            if (currentSelectedDateIso) setSelectedCalendarDayByIso(currentSelectedDateIso);

            const monthLabelEl = calendarEl.closest('.appointments-side')?.querySelector('[data-calendar-month-label="true"]');

            calendarEl.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLElement)) return;
                const cell = target.closest('span[role="gridcell"]');
                if (!cell) return;

                const iso = cell.getAttribute('data-date-iso') ?? '';
                if (!iso) return;

                // Clicking the same date again clears the selection
                if (currentSelectedDateIso === iso) {
                    currentSelectedDateIso = '';
                    calendarEl.querySelectorAll('span[role="gridcell"].selected').forEach((el) => el.classList.remove('selected'));
                    loadAppointments({ dateIso: '', statusSort: currentStatusSort, statusFilter: currentStatusFilter }).catch(() => {});
                    return;
                }

                currentSelectedDateIso = iso;
                setSelectedCalendarDayByIso(iso);
                loadAppointments({ dateIso: iso, statusSort: currentStatusSort, statusFilter: currentStatusFilter }).catch(() => {});
            });

            const prevBtn = calendarEl.closest('.appointments-side')?.querySelector('[data-prev-month="true"]');
            const nextBtn = calendarEl.closest('.appointments-side')?.querySelector('[data-next-month="true"]');

            function pad2(n) {
                return String(n).padStart(2, '0');
            }

            function isoForDate(d) {
                return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
            }

            function clampDay(year, monthIndex0, day) {
                const lastDay = new Date(year, monthIndex0 + 1, 0).getDate();
                return Math.min(day, lastDay);
            }

            function monthName(month1) {
                const names = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                return names[Math.max(1, Math.min(12, month1)) - 1] ?? '';
            }

            function renderCalendarGrid(year, month1, selectedIso) {
                const firstOfMonth = new Date(year, month1 - 1, 1);
                const gridStart = new Date(firstOfMonth);
                gridStart.setDate(firstOfMonth.getDate() - firstOfMonth.getDay()); // Sunday-start

                const rows = [];
                for (let w = 0; w < 6; w += 1) {
                    const cols = [];
                    for (let d = 0; d < 7; d += 1) {
                        const cell = new Date(gridStart);
                        cell.setDate(gridStart.getDate() + w * 7 + d);

                        const iso = isoForDate(cell);
                        const classes = [];
                        if ((cell.getMonth() + 1) !== month1) classes.push('muted');
                        if (selectedIso && iso === selectedIso) classes.push('selected');

                        cols.push(`<span class="${classes.join(' ')}" role="gridcell" data-date-iso="${iso}">${cell.getDate()}</span>`);
                    }
                    rows.push(`<div class="cal-row" role="row">${cols.join('')}</div>`);
                }

                const head = calendarEl.querySelector('.cal-row.cal-head');
                if (!head) return;
                calendarEl.innerHTML = head.outerHTML + rows.join('');

                if (monthLabelEl) monthLabelEl.textContent = `${monthName(month1)} ${year}`;
                calendarEl.dataset.month = String(month1);
                calendarEl.dataset.year = String(year);
                calendarEl.dataset.selectedDateIso = selectedIso ?? '';
            }

            function changeMonth(delta) {
                if (!calendarEl) return;

                const baseMonth = parseInt(calendarEl.dataset.month ?? '', 10);
                const baseYear = parseInt(calendarEl.dataset.year ?? '', 10);
                if (!Number.isFinite(baseMonth) || !Number.isFinite(baseYear)) return;

                const dayFromSelection = parseInt((currentSelectedDateIso ?? '').split('-')[2] ?? '', 10);
                const dayToUse = Number.isFinite(dayFromSelection) ? dayFromSelection : 1;

                const base = new Date(baseYear, baseMonth - 1, 1);
                base.setMonth(base.getMonth() + delta);

                const nextDay = clampDay(base.getFullYear(), base.getMonth(), dayToUse);
                base.setDate(nextDay);

                const newIso = isoForDate(base);
                currentSelectedDateIso = newIso;

                calendarEl.classList.add('is-transitioning');
                calendarEl.classList.toggle('is-next', delta > 0);
                calendarEl.classList.toggle('is-prev', delta < 0);

                renderCalendarGrid(base.getFullYear(), base.getMonth() + 1, newIso);

                window.setTimeout(() => {
                    calendarEl.classList.remove('is-transitioning', 'is-next', 'is-prev');
                }, 180);

                loadAppointments({ dateIso: newIso, statusSort: currentStatusSort, statusFilter: currentStatusFilter }).catch(() => {});
            }

            prevBtn?.addEventListener('click', (e) => {
                e.preventDefault();
                changeMonth(-1);
            });

            nextBtn?.addEventListener('click', (e) => {
                e.preventDefault();
                changeMonth(1);
            });
        }

        if (statusSortBtn) {
            statusSortBtn.addEventListener('click', (event) => {
                event.preventDefault();

                const next = statusSortBtn.dataset.statusSortNext ?? 'desc';
                currentStatusSort = next;
                // swap for next toggle
                statusSortBtn.dataset.statusSortCurrent = currentStatusSort;
                statusSortBtn.dataset.statusSortNext = currentStatusSort === 'asc' ? 'desc' : 'asc';

                if (currentSelectedDateIso) {
                    loadAppointments({ dateIso: currentSelectedDateIso, statusSort: currentStatusSort, statusFilter: currentStatusFilter }).catch(() => {});
                }
            });
        }

        statusFilterButtons.forEach((btn) => {
            btn.addEventListener('click', (event) => {
                event.preventDefault();
                if (!(btn instanceof HTMLElement)) return;
                const selected = btn.dataset.statusFilter ?? '';
                currentStatusFilter = currentStatusFilter === selected ? '' : selected;
                applyActiveStatusFilterPill();
                if (currentSelectedDateIso) {
                    loadAppointments({ dateIso: currentSelectedDateIso, statusSort: currentStatusSort, statusFilter: currentStatusFilter }).catch(() => {});
                }
            });
        });

        applyActiveStatusFilterPill();

        if (searchInput instanceof HTMLInputElement) {
            searchInput.addEventListener('input', () => {
                window.clearTimeout(searchDebounceTimer);
                searchDebounceTimer = window.setTimeout(() => {
                    currentSearchQuery = searchInput.value.trim();
                    loadAppointments({
                        dateIso: currentSelectedDateIso,
                        statusSort: currentStatusSort,
                        statusFilter: currentStatusFilter,
                        page: 1,
                        search: currentSearchQuery,
                    }).catch(() => {});
                }, 300);
            });

            searchInput.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter') return;
                event.preventDefault();
                window.clearTimeout(searchDebounceTimer);
                currentSearchQuery = searchInput.value.trim();
                loadAppointments({
                    dateIso: currentSelectedDateIso,
                    statusSort: currentStatusSort,
                    statusFilter: currentStatusFilter,
                    page: 1,
                    search: currentSearchQuery,
                }).catch(() => {});
            });
        }

        const initialMeta = appointmentsListContainer?.querySelector('[data-appointments-meta="true"]');
        updateDaySummary(
            currentSelectedDateIso,
            initialMeta?.getAttribute('data-confirmed') ?? '0',
            initialMeta?.getAttribute('data-completed') ?? '0',
            initialMeta?.getAttribute('data-cancelled') ?? '0',
            initialMeta?.getAttribute('data-pending') ?? '0',
            initialMeta?.getAttribute('data-rescheduled') ?? '0'
        );
    </script>
    <script src="{{ asset('js/staff-feed-poll.js') }}"></script>
    @include('partials.payment-receipt-modal')
    @include('partials.payment-receipt-modal-script')
    <script src="{{ asset('js/system-clock.js') }}"></script>
    <script>
        window.initSystemClock({
            serverNowIso: @json($serverNowIso ?? now()->toIso8601String()),
        });
    </script>
</body>
</html>
