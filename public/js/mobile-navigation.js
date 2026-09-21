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

    function init() {
        setupMenu('.sidebar', '.staff-nav-toggle', 'is-mobile-nav-open', '(max-width: 700px)');
        setupMenu('.landing-header', '.mobile-nav-toggle', 'is-mobile-nav-open', '(max-width: 900px)');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
