(function () {
    'use strict';

    var config = document.getElementById('tnr-customer-tour-config');
    if (!config) return;

    var role = config.dataset.customerTourRole || 'customer';
    var page = config.dataset.customerTourPage || 'page';
    var userId = config.dataset.customerTourUser || role;
    var shouldAutoStart = config.dataset.customerTourAutoStart === '1';
    var paymentHoldMinutes = Number.parseInt(config.dataset.paymentHoldMinutes || '15', 10);
    var lateGraceMinutes = Number.parseInt(config.dataset.lateGraceMinutes || '10', 10);
    var tourVersion = 'v3';
    var storageKey = 'tnr-system-tour:' + userId + ':' + role + ':' + page + ':' + tourVersion;
    var activeTour = null;
    var restoreDisclosures = [];
    var replayControl = null;

    function isVisible(element) {
        if (!element || element.closest('[hidden], [inert], [aria-hidden="true"]')) return false;
        if (element.checkVisibility && !element.checkVisibility({ checkVisibilityCSS: true })) return false;
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

    function hideDuringTour(element) {
        if (!element) return;
        element.dataset.tourWasHidden = element.hidden ? '1' : '0';
        element.hidden = true;
    }

    function prepareOngoingSessionExample() {
        var cards = document.getElementById('ongoing-cards');
        if (!cards || cards.querySelector('[data-session-card]')) return;

        hideDuringTour(cards.querySelector('[data-ongoing-empty]'));

        var example = document.createElement('article');
        example.className = 'ongoing-card tnr-tour-demo-record';
        example.dataset.tourDemo = 'ongoing';
        example.innerHTML = [
            '<span class="tnr-tour-demo-label">Tutorial example</span>',
            '<div class="ongoing-head">',
                '<div><div class="ongoing-client-row"><h3>Sample Customer</h3></div><p>Therapist: Juan dela Cruz</p></div>',
                '<button class="complete-btn" type="button" tabindex="-1" aria-disabled="true" data-tour-demo-action>',
                    '<i class="bi bi-check-circle"></i> Complete Session',
                '</button>',
            '</div>',
            '<div class="ongoing-meta-grid">',
                '<div class="meta-box"><span class="meta-label">Service</span><strong>Hot Stone</strong></div>',
                '<div class="meta-box"><span class="meta-label">Duration</span><strong>60 minutes</strong></div>',
                '<div class="meta-box"><span class="meta-label">Elapsed Time</span><strong>15 minutes</strong></div>',
                '<div class="meta-box"><span class="meta-label">Remaining</span><strong><i class="bi bi-clock"></i> 45 minutes</strong></div>',
            '</div>',
            '<div class="progress-row"><span class="meta-label">Progress</span><span class="progress-pct">25%</span></div>',
            '<div class="progress-track" role="progressbar" aria-label="Tutorial session progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="25"><span style="width: 25%"></span></div>',
            '<div class="ongoing-footer"><span class="meta-label">Session Price</span><strong>PHP 145.00</strong></div>',
        ].join('');
        cards.appendChild(example);
    }

    function prepareAppointmentExample() {
        var list = document.querySelector('#appointments-list-container .appointments-list');
        if (!list) return;

        hideDuringTour(list.querySelector('.empty-users'));
        var example = document.createElement('div');
        example.className = 'appt-row tnr-tour-demo-record';
        example.dataset.tourDemo = 'appointment';
        example.setAttribute('role', 'listitem');
        example.innerHTML = [
            '<button class="appt-details-trigger" type="button" disabled data-tour-demo-action aria-label="Example: view appointment details"></button>',
            '<div class="appt-client">',
                '<div class="appt-avatar" aria-hidden="true">SC</div>',
                '<div class="appt-client-main">',
                    '<span class="tnr-tour-demo-label">Tutorial example — not saved</span>',
                    '<div class="appt-client-name-row"><strong>Sample Customer</strong></div>',
                    '<span>Hot Stone · Juan dela Cruz</span>',
                    '<span class="appt-payment-line">PHP 145.00 total · PHP 72.50 paid · PHP 72.50 balance</span>',
                '</div>',
            '</div>',
            '<div class="appt-meta">',
                '<div class="appt-meta-col"><span class="appt-meta-label">Date</span><span class="appt-meta-value">Example date</span></div>',
                '<div class="appt-meta-col"><span class="appt-meta-label">Time</span><span class="appt-meta-value">3:00 PM – 4:00 PM</span></div>',
                '<div class="appt-meta-col appt-meta-col-status"><span class="appt-meta-label">Status</span><span class="appt-status balance-due">Balance Due</span></div>',
            '</div>',
            '<div class="appt-actions" role="group" aria-label="Tutorial appointment actions">',
                '<button class="appt-icon-btn collect-balance" type="button" disabled data-tour-demo-action aria-label="Example: collect remaining balance"><i class="bi bi-cash-coin" aria-hidden="true"></i></button>',
                '<button class="appt-icon-btn start" type="button" disabled data-tour-demo-action aria-label="Example: start after full payment within the allowed start window"><i class="bi bi-play-fill" aria-hidden="true"></i></button>',
                '<button class="appt-icon-btn reschedule" type="button" disabled data-tour-demo-action aria-label="Example: reschedule appointment"><i class="bi bi-calendar2-week" aria-hidden="true"></i></button>',
                '<button class="appt-icon-btn cancel" type="button" disabled data-tour-demo-action aria-label="Example: cancel appointment"><i class="bi bi-x-lg" aria-hidden="true"></i></button>',
            '</div>',
        ].join('');
        list.prepend(example);
    }

    function prepareCompletedSessionExample() {
        var tableBody = document.getElementById('completed-txn-tbody');
        if (!tableBody || tableBody.querySelector('[data-completed-search-row="true"]')) return;

        hideDuringTour(tableBody.querySelector('[data-completed-empty="true"]'));

        var example = document.createElement('tr');
        example.className = 'tnr-tour-demo-record';
        example.dataset.tourDemo = 'completed';
        example.dataset.completedSearchRow = 'true';
        example.innerHTML = [
            '<td><strong>TXN-DEMO</strong><span class="tnr-tour-demo-label">Tutorial example</span></td>',
            '<td>Sample Customer</td>',
            '<td>Tutorial</td>',
            '<td>Juan dela Cruz</td>',
            '<td>Hot Stone</td>',
            '<td>Sep 29, 2026 2:00 PM</td>',
            '<td>60 min</td>',
            '<td class="amount">PHP 145.00</td>',
            '<td><button type="button" class="notes-btn" tabindex="-1" aria-disabled="true" data-tour-demo-action>View Example Notes</button></td>',
        ].join('');
        tableBody.insertBefore(example, document.getElementById('completed-search-empty'));
    }

    function prepareTutorialExamples() {
        if (role !== 'receptionist' && role !== 'admin') return;
        if (page === 'appointments.index') prepareAppointmentExample();
        if (page === 'ongoing-sessions.index') prepareOngoingSessionExample();
        if (page === 'completed-sessions.index') prepareCompletedSessionExample();
    }

    function cleanupTutorialExamples() {
        document.querySelectorAll('[data-tour-demo]').forEach(function (example) { example.remove(); });
        document.querySelectorAll('[data-tour-was-hidden]').forEach(function (element) {
            element.hidden = element.dataset.tourWasHidden === '1';
            delete element.dataset.tourWasHidden;
        });
    }

    function resolveVisibleSteps(steps) {
        return steps.reduce(function (visibleSteps, tourStep) {
            if (!Object.prototype.hasOwnProperty.call(tourStep, 'element')) {
                visibleSteps.push(tourStep);
                return visibleSteps;
            }

            var element = typeof tourStep.element === 'function' ? tourStep.element() : tourStep.element;
            if (!isVisible(element)) return visibleSteps;

            visibleSteps.push(Object.assign({}, tourStep, { element: element }));
            return visibleSteps;
        }, []);
    }

    function customerLandingSteps() {
        return [
            introduction('Welcome to TouchNRelief', 'This guide follows the complete customer journey: compare treatments, choose a therapist, book and pay, then manage every appointment status.'),
            step(['.mobile-nav-toggle', '.nav-shell'], 'Main navigation', 'Use the navigation links, or open Menu on mobile, to find services, therapists, spa information, and contact details.'),
            step(['[data-landing-section="services"]', '#services'], 'Individual services', withExample('Compare the purpose, duration, and price of each treatment before booking.', 'A 60-minute massage and a 90-minute massage reserve different amounts of therapist time.')),
            step(['#packages', '.package-block'], 'THERA packages', withExample('Packages combine several treatments and remain separate from individual services.', 'THERA #2 includes its listed treatments under one package price and duration.')),
            step(['#membership', '.membership-block'], 'Membership information', 'Review membership plans and benefits. Use Purchase membership or Renew membership to pay through PayMongo. Member rates apply to eligible services after payment is verified.'),
            step(['[data-landing-section="therapists"]', '#therapists'], 'Choose with confidence', withExample('Review therapist specialties before opening the booking page. Availability is checked for the service and schedule you select.', 'A therapist profile does not guarantee availability on a particular date.')),
            step(['[data-customer-tour="book"]', '.hero-book-btn'], 'Start a booking', withExample('Choose a service, then a therapist, then a date and available time. Review the payment amount before checkout.', 'The selected service duration and therapist schedule determine which start times are available.'), 'top'),
            step(['[data-customer-tour="appointments"]', '[data-tnr-open-transactions]'], 'My Appointments', withExample('Upcoming and historical records are separated so expired payment holds do not crowd active bookings.', 'A confirmed booking appears under Upcoming; a completed, cancelled, or expired booking appears under History.'), 'top'),
            step(['[data-customer-tour="profile"]', '.nav-user'], 'Your account', 'Update your profile and wellness preferences or replay this tour.', 'top'),
            step(['.tnr-chat-launcher'], 'Help when you need it', 'Ask about services, hours, bookings, or your account.', 'left'),
        ];
    }

    function bookingSteps() {
        return [
            introduction('Booking walkthrough', 'Complete each choice in order. The system prevents incompatible services, therapists, and time slots from being submitted.'),
            step(['.booking-return-link'], 'Return safely', 'Go back without creating a booking.', 'bottom', 'start'),
            step(['.booking-mobile-progress'], 'Booking steps', 'On mobile, complete Service, Therapist, then Date and time. Later fields appear after the earlier selection. This guide explains the current step without changing your selections.'),
            step(['#booking-panel-service'], 'Choose a service or package', withExample('Review the duration, inclusions, and price before choosing a therapist.', 'A 120-minute package needs a longer open schedule than a 45-minute service.')),
            step(['#booking-panel-therapist'], 'Choose a therapist', 'Choose a therapist, or select Any available therapist if you have no preference. An available therapist is assigned when you reserve.'),
            step(['#booking-panel-schedule'], 'Choose a date and time', withExample('After choosing a service and therapist, select a date to load available start times. A start time is blocked when any part of the service would overlap another appointment. Choose another date or therapist if none fit.', 'A slot overlapping your existing appointment cannot be selected.')),
            step(['#notes'], 'Add useful notes', 'Share preferences or requests the spa team should know.'),
            step(['.booking-submit'], 'Review payment', withExample('Continue to the payment summary after every required choice is complete.', 'A downpayment confirms part of the price; the remaining balance must be settled before the session starts.'), 'top'),
        ];
    }

    function profileSteps() {
        return [
            introduction('Account and appointment guide', 'Choose a profile section to manage personal information, account details, password, wellness preferences, or appointments. Save or cancel edits before switching sections.'),
            step(['.profile-id', '.profile-head'], 'Profile identity', 'Your name and photo help staff identify your account.'),
            step(['.profile-category-nav'], 'Profile sections', 'Choose a section from the left on desktop. On mobile, open Profile sections above the details. Only the selected section is shown.'),
            step(['[data-profile-panel]:not([hidden])'], 'Edit the selected section', 'This is the section you currently selected. Personal information, account details, password, and wellness preferences have their own edit controls; My Appointments opens your booking records.'),
            step(['.profile-category-nav'], 'Appointment history', withExample('Choose My Appointments from Profile sections, then View my appointments to open your records.', 'Confirmed and payment-pending bookings stay under Upcoming; completed, cancelled, and expired records stay under History.')),
        ];
    }

    function customerAppointmentSteps() {
        return [
            introduction('My Appointments guide', 'Learn how booking status, payment information, and available actions change throughout an appointment.'),
            step(['#tnr-transactions-modal .txn-modal-header'], 'Appointment center', 'This panel contains only the signed-in account\'s booking and payment records.'),
            step(['.txn-group-tabs'], 'Upcoming and History', withExample('Switch between active commitments and past records.', 'A confirmed appointment is Upcoming. A completed session, cancellation, no-show, or expired payment hold is History.')),
            step(['.txn-toolbar'], 'Search, filter, and sort', withExample('Narrow a long history by date, service, therapist, payment amount, or order.', 'Search “Hot Stone,” filter records with an amount, then sort by most recent.')),
            step(['.txn-card'], 'Read an appointment card', withExample('Each card shows the reference, service, therapist, schedule, duration, amount, and current status.', '“Payment hold expired” means the '+paymentHoldMinutes+'-minute payment window ended and that reservation is no longer active.')),
            step(['[data-txn-payment-toggle]', '.txn-payment-block'], 'Payment details', withExample('Review the payment summary and open Payment details for the recorded method, amounts, and reference. Verified payments stay attached when an eligible booking is rescheduled.', 'A 50% downpayment can show a remaining balance that must be collected before the session starts.')),
            step(['.txn-card-actions'], 'Available actions', withExample('Buttons appear only when the booking rules permit an action.', 'Reschedule can change the therapist, date, and time while keeping the same service and confirmed payment. Cancellation follows the displayed eligibility and refund rules, including after a reschedule. An active payment hold may show Continue payment.')),
            step(['.txn-empty', '#txn-filter-empty:not(.txn-hidden)'], 'No matching appointments', 'If this list is empty, make a booking or clear the active search and filters. The guide does not create a sample booking in your account.'),
            step(['.txn-arrival-policy > summary'], 'Arrival policy', 'Open Arrival and no-show policy for the current grace period and staff review window. The grace period is '+lateGraceMinutes+' minutes; if attendance is not updated after the review window, the system marks a no-show automatically.'),
        ];
    }

    function staffFoundationSteps() {
        return [
            introduction('Receptionist workspace', 'This page-level training covers the complete daily workflow. Each major staff page starts its own guide the first time you open it.'),
            step(['.brand'], 'TouchNRelief staff area', 'This is the receptionist portal for day-to-day spa operations.'),
            step(['.staff-mobile-shortcuts', '.nav-list'], 'Daily work pages', withExample('Use the sidebar on desktop. On mobile, use the bottom shortcuts and More for the remaining pages.', 'Appointments → collect any remaining balance → start an eligible session → Ongoing Sessions → Completed Sessions.')),
            step(['.staff-mobile-shortcuts'], 'Mobile shortcuts', 'On smaller screens, use this bar for the most common staff actions.', 'top'),
        ];
    }

    function receptionistDashboardSteps() {
        return staffFoundationSteps().concat([
            step(['.topbar .welcome'], 'Today at a glance', 'The dashboard heading and live clock anchor the current operating day.'),
            step(['.staff-summary'], 'Operational summary', 'Review today\'s active appointments, ongoing sessions, upcoming appointments, and available therapists.'),
            step(['[aria-labelledby="frontdesk-today-title"]'], 'Today\'s appointments', 'Review the current list and use Open schedule to manage arrivals, balances, rescheduling, session starts, and no-shows.'),
            step(['[aria-labelledby="frontdesk-sessions-title"]'], 'Sessions in progress', 'Review the running treatments and open Ongoing Sessions for timing and completion controls.'),
            step(['a[href*="/ongoing-sessions"]'], 'Ongoing Sessions', withExample('Monitor services already in progress and their elapsed and remaining time.', 'A 60-minute massage started at 2:00 PM shows live progress until it is completed.')),
            step(['a[href*="/completed-sessions"]'], 'Completed Sessions', withExample('Review completed service transactions, payment totals, and session notes.', 'After completing the 2:00 PM massage, its record moves here with the therapist and final amount.')),
            step(['a[href*="/appointments"]'], 'Appointments', withExample('Create walk-in or registered-customer bookings, verify payments, collect balances, reschedule, cancel, and start eligible sessions.', 'For a confirmed 3:00 PM booking with a balance, collect the exact remaining amount before Start Session becomes appropriate.')),
            step(['a[href*="/therapist-tracking"]'], 'Therapist Monitoring', withExample('Check current availability, specialties, and accumulated service hours.', 'A therapist in an active session appears busy and becomes available when the session is completed.')),
            step(['a[href*="/client-records"]'], 'Client Records', withExample('Search customer profiles and inspect their appointment and service history.', 'Open a returning client before assisting them to confirm contact details and past services.')),
            step(['.mobile-dashboard-actions'], 'Quick actions', 'On compact screens, these links open the three most common operational pages.'),
            step(['[data-open-dashboard-notifications]', '[data-topbar-notif-sync]'], 'Notifications', 'Review new bookings, payment updates, cancellations, and reschedules.', 'bottom', 'end'),
            step(['#tnr-profile-menu-trigger'], 'Account menu', 'Open your staff profile, change the display theme, or replay the page tour. Log out is at the bottom of the sidebar, or in the account menu on compact screens.', 'bottom', 'end'),
        ]);
    }

    function staffPageSteps() {
        var pageIntroductions = {
            'dashboard': ['Administrator dashboard guide', 'Review the daily overview, appointments, and current sessions.'],
            'users.index': ['Manage User guide', 'Find and manage receptionist or customer accounts.'],
            'services.index': ['Services and Membership guide', 'Review the catalog, packages, and membership plan.'],
            'reporting.index': ['Reporting guide', 'Set the reporting period, review totals, and download reports.'],
            'landing-settings.edit': ['Landing Page guide', 'Maintain the spa&#39;s published contact details, hours, and official links.'],
            'system-settings.edit': ['System Settings guide', 'Review operational rules and application recovery exports.'],
            'activity-logs.index': ['Activity Log guide', 'Review recorded staff actions.'],
            'appointments.index': ['Appointments guide', 'Learn the booking, payment, rescheduling, cancellation, and session-start workflow on this page.'],
            'ongoing-sessions.index': ['Ongoing Sessions guide', 'Learn how to monitor active treatments and move finished work into completed history.'],
            'completed-sessions.index': ['Completed Sessions guide', 'Learn how to review finished treatments, totals, date ranges, and session notes.'],
            'therapist-tracking.index': ['Therapist Monitoring guide', 'Learn how availability, specialties, and completed service hours support therapist assignment.'],
            'client-records.index': ['Client Records guide', 'Learn how to locate the correct customer and open their complete record.'],
            'client-records.show': ['Customer record guide', 'Learn how to verify customer details and review their appointment and transaction history.'],
        };
        var pageSteps = {
            'dashboard': [
                step(['.staff-overview'], 'Today at TouchNRelief', 'Review today&#39;s operational totals and the sales trend. Open Appointments to work with an individual booking.'),
                step(['[aria-labelledby="admin-today-title"]'], 'Today&#39;s appointments', 'Review the listed bookings and open Appointments to check payment, schedule, and available actions.'),
                step(['[aria-labelledby="admin-sessions-title"]'], 'Current sessions', 'Review treatments already in progress. Ongoing Sessions contains their timing and completion controls.'),
            ],
            'users.index': [
                step(['.users-switch'], 'Receptionists and customers', 'Switch directories before searching or managing an account.'),
                step(['#users-search', '.users-panel'], 'Find an account', 'Locate the correct person in the selected directory. Use Search where it is shown, and check the displayed contact details or account ID before choosing an action.'),
                step(['#toggle-add-receptionist'], 'Add Receptionist', 'Create a staff account here. Review the person&#39;s details before saving.'),
                step(['.users-panel'], 'Account records', 'Use the available row actions to review or edit an account. Read the confirmation before archiving: it prevents sign-in while retaining booking history.'),
            ],
            'services.index': [
                step(['.services-wrap'], 'Service catalog', role === 'admin' ? 'Review service names, durations, prices, and availability. Use a service&#39;s edit control to open Details and Time slots.' : 'Review service names, durations, prices, and availability. Only the administrator can edit the catalog.'),
                step(['[aria-labelledby="packages-heading"]'], 'Packages', 'Review package inclusions, duration, regular price, and eligible member price.'),
                step(['[aria-labelledby="membership-heading"]'], 'Membership', 'Review the configured plan, price, validity, and benefits. Customers purchase memberships through PayMongo.'),
                step(['[data-open-membership-modal]'], 'Edit membership', 'Open the membership editor to update the plan. Review all changes before saving; opening this guide does not change the plan.'),
            ],
            'reporting.index': [
                step(['#repPeriod'], 'Reporting period', 'Choose Daily, Weekly, Monthly, Yearly, or Custom to set the report coverage.'),
                step(['.rep-metrics'], 'Report totals', 'Read totals for the selected period. Collection and sales details below explain the amounts.'),
                step(['.rep-sales-monitoring'], 'Collections and sales', 'Review collection and sales breakdowns. Gateway payments that are not allocated to a booking or membership require review and are excluded from sales totals.'),
                step(['#repSystemHealth'], 'Operational checks', 'Expand these checks when reviewing overdue processing or gateway cases. These checks do not change appointments or financial records.'),
                step(['.rep-download-actions'], 'Export or preview the report', 'Export Excel or open Preview PDF for the selected reporting period. Review the PDF in the new tab, then use the viewer download control to save it.'),
            ],
            'landing-settings.edit': [
                step(['#landingSettingsForm'], 'Contact, hours, and social accounts', 'Edit the spa&#39;s contact information, opening hours, and official social links shown on the landing page.'),
                step(['.ls-preview'], 'Live preview', 'Review the preview as you type. Previewing does not publish your edits.'),
                step(['#landingSettingsForm .ls-actions'], 'Save landing page settings', 'Save when the details are correct. Check the public landing page after saving.'),
            ],
            'system-settings.edit': [
                step(['#systemSettingsForm'], 'Operational rules', 'Review booking and payment, attendance, and reservation abuse settings. Values are applied when the system evaluates the relevant rule.'),
                step(['#systemBackupLink'], 'Application recovery export', 'Download application records as JSON. This is not a PostgreSQL database dump. The file contains sensitive customer and financial data; store it securely.'),
                step(['#systemSettingsForm .ls-actions'], 'Save system settings', 'Review the values before saving. Existing appointment records are not rewritten; current rules are used when eligibility, expiration, or attendance is evaluated.'),
            ],
            'activity-logs.index': [
                step(['.activity-stats'], 'Activity summary', 'Review the activity counts shown for the current log scope.'),
                step(['.activity-filters'], 'Find activity', 'Use the available filters to narrow the log before reviewing an event.'),
                step(['.activity-table'], 'Recorded events', 'Review the recorded actor, action, and time. Use this record as an audit trail rather than an appointment editing screen.'),
            ],
            'appointments.index': [
                step(['.topbar .welcome', '.appointments-head'], 'Appointments workspace', 'Create and manage customer and walk-in bookings.'),
                step(['.metric-grid-appointments'], 'Daily totals', 'See the active-client count and appointments for the selected day.'),
                step(['.clients-add-appointment-btn', '[data-mobile-add-appointment]'], 'Create an appointment', withExample('Choose a registered client or walk-in, then add the service, schedule, and payment. Select a therapist or leave Any available therapist selected.', 'For a walk-in customer, collect the required information at the counter before confirming the booking.')),
                step(['#appointments-search'], 'Search appointments', withExample('Search by client, service, therapist, or appointment detail.', 'Type a client surname to find their booking without scanning the full day.')),
                step(['.appointments-sub'], 'Filter by status', withExample('Use status totals to isolate bookings requiring a specific action.', 'Payment Pending means the initial checkout still needs verification. Balance Due means the booking is confirmed but still has an amount to collect.')),
                step(['#appointments-list-container', '.appointments-card'], 'Appointment list', 'Each row contains the client, service, therapist, schedule, payment state, and permitted actions.'),
                step(['[data-tour-demo="appointment"]', '.appt-row:has([data-open-view])'], 'Review full details', withExample('Click an appointment row to open its details before changing a booking or payment. The tutorial row is not saved and its buttons cannot change records.', 'The sample appointment costs PHP 145.00, with PHP 72.50 paid and PHP 72.50 remaining.')),
                step(['[data-tour-demo="appointment"] .appt-icon-btn.collect-balance', '.appt-icon-btn.collect-balance[data-open-balance]'], 'Collect a remaining balance', withExample('Record only the exact amount actually received at the counter.', 'Collect the sample appointment’s PHP 72.50 balance before starting the session.')),
                step(['.appt-icon-btn.start'], 'Start an eligible session', withExample('Start is allowed only within the configured time window and after required payment.', 'A fully paid 3:00 PM appointment can be started near 3:00 PM, then appears in Ongoing Sessions.')),
                step(['[data-tour-demo="appointment"] .appt-icon-btn.reschedule', '.appt-icon-btn.reschedule[data-open-reschedule]'], 'Reschedule carefully', 'Keep the therapist or choose another, then select a date and time that fit the full service duration. Confirmed payments remain attached; availability is checked again before saving.'),
                step(['[data-tour-demo="appointment"] .appt-icon-btn.cancel', '.appt-icon-btn.cancel[data-open-cancel]'], 'Cancel with a reason', 'Record a clear reason because cancellation and refund information remains in the customer history.'),
                step(['#appointments-export-link'], 'Export appointment records', 'Choose the date coverage and status scope before downloading the appointment CSV.'),
            ],
            'ongoing-sessions.index': [
                step(['.topbar .welcome'], 'Ongoing sessions', 'Monitor treatments that have already started.'),
                step(['#ongoing-cards'], 'Live session board', withExample('Every active service appears as a live card. An empty message means no session is currently running.', 'After staff starts booking BKG-00125, its customer and therapist appear here.')),
                step(['.ongoing-card .ongoing-head'], 'Client and therapist', 'Confirm who is receiving the treatment and which therapist is responsible.'),
                step(['.ongoing-meta-grid'], 'Service timing', withExample('Duration, elapsed time, and remaining time update automatically.', 'A 60-minute service with 15 minutes elapsed shows approximately 45 minutes remaining.')),
                step(['.progress-track'], 'Live progress', 'The progress bar gives a quick visual indication of how far the session has advanced.'),
                step(['.ongoing-footer'], 'Session price', 'The displayed price is the booked service or package price; payment collection remains in Appointments.'),
                step(['.complete-btn'], 'Complete Session', withExample('Complete a finished treatment when this action is available. Sessions also complete automatically at their scheduled end. Finished records appear in Completed Sessions.', 'When the therapist confirms the massage is finished, select Complete Session and verify it in completed history.')),
                step(['[data-ongoing-empty]'], 'When there are no sessions', 'This is a normal operational state. Start an eligible paid appointment from the Appointments page to create an ongoing session.'),
            ],
            'completed-sessions.index': [
                step(['.topbar .welcome'], 'Completed Sessions', 'Review the operational history of finished treatments on this page.'),
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
                step(['.tt-metrics'], 'Availability summary', withExample('Review the team and current status totals. Use appointment availability for a specific service, date, and full treatment duration.', 'A therapist serving an active client is counted as busy until that session is completed.')),
                step(['.tt-table'], 'Therapist directory', 'The desktop table compares name, specialization, current status, and total service hours.'),
                step(['.tt-cards'], 'Mobile therapist cards', 'On smaller screens, the same therapist information is arranged as readable cards.'),
                step(['[data-open-tt-view]'], 'Open therapist details', withExample('Review contact information, specialties, status, and recorded hours.', 'Before assigning a walk-in, confirm the therapist is available and trained for the requested treatment.')),
                step(['.tt-specs'], 'Specializations', 'Use specialties to match treatments with suitable therapists; availability is still checked separately.'),
                step(['.tt-status', '[data-tt-status]'], 'Availability status', 'This is the current operational status. Available does not guarantee every future slot: working schedules, service duration, and existing bookings are checked separately.'),
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
                step(['.cr-labs'], 'Appointment and transaction history', withExample('Review services, therapists, schedules, status, notes, and amounts connected to this customer.', 'A cancelled paid booking remains in history so its payment and refund trail is not lost.')),
            ],
        };

        if (page === 'therapist-tracking.index' && isVisible(document.getElementById('tt-manage-panel'))) {
            return [
                introduction('Team Profiles guide', 'Maintain therapist profiles shown to customers on the landing and booking pages.'),
                step(['[data-open-tt-add]'], 'Add Therapist', 'Add the therapist&#39;s actual details and specialties before saving.'),
                step(['.tt-manage-grid'], 'Published team profiles', 'Use each profile&#39;s edit action to maintain photos, specialties, and other details. Therapist Monitoring handles operational status and schedules.'),
            ];
        }

        var pageIntroduction = pageIntroductions[page] || ['Page guide', 'Learn the controls and records available in this staff workspace.'];

        return [introduction(pageIntroduction[0], pageIntroduction[1])].concat(pageSteps[page] || []);
    }

    function buildSteps(scope) {
        if (scope === 'appointments') return customerAppointmentSteps();
        if (role === 'receptionist') return page === 'receptionist.dashboard' ? receptionistDashboardSteps() : staffPageSteps();
        if (role === 'admin') return staffPageSteps();
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
        document.querySelectorAll('[data-user-menu].open').forEach(function (menu) {
            menu.classList.remove('open');
            menu.querySelector('.nav-user')?.setAttribute('aria-expanded', 'false');
        });
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
        if (isVisible(document.getElementById('tnr-registration-onboarding-modal'))) return;
        activeTour?.destroy();
        replayControl = document.activeElement;
        closeMenus();
        cleanupTutorialExamples();
        if (page === 'profile.edit') {
            var sections = document.querySelector('.profile-category-disclosure');
            if (sections) {
                restoreDisclosures.push({ element: sections, open: sections.open });
                sections.open = true;
            }
        }
        prepareTutorialExamples();
        var visibleSteps = resolveVisibleSteps(buildSteps(scope));
        if (!visibleSteps.length) {
            cleanupTutorialExamples();
            return;
        }
        activeTour = window.driver.js.driver({
            steps: visibleSteps, popoverClass: 'tnr-customer-tour', showProgress: true,
            progressText: '{{current}} of {{total}}', nextBtnText: 'Next', prevBtnText: 'Back', doneBtnText: 'Finish',
            smoothScroll: false, allowClose: true, allowKeyboardControl: true, disableActiveInteraction: true, overlayOpacity: 0.72,
            stagePadding: 8, stageRadius: 10, skipMissingElement: true,
            animate: !window.matchMedia('(prefers-reduced-motion: reduce)').matches,
            onDestroyed: function () {
                markSeen();
                cleanupTutorialExamples();
                restoreDisclosures.forEach(function (saved) { saved.element.open = saved.open; });
                restoreDisclosures = [];
                activeTour = null;
                var focusTarget = isVisible(replayControl) ? replayControl : target('#tnr-profile-menu-trigger', '.nav-user', '.mobile-nav-toggle');
                if (focusTarget instanceof HTMLElement) focusTarget.focus({ preventScroll: true });
            },
        });
        activeTour.drive();
    }

    document.addEventListener('keydown', function (event) {
        if (!activeTour?.isActive()) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopImmediatePropagation();
            activeTour.destroy();
        } else if (event.key === 'Tab') {
            var buttons = Array.from(document.querySelectorAll('.driver-popover button')).filter(function (button) {
                return isVisible(button) && !button.disabled;
            });
            if (!buttons.length) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            var index = buttons.indexOf(document.activeElement);
            buttons[(index + (event.shiftKey ? -1 : 1) + buttons.length) % buttons.length].focus();
        }
    }, true);

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
