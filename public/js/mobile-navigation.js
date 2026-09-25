(function () {
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

        var activeId = '';
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

    function init() {
        setupMenu('.sidebar', '.staff-nav-toggle', 'is-mobile-nav-open', '(max-width: 700px)');
        setupMenu('.landing-header', '.mobile-nav-toggle', 'is-mobile-nav-open', '(max-width: 900px)');
        setupLandingScrollSpy();

        var staffMenuButton = document.querySelector('[data-staff-mobile-menu]');
        var staffToggle = document.querySelector('.staff-nav-toggle');
        staffMenuButton?.addEventListener('click', function () {
            staffToggle?.click();
            staffMenuButton.setAttribute('aria-expanded', staffToggle?.getAttribute('aria-expanded') || 'false');
            if (staffToggle?.getAttribute('aria-expanded') === 'true') {
                document.querySelector('.sidebar')?.scrollIntoView({ block: 'start', behavior: 'smooth' });
            }
        });
        if (staffToggle && staffMenuButton) {
            new MutationObserver(function () {
                staffMenuButton.setAttribute('aria-expanded', staffToggle.getAttribute('aria-expanded') || 'false');
            }).observe(staffToggle, { attributes: true, attributeFilter: ['aria-expanded'] });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
