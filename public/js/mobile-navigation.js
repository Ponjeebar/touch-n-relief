(function () {
    function syncCustomerMobileSection(sectionId) {
        var shortcuts = document.querySelector('.customer-mobile-shortcuts');
        if (!shortcuts) return;
        if (shortcuts.querySelector('[data-customer-mobile-item="appointments"][aria-expanded="true"]')) return;

        var home = shortcuts.querySelector('[data-customer-mobile-item="home"]');
        var services = shortcuts.querySelector('[data-customer-mobile-item="services"]');
        var servicesActive = sectionId === 'services';

        if (home) {
            if (servicesActive) home.removeAttribute('aria-current');
            else home.setAttribute('aria-current', 'page');
        }

        if (services) {
            if (servicesActive) services.setAttribute('aria-current', 'location');
            else services.removeAttribute('aria-current');
        }
    }

    function setupMenu(rootSelector, buttonSelector, openClass, query) {
        var root = document.querySelector(rootSelector);
        var button = root && root.querySelector(buttonSelector);
        if (!root || !button) return;

        var mobile = window.matchMedia(query);

        function close(restoreFocus) {
            root.classList.remove(openClass);
            button.setAttribute('aria-expanded', 'false');
            button.setAttribute('aria-label', 'Open navigation menu');
            if (restoreFocus) button.focus();
        }

        button.addEventListener('click', function () {
            var opening = !root.classList.contains(openClass);
            root.classList.toggle(openClass, opening);
            button.setAttribute('aria-expanded', opening ? 'true' : 'false');
            button.setAttribute('aria-label', opening ? 'Close navigation menu' : 'Open navigation menu');
        });

        document.addEventListener('click', function (event) {
            if (mobile.matches && !root.contains(event.target)) close(false);
        });

        document.addEventListener('keydown', function (event) {
            if (mobile.matches && event.key === 'Escape' && root.classList.contains(openClass)) {
                close(true);
            }
        });

        root.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                if (mobile.matches) close(false);
            });
        });

        mobile.addEventListener('change', function () { close(false); });
    }

    function setupLandingScrollSpy() {
        var header = document.querySelector('.landing-header');
        var links = Array.from(document.querySelectorAll('[data-landing-section]'));
        var sections = links.map(function (link) {
            var id = link.getAttribute('data-landing-section');
            return { id: id, link: link, section: id ? document.getElementById(id) : null };
        }).filter(function (item) { return item.section; }).sort(function (first, second) {
            return first.section.offsetTop - second.section.offsetTop;
        });
        if (!header || !sections.length) return;

        var activeId = null;
        var scheduled = false;

        function setActive(id) {
            if (activeId === id) return;
            activeId = id;
            sections.forEach(function (item) {
                var active = item.id === id;
                item.link.classList.toggle('is-active', active);
                if (active) item.link.setAttribute('aria-current', 'location');
                else item.link.removeAttribute('aria-current');
            });
            syncCustomerMobileSection(id);
        }

        function update() {
            scheduled = false;
            var marker = header.getBoundingClientRect().height + Math.min(180, window.innerHeight * 0.28);
            var current = '';
            sections.forEach(function (item) {
                if (item.section.getBoundingClientRect().top <= marker) current = item.id;
            });
            if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4) {
                current = sections[sections.length - 1].id;
            }
            setActive(current);
        }

        function scheduleUpdate() {
            if (scheduled) return;
            scheduled = true;
            window.requestAnimationFrame(update);
        }

        sections.forEach(function (item) {
            item.link.addEventListener('click', function () { setActive(item.id); });
        });
        window.addEventListener('scroll', scheduleUpdate, { passive: true });
        window.addEventListener('resize', scheduleUpdate);
        window.addEventListener('hashchange', scheduleUpdate);
        update();
    }

    function setupStaffMoreMenu() {
        var menu = document.querySelector('[data-staff-more-menu]');
        var openButton = document.querySelector('[data-staff-mobile-menu]');
        if (!menu || !openButton) return;

        var closeButtons = menu.querySelectorAll('[data-staff-more-close]');
        var firstCloseButton = menu.querySelector('.staff-mobile-more-close');
        var mobile = window.matchMedia('(max-width: 1024px)');

        function close(restoreFocus) {
            menu.hidden = true;
            openButton.setAttribute('aria-expanded', 'false');
            document.body.classList.remove('staff-mobile-menu-open');
            if (restoreFocus) openButton.focus();
        }

        function open() {
            if (!mobile.matches) return;
            menu.hidden = false;
            openButton.setAttribute('aria-expanded', 'true');
            document.body.classList.add('staff-mobile-menu-open');
            window.requestAnimationFrame(function () { firstCloseButton?.focus(); });
        }

        openButton.addEventListener('click', function () {
            if (menu.hidden) open();
            else close(true);
        });

        closeButtons.forEach(function (button) {
            button.addEventListener('click', function () { close(true); });
        });

        menu.querySelectorAll('a, [data-staff-chat-open]').forEach(function (item) {
            item.addEventListener('click', function () { close(false); });
        });

        document.addEventListener('keydown', function (event) {
            if (menu.hidden) return;
            if (event.key === 'Escape') {
                close(true);
                return;
            }
            if (event.key !== 'Tab') return;

            var focusable = Array.from(menu.querySelectorAll('.staff-mobile-more-sheet a, .staff-mobile-more-sheet button'))
                .filter(function (item) { return !item.disabled && item.tabIndex !== -1; });
            if (!focusable.length) return;
            var first = focusable[0];
            var last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });

        mobile.addEventListener('change', function () { close(false); });
    }

    function setupStaffHeaderActions() {
        var mobileHeader = document.querySelector('[data-staff-mobile-header-actions]');
        var pageHeader = document.querySelector('.main > .topbar, .main > .tt-hero-with-profile');
        var actions = pageHeader?.querySelector(':scope > .right');
        if (!mobileHeader || !pageHeader || !actions) return;

        var placeholder = document.createComment('staff header actions');
        actions.parentNode.insertBefore(placeholder, actions);
        var mobile = window.matchMedia('(max-width: 1024px)');

        function sync() {
            if (mobile.matches) {
                if (actions.parentNode !== mobileHeader) mobileHeader.appendChild(actions);
                return;
            }
            if (actions.parentNode === mobileHeader && placeholder.parentNode) {
                placeholder.parentNode.insertBefore(actions, placeholder.nextSibling);
            }
        }

        mobile.addEventListener('change', sync);
        sync();
    }

    function init() {
        setupMenu('.landing-header', '.mobile-nav-toggle', 'is-mobile-nav-open', '(max-width: 900px)');
        setupLandingScrollSpy();
        setupStaffHeaderActions();
        setupStaffMoreMenu();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
