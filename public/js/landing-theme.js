(function () {
    'use strict';

    var storageKey = 'tnr-theme';

    function currentTheme() {
        return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    }

    function syncControl(button, mode) {
        var isDark = mode === 'dark';
        var nextMode = isDark ? 'light' : 'dark';
        var label = 'Switch to ' + nextMode + ' mode';

        button.setAttribute('aria-pressed', isDark ? 'true' : 'false');
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var button = document.getElementById('tnr-landing-theme-toggle');
        if (!button) return;

        syncControl(button, currentTheme());

        button.addEventListener('click', function () {
            var mode = currentTheme() === 'dark' ? 'light' : 'dark';

            if (mode === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            } else {
                document.documentElement.removeAttribute('data-theme');
            }

            try {
                localStorage.setItem(storageKey, mode);
            } catch (e) {}

            syncControl(button, mode);
        });
    });
})();
