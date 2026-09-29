(function () {
    'use strict';

    var config = document.getElementById('tnr-customer-tour-config');
    if (!config) return;

    var role = config.dataset.customerTourRole || 'customer';
    var page = config.dataset.customerTourPage || 'page';
    var userId = config.dataset.customerTourUser || role;
    var shouldAutoStart = config.dataset.customerTourAutoStart === '1';
    var storageKey = 'tnr-system-tour:' + userId + ':' + role + ':' + page + ':v2';
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

    function customerLandingSteps() {
        return [
            introduction('Welcome to TouchNRelief', 'This complete tour shows where to compare treatments, book a session, and manage your account.'),
            step(['.landing-nav', '.nav-shell'], 'Main navigation', 'Move between services, therapists, spa information, and contact details.'),
            step(['[data-landing-section="services"]', '#services'], 'Services and THERA packages', 'Compare inclusions, duration, and prices. Packages are separate from individual treatments.'),
            step(['#membership', '.membership-block'], 'Membership information', 'Review membership plans and benefits. Membership purchases are not processed in the system yet.'),
            step(['[data-landing-section="therapists"]', '#therapists'], 'Choose with confidence', 'Review therapist specialties and availability before opening the booking page.'),
            step(['[data-customer-tour="book"]', '.hero-book-btn'], 'Start a booking', 'Choose a date, service, therapist, time, and payment option.', 'top'),
            step(['[data-customer-tour="appointments"]', '[data-tnr-open-transactions]'], 'My Appointments', 'Review active, completed, cancelled, and expired bookings by status.', 'top'),
            step(['[data-customer-tour="profile"]', '.nav-user'], 'Your account', 'Update your profile and wellness preferences or replay this tour.', 'top'),
            step(['.tnr-chat-launcher'], 'Help when you need it', 'Ask about services, hours, bookings, or your account.', 'left'),
        ];
    }

    function bookingSteps() {
        return [
            introduction('Booking walkthrough', 'Complete the steps in order. Later sections unlock after the required earlier choices.'),
            step(['.booking-return-link'], 'Return safely', 'Go back without creating a booking.', 'bottom', 'start'),
            step(['#booking_date'], '1. Choose a date', 'Select an available date to enable the service choices.'),
            step(['#booking-panel-service'], '2. Choose a service', 'Pick an individual service or THERA package and review its duration and price.'),
            step(['#booking-panel-therapist'], '3. Choose a therapist', 'Recommended specialists are highlighted, but any available therapist may be selected.'),
            step(['#time-slots', '.booking-field-time'], '4. Choose a time', 'The legend identifies available, busy, fully booked, and conflicting slots.'),
            step(['#notes'], 'Add useful notes', 'Share preferences or requests the spa team should know.'),
            step(['.booking-submit'], 'Review payment', 'Continue to the secure payment summary after completing every required choice.', 'top'),
        ];
    }

    function profileSteps() {
        return [
            introduction('Account and appointment guide', 'Manage your personal information, preferences, password, and booking history here.'),
            step(['.profile-id', '.profile-head'], 'Profile identity', 'Your name and photo help staff identify your account.'),
            step(['.profile-section', '.profile-card'], 'Personal information', 'Keep your contact information accurate for booking updates.'),
            step(['[data-tnr-open-transactions]', '.profile-section-compact'], 'Appointment history', 'Open categorized upcoming, completed, cancelled, and expired records.'),
            step(['.wellness-preferences', '[data-wellness-preferences]'], 'Wellness preferences', 'Update the preferences used for therapist recommendations.'),
            step(['.password-section', '#current_password'], 'Account security', 'Change your password when needed.'),
        ];
    }

    function staffFoundationSteps() {
        return [
            introduction('Receptionist workspace', 'This guide covers the daily booking, payment, session, therapist, and customer record workflow.'),
            step(['.brand'], 'TouchNRelief staff area', 'This is the receptionist portal for day-to-day spa operations.'),
            step(['#staff-mobile-navigation', '.nav-list'], 'Daily work pages', 'Move between the dashboard, sessions, appointments, therapists, and client records.'),
            step(['.staff-mobile-shortcuts'], 'Mobile shortcuts', 'On smaller screens, use this bar for the most common staff actions.', 'top'),
        ];
    }

    function receptionistDashboardSteps() {
        return staffFoundationSteps().concat([
            step(['.topbar .welcome'], 'Today at a glance', 'The dashboard heading and live clock anchor the current operating day.'),
            step(['.receptionist-metric-grid'], 'Operational summary', 'Monitor ongoing sessions, sales, active therapists, and upcoming appointments.'),
            step(['.chart-grid'], 'Activity trends', 'Use the charts to understand session volume and service activity.'),
            step(['.mobile-dashboard-actions'], 'Quick actions', 'Open appointments, customer records, or therapist monitoring directly.'),
            step(['[data-open-dashboard-notifications]', '[data-topbar-notif-sync]'], 'Notifications', 'Review new bookings, payment updates, cancellations, and reschedules.', 'bottom', 'end'),
            step(['#tnr-settings-trigger'], 'Display settings', 'Switch between light and dark mode.'),
            step(['#tnr-profile-menu-trigger'], 'Profile and tour', 'Update your profile, replay the page tour, or sign out.', 'bottom', 'end'),
        ]);
    }

    function staffPageSteps() {
        var pageSteps = {
            'appointments.index': [
                step(['.topbar .welcome', '.appointments-head'], 'Appointments workspace', 'Create and manage customer and walk-in bookings.'),
                step(['.clients-add-appointment-btn', '[data-mobile-add-appointment]'], 'Create an appointment', 'Add the client, schedule, treatment, therapist, and payment details.'),
                step(['#appointments-search', '.appointments-sub'], 'Find a booking', 'Use search and status controls to narrow the list.'),
                step(['#appointments-list-container', '.appointments-card'], 'Review and act', 'Open a booking to collect a balance, reschedule, cancel, or start the session when allowed.'),
            ],
            'ongoing-sessions.index': [
                step(['.topbar .welcome'], 'Ongoing sessions', 'Monitor treatments that have already started.'),
                step(['.ongoing-wrap', '.session-grid', '.ongoing-list'], 'Live session status', 'Check remaining time and finish a session when the service is complete.'),
            ],
            'completed-sessions.index': [
                step(['.summary-grid'], 'Completed summary', 'Review completed session volume and totals.'),
                step(['.history-head'], 'Search history', 'Search or change the date range before reviewing past sessions.'),
                step(['.history-table'], 'Completed records', 'Verify the service, therapist, payment, notes, and completion details.'),
            ],
            'therapist-tracking.index': [
                step(['.tt-period-bar'], 'Service-hour period', 'Choose the year used for therapist service-hour totals.'),
                step(['.tt-metrics'], 'Availability summary', 'See available and busy therapists plus completed service hours.'),
                step(['.tt-table'], 'Therapist directory', 'Open a therapist to review specialties, status, and service hours.'),
            ],
            'client-records.index': [
                step(['.cr-toolbar', '.cr-filter-bar', '.cr-head'], 'Find a customer', 'Search and filter customer records by activity status.'),
                step(['.cr-list', '.cr-table', '.cr-customer-list'], 'Customer records', 'Open a customer to review their profile and booking history.'),
            ],
            'client-records.show': [
                step(['.cr-profile', '.cr-detail-head'], 'Customer profile', 'Confirm identity and contact information before making changes.'),
                step(['.cr-history', '.cr-transactions'], 'Customer history', 'Review appointments, payments, and completed spa sessions.'),
            ],
        };

        return staffFoundationSteps().concat(pageSteps[page] || [
            step(['main .topbar', 'main header', 'main h1'], 'Current workspace', 'The heading identifies the staff task on this page.'),
            step(['main section', 'main .data-card', 'main table'], 'Controls and records', 'Use the visible controls to review and update permitted records.'),
        ]).concat([
            step(['[data-topbar-notif-sync]', '.topbar-notifications'], 'Stay updated', 'Open notifications for booking and payment activity.', 'bottom', 'end'),
            step(['#tnr-profile-menu-trigger'], 'Your account', 'Update your profile, replay this guide, or sign out.', 'bottom', 'end'),
        ]);
    }

    function buildSteps() {
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

    function startTour(force) {
        if (!force && hasSeen()) return;
        if (!window.driver?.js?.driver) return;
        closeMenus();
        activeTour?.destroy();
        activeTour = window.driver.js.driver({
            steps: buildSteps(), popoverClass: 'tnr-customer-tour', showProgress: true,
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
        button.addEventListener('click', function () { startTour(true); });
    });
    if (shouldAutoStart && !hasSeen()) window.setTimeout(function () { startTour(false); }, 700);
})();
