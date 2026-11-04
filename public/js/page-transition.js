(function () {
    'use strict';

    var showDelay = 180;
    var showTimer = null;
    var overlay = null;
    var logoUrl = document.currentScript?.getAttribute('data-loading-logo') || '';
    var destinationPath = '';

    function skeletonLine(className) {
        return '<span class="page-skeleton-line ' + className + '"></span>';
    }

    function createOverlay() {
        if (overlay) return overlay;

        overlay = document.createElement('div');
        overlay.className = 'page-transition-skeleton';
        overlay.setAttribute('aria-hidden', 'true');
        overlay.innerHTML = '<div class="page-skeleton-header">' +
            '<div class="page-skeleton-identity"><img class="page-skeleton-logo" alt="" width="48" height="48">' +
            '<div><strong>TouchNRelief</strong><span>Buenos Touché Spa</span></div></div>' +
            '<div class="page-skeleton-status" role="status" aria-live="polite"></div></div>' +
            '<div class="page-skeleton-progress" aria-hidden="true"></div>' +
            '<div class="page-skeleton-layout" aria-hidden="true"></div>';
        overlay.querySelector('.page-skeleton-logo').src = logoUrl;

        document.body.appendChild(overlay);
        return overlay;
    }

    function show() {
        var element = createOverlay();
        var mode = 'customer';
        var label = 'Loading your next page';
        if (/^\/booking\/?$/.test(destinationPath)) {
            mode = 'booking';
            label = 'Loading appointment booking';
        } else if (/^\/profile(?:\/|$)/.test(destinationPath)) {
            mode = 'profile';
            label = 'Loading your profile';
        } else if (/^\/(login|register|forgot-password|verify-code)(?:\/|$)/.test(destinationPath)) {
            mode = 'auth';
            label = 'Loading account access';
        } else if (destinationPath === '/') {
            mode = 'home';
            label = 'Loading Buenos Touché Spa';
        } else if (document.querySelector('.dashboard') || /^\/(dashboard|receptionist-dashboard|appointments)(?:\/|$)/.test(destinationPath)) {
            mode = 'staff';
            label = 'Loading your workspace';
        }
        element.setAttribute('data-loading-layout', mode);
        element.querySelector('.page-skeleton-status').textContent = label;
        element.querySelector('.page-skeleton-layout').innerHTML = loadingLayout(mode);
        element.setAttribute('aria-hidden', 'false');
        document.body.setAttribute('aria-busy', 'true');
        requestAnimationFrame(function () {
            element.classList.add('is-visible');
        });
    }

    function loadingLayout(mode) {
        var heading = skeletonLine('page-skeleton-title') + skeletonLine('page-skeleton-copy');
        var choice = '<div class="page-skeleton-choice">' + skeletonLine('page-skeleton-image') +
            '<div>' + skeletonLine('page-skeleton-name') + skeletonLine('page-skeleton-detail') + '</div></div>';
        var fields = '<div class="page-skeleton-field">' + skeletonLine('page-skeleton-label') + skeletonLine('page-skeleton-input') + '</div>';
        if (mode === 'booking') {
            return '<div class="page-skeleton-content">' + heading + '<div class="page-skeleton-booking">' +
                '<section class="page-skeleton-section"><h2>Services</h2>' + choice.repeat(3) + '</section>' +
                '<section class="page-skeleton-section"><h2>Date and time</h2>' + fields +
                '<div class="page-skeleton-times">' + skeletonLine('page-skeleton-time').repeat(12) + '</div>' + skeletonLine('page-skeleton-action') + '</section>' +
                '<section class="page-skeleton-section"><h2>Therapists</h2>' + choice.repeat(3) + '</section></div></div>';
        }
        if (mode === 'staff') {
            return '<aside class="page-skeleton-sidebar">' + skeletonLine('page-skeleton-side-item').repeat(6) + '</aside>' +
                '<div class="page-skeleton-content">' + heading + '<div class="page-skeleton-toolbar">' + skeletonLine('page-skeleton-input').repeat(2) + '</div>' +
                '<div class="page-skeleton-records">' + ('<div class="page-skeleton-record">' + skeletonLine('page-skeleton-name') + skeletonLine('page-skeleton-detail').repeat(2) + skeletonLine('page-skeleton-state') + '</div>').repeat(6) + '</div></div>';
        }
        if (mode === 'auth') {
            return '<div class="page-skeleton-auth">' + heading + fields.repeat(2) + skeletonLine('page-skeleton-action') + '</div>';
        }
        if (mode === 'profile') {
            return '<div class="page-skeleton-content">' + heading + '<div class="page-skeleton-profile">' +
                '<div class="page-skeleton-person">' + skeletonLine('page-skeleton-portrait') + skeletonLine('page-skeleton-name') + '</div>' +
                '<div class="page-skeleton-fields">' + fields.repeat(6) + skeletonLine('page-skeleton-action') + '</div></div></div>';
        }
        if (mode === 'home') {
            return '<div class="page-skeleton-home">' + heading + skeletonLine('page-skeleton-copy') + skeletonLine('page-skeleton-action') + '</div>';
        }
        return '<div class="page-skeleton-content">' + heading + '<div class="page-skeleton-section">' + skeletonLine('page-skeleton-copy').repeat(4) + '</div></div>';
    }

    function schedule(url) {
        if (showTimer || document.body.getAttribute('aria-busy') === 'true') return;
        destinationPath = new URL(url || window.location.href, window.location.href).pathname;
        showTimer = window.setTimeout(show, showDelay);
    }

    function reset() {
        if (showTimer) {
            window.clearTimeout(showTimer);
            showTimer = null;
        }

        if (overlay) {
            overlay.classList.remove('is-visible');
            overlay.setAttribute('aria-hidden', 'true');
        }

        document.body.removeAttribute('aria-busy');
    }

    function isPageNavigation(link) {
        if (!(link instanceof HTMLAnchorElement)) return false;
        if (link.hasAttribute('download') || link.dataset.pageTransition === 'off') return false;
        if (link.target && link.target.toLowerCase() !== '_self') return false;

        var rawHref = (link.getAttribute('href') || '').trim();
        if (!rawHref || rawHref.charAt(0) === '#' || /^(mailto:|tel:|javascript:)/i.test(rawHref)) return false;

        var destination;
        try {
            destination = new URL(link.href, window.location.href);
        } catch (error) {
            return false;
        }

        if (destination.origin !== window.location.origin || !/^https?:$/.test(destination.protocol)) return false;

        var sameDocument = destination.pathname === window.location.pathname
            && destination.search === window.location.search
            && destination.hash;

        return !sameDocument;
    }

    document.addEventListener('click', function (event) {
        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        var target = event.target;
        if (!(target instanceof Element)) return;

        var link = target.closest('a[href]');
        if (!isPageNavigation(link)) return;

        window.setTimeout(function () {
            if (!event.defaultPrevented) schedule(link.href);
        }, 0);
    });

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement) || form.dataset.pageTransition === 'off') return;
        if (form.target && form.target.toLowerCase() !== '_self') return;
        if ((form.getAttribute('method') || '').toLowerCase() === 'dialog') return;

        var submitter = event.submitter;
        if (submitter && submitter.dataset.pageTransition === 'off') return;

        window.setTimeout(function () {
            if (!event.defaultPrevented) schedule(submitter?.hasAttribute('formaction') ? submitter.formAction : form.action);
        }, 0);
    });

    window.addEventListener('pageshow', reset);
    window.addEventListener('pagehide', function () {
        if (showTimer) window.clearTimeout(showTimer);
    });
})();
