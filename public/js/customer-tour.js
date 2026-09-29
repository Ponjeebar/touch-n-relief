(function () {
    'use strict';

    var config = document.getElementById('tnr-customer-tour-config');
    if (!config) return;

    var role = config.dataset.customerTourRole || 'customer';
    var page = config.dataset.customerTourPage || 'page';
    var userId = config.dataset.customerTourUser || role;
    var shouldAutoStart = config.dataset.customerTourAutoStart === '1';
    var tourVersion = 'v3';
    var storageKey = 'tnr-system-tour:' + userId + ':' + role + ':' + page + ':' + tourVersion;
    var activeTour = null;

    function isVisible(element) {
        if (!element) return false;
        var style = window.getComputedStyle(element);
        var rect = element.getBoundingClientRect();
        return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
    }

    function target() {
        var selectors = Array.prototype.slice.call(arguments);
        for (var i = 0; i < selectors.length; i += 1) {
            var matches = document.querySelectorAll(selectors[i]);
            for (var j = 0; j < matches.length; j += 1) {
                if (isVisible(matches[j])) return matches[j];
            }
        }
        return undefined;
    }

    function step(selectors, title, description, side, align) {
        return {
            element: function () { return target.apply(null, selectors); },
            popover: { title: title, description: description, side: side || 'bottom', align: align || 'center' },
        };
    }

    function introduction(title, description) {
        return { popover: { title: title, description: description } };
    }

    function withExample(description, exampleText) {
        return description + '<span class="tnr-tour-example"><strong>Example:</strong> ' + exampleText + '</span>';
    }

    function customerLandingSteps() {
        return [
            introduction('Welcome to TouchNRelief', 'This guide follows the complete customer journey: compare treatments, choose a therapist, book and pay, then manage every appointment status.'),
            step(['.landing-nav', '.nav-shell'], 'Main navigation', 'Move between services, therapists, spa information, and contact details.'),
            step(['[data-landing-section="services"]', '#services'], 'Individual services', withExample('Compare the purpose, duration, and price of each treatment before booking.', 'A 60-minute massage and a 90-minute massage reserve different amounts of therapist time.')),
            step(['#packages', '.package-block'], 'THERA packages', withExample('Packages combine several treatments and remain separate from individual services.', 'THERA #2 includes its listed treatments under one package price and duration.')),
            step(['#membership', '.membership-block'], 'Membership information', 'Review membership plans and benefits. Membership purchases are not processed in the system yet.'),
            step(['[data-landing-section="therapists"]', '#therapists'], 'Choose with confidence', withExample('Review specialties before opening the booking page. The booking screen also highlights matching specialists.', 'For Hot Stone, a therapist whose specialties include Hot Stone may be marked as the best recommendation.')),
            step(['[data-customer-tour="book"]', '.hero-book-btn'], 'Start a booking', withExample('Choose a date, service, therapist, time, and payment option in that order.', 'After selecting Sep 30, Hot Stone, and an available therapist, only valid time slots can be chosen.'), 'top'),
            step(['[data-customer-tour="appointments"]', '[data-tnr-open-transactions]'], 'My Appointments', withExample('Upcoming and historical records are separated so expired payment holds do not crowd active bookings.', 'A confirmed booking appears under Upcoming; a completed, cancelled, or expired booking appears under History.'), 'top'),
            step(['[data-customer-tour="profile"]', '.nav-user'], 'Your account', 'Update your profile and wellness preferences or replay this tour.', 'top'),
            step(['.tnr-chat-launcher'], 'Help when you need it', 'Ask about services, hours, bookings, or your account.', 'left'),
        ];
    }

    function bookingSteps() {
        return [
            introduction('Booking walkthrough', 'Complete each choice in order. The system prevents incompatible services, therapists, and time slots from being submitted.'),
            step(['.booking-return-link'], 'Return safely', 'Go back without creating a booking.', 'bottom', 'start'),
            step(['#booking_date'], '1. Choose a date', withExample('Select an available date to enable service choices.', 'Choosing Sep 30 loads services and therapist schedules for Sep 30 only.')),
            step(['#booking-panel-service'], '2. Choose a service or package', withExample('Review the duration, inclusions, and price because they determine the schedule and payment amount.', 'A 120-minute THERA package needs a longer open schedule than a 45-minute service.')),
            step(['#booking-panel-therapist'], '3. Choose a therapist', withExample('Recommended specialists are highlighted, but any bookable therapist may be selected.', 'A greyed-out therapist is unavailable for the current combination and cannot be selected.')),
            step(['#time-slots', '.booking-field-time'], '4. Choose a time', withExample('Use the legend to distinguish available, busy, fully booked, and conflicting slots.', 'If you already have a 3:00 PM booking, that slot is marked as your appointment.')),
            step(['#notes'], 'Add useful notes', 'Share preferences or requests the spa team should know.'),
            step(['.booking-submit'], 'Review payment', withExample('Continue to the payment summary after every required choice is complete.', 'A downpayment confirms part of the price; the remaining balance must be settled before the session starts.'), 'top'),
        ];
    }

    function profileSteps() {
        return [
            introduction('Account and appointment guide', 'Manage your personal information, preferences, password, and booking history here.'),
            step(['.profile-id', '.profile-head'], 'Profile identity', 'Your name and photo help staff identify your account.'),
            step(['.profile-section', '.profile-card'], 'Personal information', 'Keep your contact information accurate for booking updates.'),
            step(['[data-tnr-open-transactions]', '.profile-section-compact'], 'Appointment history', withExample('Open categorized upcoming and historical records.', 'Confirmed and payment-pending bookings stay under Upcoming; completed, cancelled, and expired records stay under History.')),
            step(['.wellness-preferences', '[data-wellness-preferences]'], 'Wellness preferences', 'Update the preferences used for therapist recommendations.'),
            step(['.password-section', '#current_password'], 'Account security', 'Change your password when needed.'),
        ];
    }

    function customerAppointmentSteps() {
        return [
            introduction('My Appointments guide', 'Learn how booking status, payment information, and available actions change throughout an appointment.'),
            step(['#tnr-transactions-modal .txn-modal-header'], 'Appointment center', 'This panel contains only the signed-in customer’s booking and payment records.'),
            step(['.txn-group-tabs'], 'Upcoming and History', withExample('Switch between active commitments and past records.', 'A confirmed appointment is Upcoming. A completed session, cancellation, no-show, or expired payment hold is History.')),
            step(['.txn-toolbar'], 'Search, filter, and sort', withExample('Narrow a long history by date, service, therapist, payment amount, or order.', 'Search “Hot Stone,” filter records with an amount, then sort by most recent.')),
            step(['.txn-card'], 'Read an appointment card', withExample('Each card shows the reference, service, therapist, schedule, duration, amount, and current status.', '“Payment hold expired” means the 15-minute payment window ended and that reservation is no longer active.')),
            step(['[data-txn-payment-toggle]', '.txn-payment-block'], 'Payment details', withExample('Expand this section to review method, type, initial payment, total paid, balance, and reference.', 'A 50% downpayment can show a remaining balance that must be collected before the session starts.')),
            step(['.txn-card-actions'], 'Available actions', withExample('Buttons appear only when the booking rules permit an action.', 'An eligible upcoming booking may be rescheduled or cancelled; an active payment hold may show Continue payment.')),
            step(['.txn-modal-note'], 'Arrival policy', 'Arrive before the appointment time. The system can cancel a session when the customer is more than 10 minutes late.'),
        ];
    }

    function staffFoundationSteps() {
        return [
            introduction('Receptionist workspace', 'This page-level training covers the complete daily workflow. Each major staff page starts its own guide the first time you open it.'),
            step(['.brand'], 'TouchNRelief staff area', 'This is the receptionist portal for day-to-day spa operations.'),
            step(['#staff-mobile-navigation', '.nav-list'], 'Daily work pages', withExample('Move between bookings, live sessions, completed transactions, therapists, and customer records.', 'A normal flow is Appointments → collect balance → start session → Ongoing Sessions → Complete Session → Completed Sessions.')),
            step(['.staff-mobile-shortcuts'], 'Mobile shortcuts', 'On smaller screens, use this bar for the most common staff actions.', 'top'),
        ];
    }

    function receptionistDashboardSteps() {
        return staffFoundationSteps().concat([
            step(['.topbar .welcome'], 'Today at a glance', 'The dashboard heading and live clock anchor the current operating day.'),
            step(['.receptionist-metric-grid'], 'Operational summary', 'Monitor ongoing sessions, sales, active therapists, and upcoming appointments.'),
            step(['.chart-grid'], 'Activity trends', 'Use the charts to understand session volume and service activity.'),
            step(['a[href*="/appointments"]'], 'Appointments', withExample('Create walk-in or registered-customer bookings, verify payments, collect balances, reschedule, cancel, and start eligible sessions.', 'For a confirmed 3:00 PM booking with a balance, collect the exact remaining amount before Start Session becomes appropriate.')),
            step(['a[href*="/ongoing-sessions"]'], 'Ongoing Sessions', withExample('Monitor services already in progress and their elapsed and remaining time.', 'A 60-minute massage started at 2:00 PM shows live progress until it is completed.')),
            step(['a[href*="/completed-sessions"]'], 'Completed Sessions', withExample('Review completed service transactions, payment totals, and session notes.', 'After completing the 2:00 PM massage, its record moves here with the therapist and final amount.')),
            step(['a[href*="/therapist-tracking"]'], 'Therapist Monitoring', withExample('Check current availability, specialties, and accumulated service hours.', 'A therapist in an active session appears busy and becomes available when the session is completed.')),
            step(['a[href*="/client-records"]'], 'Client Records', withExample('Search customer profiles and inspect their appointment and service history.', 'Open a returning client before assisting them to confirm contact details and past services.')),
            step(['.mobile-dashboard-actions'], 'Quick actions', 'On compact screens, these links open the three most common operational pages.'),
            step(['[data-open-dashboard-notifications]', '[data-topbar-notif-sync]'], 'Notifications', 'Review new bookings, payment updates, cancellations, and reschedules.', 'bottom', 'end'),
            step(['#tnr-settings-trigger'], 'Display settings', 'Switch between light and dark mode.'),
            step(['#tnr-profile-menu-trigger'], 'Profile and tour', 'Update your profile, replay the page tour, or sign out.', 'bottom', 'end'),
        ]);
    }

    function staffPageSteps() {
        var pageIntroductions = {
            'appointments.index': ['Appointments guide', 'Learn the booking, payment, rescheduling, cancellation, and session-start workflow on this page.'],
            'ongoing-sessions.index': ['Ongoing Sessions guide', 'Learn how to monitor active treatments and move finished work into completed history.'],
            'completed-sessions.index': ['Completed Sessions guide', 'Learn how to review finished treatments, totals, date ranges, and session notes.'],
            'therapist-tracking.index': ['Therapist Monitoring guide', 'Learn how availability, specialties, and completed service hours support therapist assignment.'],
            'client-records.index': ['Client Records guide', 'Learn how to locate the correct customer and open their complete record.'],
            'client-records.show': ['Customer record guide', 'Learn how to verify customer details and review their appointment and transaction history.'],
        };
        var pageSteps = {
            'appointments.index': [
                step(['.topbar .welcome', '.appointments-head'], 'Appointments workspace', 'Create and manage customer and walk-in bookings.'),
                step(['.metric-grid-appointments'], 'Daily totals', 'See the active-client count and appointments for the selected day.'),
                step(['.clients-add-appointment-btn', '[data-mobile-add-appointment]'], 'Create an appointment', withExample('Choose a registered client or walk-in, then add the schedule, service, therapist, and payment.', 'For a walk-in customer, collect the required information at the counter before confirming the booking.')),
                step(['#appointments-search'], 'Search appointments', withExample('Search by client, service, therapist, or appointment detail.', 'Type a client surname to find their booking without scanning the full day.')),
                step(['.appointments-sub'], 'Filter by status', withExample('Use status totals to isolate bookings requiring a specific action.', 'Choose Pending to review bookings awaiting confirmation, or Confirmed to find sessions ready for check-in.')),
                step(['#appointments-list-container', '.appointments-card'], 'Appointment list', 'Each row contains the client, service, therapist, schedule, payment state, and permitted actions.'),
                step(['.appt-icon-btn.view[data-open-view]'], 'Review full details', withExample('Open details before changing a booking or payment.', 'Confirm the booking reference, amount paid, remaining balance, and payment reference match the customer’s record.')),
                step(['.appt-icon-btn.collect-balance[data-open-balance]'], 'Collect a remaining balance', withExample('Record only the exact amount actually received at the counter.', 'If ₱200 is already paid on a ₱400 service, collect and record the ₱200 balance before starting.')),
                step(['.appt-icon-btn.start'], 'Start an eligible session', withExample('Start is allowed only within the configured time window and after required payment.', 'A fully paid 3:00 PM appointment can be started near 3:00 PM, then appears in Ongoing Sessions.')),
                step(['.appt-icon-btn.reschedule[data-open-reschedule]'], 'Reschedule carefully', 'Choose a new available date and time; availability is checked again before saving.'),
                step(['.appt-icon-btn.cancel[data-open-cancel]'], 'Cancel with a reason', 'Record a clear reason because cancellation and refund information remains in the customer history.'),
                step(['#appointments-export-link'], 'Export appointment records', 'Choose the date coverage and status scope before downloading the appointment CSV.'),
            ],
            'ongoing-sessions.index': [
                step(['.topbar .welcome'], 'Ongoing sessions', 'Monitor treatments that have already started.'),
                step(['#ongoing-cards'], 'Live session board', withExample('Every active service appears as a live card. An empty message means no session is currently running.', 'After staff starts booking BKG-00125, its customer and therapist appear here.')),
                step(['.ongoing-card .ongoing-head'], 'Client and therapist', 'Confirm who is receiving the treatment and which therapist is responsible.'),
                step(['.ongoing-meta-grid'], 'Service timing', withExample('Duration, elapsed time, and remaining time update automatically.', 'A 60-minute service with 15 minutes elapsed shows approximately 45 minutes remaining.')),
                step(['.progress-track'], 'Live progress', 'The progress bar gives a quick visual indication of how far the session has advanced.'),
                step(['.ongoing-footer'], 'Session price', 'The displayed price is the booked service or package price; payment collection remains in Appointments.'),
                step(['.complete-btn'], 'Complete Session', withExample('Use this after the treatment is actually finished. The record moves to Completed Sessions.', 'When the therapist confirms the massage is finished, select Complete Session and verify it in completed history.')),
                step(['[data-ongoing-empty]'], 'When there are no sessions', 'This is a normal operational state. Start an eligible paid appointment from the Appointments page to create an ongoing session.'),
            ],
            'completed-sessions.index': [
                step(['.topbar .welcome'], 'Completed Sessions', 'This page is the permanent operational history of finished treatments.'),
                step(['.summary-grid'], 'Completed summary', withExample('Review completed session count, service hours, and collected totals for the current scope.', 'If three 60-minute treatments finish today, the summary represents three sessions and three service hours.')),
                step(['.history-scope-btn'], 'Daily or past history', 'Switch from today’s completed sessions to the broader past transaction history.'),
                step(['.history-range-form'], 'Choose a date range', withExample('Limit past history to the dates needed for review.', 'Select Sep 1 through Sep 30 to review September sessions only.')),
                step(['#completed-txn-search'], 'Search completed records', 'Search by transaction, customer, therapist, service, schedule, or payment information.'),
                step(['.history-table'], 'Completed transaction table', withExample('Verify the reference, client, service, therapist, date, time, duration, and final amount.', 'A completed Hot Stone session should show the assigned therapist and the amount recorded for that booking.')),
                step(['.notes-btn'], 'Session notes', 'Open notes when staff needs to review treatment details recorded with the completed session.'),
                step(['.history-empty'], 'Empty history', 'If no rows appear, no completed sessions match the selected date range and search.'),
            ],
            'therapist-tracking.index': [
                step(['.tt-period-bar'], 'Service-hour period', 'Choose the year used for therapist service-hour totals.'),
                step(['.tt-metrics'], 'Availability summary', withExample('See the team size, available and busy therapists, and average completed hours.', 'A therapist serving an active client is counted as busy until that session is completed.')),
                step(['.tt-table'], 'Therapist directory', 'The desktop table compares name, specialization, current status, and total service hours.'),
                step(['.tt-cards'], 'Mobile therapist cards', 'On smaller screens, the same therapist information is arranged as readable cards.'),
                step(['[data-open-tt-view]'], 'Open therapist details', withExample('Review contact information, specialties, status, and recorded hours.', 'Before assigning a walk-in, confirm the therapist is available and trained for the requested treatment.')),
                step(['.tt-specs'], 'Specializations', 'Use specialties to match treatments with suitable therapists; availability is still checked separately.'),
                step(['.tt-status', '[data-tt-status]'], 'Availability status', 'Available means ready for assignment; Busy means the therapist has an active session.'),
                step(['.tt-hours', '[data-service-hours]'], 'Service hours', 'These totals come from completed sessions in the selected reporting year.'),
            ],
            'client-records.index': [
                step(['.topbar .welcome'], 'Client Records', 'Use this page to find a registered customer before reviewing their detailed history.'),
                step(['#client-records-search', '#client-records-mobile-search'], 'Search by customer name', withExample('The desktop and mobile search boxes filter the same customer list.', 'Type “Rehanie” to isolate records whose customer name contains Rehanie.')),
                step(['.cr-filter-badges'], 'Filter customer status', withExample('Separate active, inactive, new, and archived customer records.', 'Use Archived when staff needs to find a retained historical record that is hidden from the active list.')),
                step(['#client-records-list'], 'Customer list', 'Each row identifies a customer and links to the full record.'),
                step(['.cr-customer-row-link'], 'Open a customer', withExample('Select the correct person before reviewing or updating information.', 'Confirm the displayed contact detail if two customers have similar names.')),
            ],
            'client-records.show': [
                step(['.cr-back'], 'Return to customer search', 'Go back to the client list without changing this record.'),
                step(['.cr-profile', '.cr-detail-head', '.cr-identity'], 'Customer identity', withExample('Confirm the customer name and contact information before assisting them.', 'Use the email or phone number to distinguish customers with similar names.')),
                step(['.cr-stats'], 'Customer summary', 'Review the customer’s birthday and age alongside their identity.'),
                step(['.cr-user-profile'], 'Profile and wellness information', 'Confirm contact details, sex, therapist preference, pregnancy status, and pressure preference. Treat preferences as booking context rather than medical instructions.'),
                step(['.cr-history-tools'], 'Search and filter history', 'Narrow the customer’s records by service, therapist, date, status, or amount.'),
                step(['.cr-txn-list'], 'Appointment and transaction history', withExample('Review services, therapists, schedules, status, notes, and amounts connected to this customer.', 'A cancelled paid booking remains in history so its payment and refund trail is not lost.')),
            ],
        };

        var pageIntroduction = pageIntroductions[page] || ['Page guide', 'Learn the controls and records available in this staff workspace.'];

        return [introduction(pageIntroduction[0], pageIntroduction[1])].concat(pageSteps[page] || [
            step(['main .topbar', 'main header', 'main h1'], 'Current workspace', 'The heading identifies the staff task on this page.'),
            step(['main section', 'main .data-card', 'main table'], 'Controls and records', 'Use the visible controls to review and update permitted records.'),
        ]);
    }

    function buildSteps(scope) {
        if (role === 'customer' && scope === 'appointments') return customerAppointmentSteps();
        if (role === 'receptionist') return page === 'receptionist.dashboard' ? receptionistDashboardSteps() : staffPageSteps();
        if (page === 'booking.create') return bookingSteps();
        if (page === 'profile.edit') return profileSteps();
        return customerLandingSteps();
    }

    function markSeen() {
        try { localStorage.setItem(storageKey, 'complete'); } catch (error) { /* Replay remains available. */ }
    }

    function hasSeen() {
        try { return localStorage.getItem(storageKey) === 'complete'; } catch (error) { return false; }
    }

    function closeMenus() {
        document.querySelectorAll('[data-user-menu].open').forEach(function (menu) { menu.classList.remove('open'); });
        document.querySelectorAll('.profile-dropdown:not(.profile-dropdown-hidden)').forEach(function (menu) {
            menu.classList.add('profile-dropdown-hidden');
        });
        document.querySelectorAll('.profile-menu-trigger[aria-expanded="true"]').forEach(function (control) {
            control.setAttribute('aria-expanded', 'false');
        });
    }

    function startTour(force, scope) {
        if (!force && hasSeen()) return;
        if (!window.driver?.js?.driver) return;
        closeMenus();
        activeTour?.destroy();
        activeTour = window.driver.js.driver({
            steps: buildSteps(scope), popoverClass: 'tnr-customer-tour', showProgress: true,
            progressText: '{{current}} of {{total}}', nextBtnText: 'Next', prevBtnText: 'Back', doneBtnText: 'Finish',
            smoothScroll: true, allowClose: true, allowKeyboardControl: true, overlayOpacity: 0.72,
            stagePadding: 8, stageRadius: 10, skipMissingElement: true,
            animate: !window.matchMedia('(prefers-reduced-motion: reduce)').matches,
            onDestroyed: function () { markSeen(); activeTour = null; },
        });
        activeTour.drive();
    }

    var replayButtons = document.querySelectorAll('[data-start-customer-tour]');
    if (!window.driver?.js?.driver) {
        replayButtons.forEach(function (button) { button.remove(); });
        return;
    }
    replayButtons.forEach(function (button) {
        button.addEventListener('click', function () { startTour(true, button.dataset.tourScope || ''); });
    });
    if (shouldAutoStart && !hasSeen()) window.setTimeout(function () { startTour(false); }, 700);
})();
