(function () {
    'use strict';

    var config = document.getElementById('tnr-customer-tour-config');
    if (!config) return;

    var tourVersion = 'landing-v1';
    var userId = config.getAttribute('data-customer-tour-user') || 'customer';
    var storageKey = 'tnr-customer-tour:' + userId + ':' + tourVersion;
    var shouldAutoStart = config.getAttribute('data-customer-tour-auto-start') === '1';
    var activeTour = null;

    function isVisible(element) {
        if (!element) return false;
        var style = window.getComputedStyle(element);
        var rect = element.getBoundingClientRect();

        return style.display !== 'none'
            && style.visibility !== 'hidden'
            && rect.width > 0
            && rect.height > 0;
    }

    function firstVisible(selectors) {
        for (var i = 0; i < selectors.length; i += 1) {
            var matches = document.querySelectorAll(selectors[i]);
            for (var j = 0; j < matches.length; j += 1) {
                if (isVisible(matches[j])) return matches[j];
            }
        }

        return undefined;
    }

    function markTourSeen() {
        try {
            localStorage.setItem(storageKey, 'complete');
        } catch (error) {
            // The replay control remains available when browser storage is blocked.
        }
    }

    function hasSeenTour() {
        try {
            return localStorage.getItem(storageKey) === 'complete';
        } catch (error) {
            return false;
        }
    }

    function closeOpenNavigation() {
        document.querySelectorAll('[data-user-menu].open').forEach(function (menu) {
            menu.classList.remove('open');
            menu.querySelector('.nav-user')?.setAttribute('aria-expanded', 'false');
        });

        var mobileToggle = document.querySelector('.mobile-nav-toggle[aria-expanded="true"]');
        if (mobileToggle) mobileToggle.click();
    }

    function buildSteps() {
        return [
            {
                popover: {
                    title: 'Welcome to TouchNRelief',
                    description: 'Here are the main tools you can use to choose a service and manage your spa appointments.',
                },
            },
            {
                element: function () {
                    return firstVisible([
                        '[data-customer-tour="services"]',
                        '[data-landing-section="services"]',
                        '#services h2',
                    ]);
                },
                popover: {
                    title: 'Browse services and packages',
                    description: 'Compare available treatments, THERA packages, duration, and pricing before you book.',
                    side: 'bottom',
                    align: 'center',
                },
            },
            {
                element: function () {
                    return firstVisible([
                        '[data-landing-section="therapists"]',
                        '#therapists h2',
                        '#therapists .therapist-trigger',
                    ]);
                },
                popover: {
                    title: 'Meet the therapists',
                    description: 'Review each therapist’s specialties before choosing who you want to book with.',
                    side: 'bottom',
                    align: 'center',
                },
            },
            {
                element: function () {
                    return firstVisible([
                        '[data-customer-tour="book"]',
                        '.hero-book-btn',
                    ]);
                },
                popover: {
                    title: 'Book an appointment',
                    description: 'Choose your date, service, therapist, and available time, then continue to payment.',
                    side: 'top',
                    align: 'center',
                },
            },
            {
                element: function () {
                    return firstVisible([
                        '[data-customer-tour="appointments"]',
                        '[data-tnr-open-transactions]',
                    ]);
                },
                popover: {
                    title: 'Manage appointments',
                    description: 'Check upcoming bookings, continue an active payment, or review completed and expired records.',
                    side: 'top',
                    align: 'center',
                },
            },
            {
                element: function () {
                    return firstVisible([
                        '[data-customer-tour="profile"]',
                        '.nav-user-wrap--desktop .nav-user',
                        '.nav-user-wrap--mobile .nav-user',
                    ]);
                },
                popover: {
                    title: 'Keep your profile updated',
                    description: 'Update your contact information, password, profile photo, and wellness preferences from your profile.',
                    side: 'top',
                    align: 'center',
                },
            },
        ];
    }

    function startTour(force) {
        if (!force && hasSeenTour()) return;
        if (!window.driver?.js?.driver) return;

        closeOpenNavigation();
        activeTour?.destroy();
        activeTour = window.driver.js.driver({
            steps: buildSteps(),
            popoverClass: 'tnr-customer-tour',
            showProgress: true,
            progressText: '{{current}} of {{total}}',
            nextBtnText: 'Next',
            prevBtnText: 'Back',
            doneBtnText: 'Finish',
            smoothScroll: true,
            allowClose: true,
            allowKeyboardControl: true,
            overlayOpacity: 0.72,
            stagePadding: 8,
            stageRadius: 12,
            skipMissingElement: true,
            animate: !window.matchMedia('(prefers-reduced-motion: reduce)').matches,
            onDestroyed: function () {
                markTourSeen();
                activeTour = null;
            },
        });
        activeTour.drive();
    }

    var replayButtons = document.querySelectorAll('[data-start-customer-tour]');
    if (!window.driver?.js?.driver) {
        replayButtons.forEach(function (button) {
            button.remove();
        });
        return;
    }

    replayButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            startTour(true);
        });
    });

    if (shouldAutoStart && !hasSeenTour()) {
        window.setTimeout(function () {
            startTour(false);
        }, 650);
    }
})();
