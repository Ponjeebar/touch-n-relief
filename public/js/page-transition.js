(function () {
    'use strict';

    var showDelay = 180;
    var showTimer = null;
    var overlay = null;

    function skeletonLine(className) {
        return '<span class="page-skeleton-line ' + className + '"></span>';
    }

    function createOverlay() {
        if (overlay) return overlay;

        overlay = document.createElement('div');
        overlay.className = 'page-transition-skeleton';
        overlay.setAttribute('aria-hidden', 'true');
        overlay.innerHTML =
            '<div class="page-skeleton-status" role="status" aria-live="polite">Loading the next TouchNRelief page</div>' +
            '<div class="page-skeleton-customer" aria-hidden="true">' +
                '<div class="page-skeleton-nav">' +
                    '<span class="page-skeleton-logo"></span>' +
                    skeletonLine('page-skeleton-brand') +
                    '<div class="page-skeleton-nav-links">' +
                        skeletonLine('page-skeleton-nav-link') +
                        skeletonLine('page-skeleton-nav-link') +
                        skeletonLine('page-skeleton-nav-link') +
                    '</div>' +
                '</div>' +
                '<div class="page-skeleton-content">' +
                    skeletonLine('page-skeleton-eyebrow') +
                    skeletonLine('page-skeleton-title') +
                    skeletonLine('page-skeleton-copy') +
                    '<div class="page-skeleton-columns">' +
                        '<div class="page-skeleton-panel">' + skeletonLine('page-skeleton-panel-title') + skeletonLine('page-skeleton-row') + skeletonLine('page-skeleton-row') + skeletonLine('page-skeleton-row-short') + '</div>' +
                        '<div class="page-skeleton-panel">' + skeletonLine('page-skeleton-panel-title') + skeletonLine('page-skeleton-row') + skeletonLine('page-skeleton-row-short') + '</div>' +
                        '<div class="page-skeleton-panel">' + skeletonLine('page-skeleton-panel-title') + skeletonLine('page-skeleton-row') + skeletonLine('page-skeleton-row') + '</div>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div class="page-skeleton-staff" aria-hidden="true">' +
                '<aside class="page-skeleton-sidebar">' +
                    '<span class="page-skeleton-logo"></span>' +
                    '<span class="page-skeleton-side-item"></span>'.repeat(6) +
                '</aside>' +
                '<div class="page-skeleton-workspace">' +
                    '<div class="page-skeleton-topbar">' + skeletonLine('page-skeleton-heading') + '<span class="page-skeleton-avatar"></span></div>' +
                    '<div class="page-skeleton-metrics">' +
                        '<div class="page-skeleton-metric"></div>'.repeat(4) +
                    '</div>' +
                    '<div class="page-skeleton-data-grid"><div class="page-skeleton-data"></div><div class="page-skeleton-data"></div></div>' +
                '</div>' +
            '</div>';

        document.body.appendChild(overlay);
        return overlay;
    }

    function show() {
        var element = createOverlay();
        element.classList.toggle('is-staff-layout', Boolean(document.querySelector('.dashboard')));
        element.setAttribute('aria-hidden', 'false');
        document.body.setAttribute('aria-busy', 'true');
        requestAnimationFrame(function () {
            element.classList.add('is-visible');
        });
    }

    function schedule() {
        if (showTimer || document.body.getAttribute('aria-busy') === 'true') return;
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
            if (!event.defaultPrevented) schedule();
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
            if (!event.defaultPrevented) schedule();
        }, 0);
    });

    window.addEventListener('pageshow', reset);
    window.addEventListener('pagehide', function () {
        if (showTimer) window.clearTimeout(showTimer);
    });
})();
