{{-- Apply saved theme before paint to avoid flash --}}
<link rel="stylesheet" href="{{ asset('css/password-capslock.css') }}">
<link rel="stylesheet" href="{{ asset('css/password-toggle.css') }}">
<script src="{{ asset('js/password-capslock.js') }}" defer></script>
<script src="{{ asset('js/password-toggle.js') }}" defer></script>
<script>
(function () {
    try {
        var k = 'tnr-theme';
        var v = localStorage.getItem(k);
        if (v === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        } else {
            document.documentElement.removeAttribute('data-theme');
        }
    } catch (e) {}
})();
(function () {
    if (window.__tnrThemeApi) return;
    window.__tnrThemeApi = true;
    var k = 'tnr-theme';

    window.tnrGetTheme = function () {
        return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    };

    window.tnrSetTheme = function (mode) {
        if (mode !== 'dark' && mode !== 'light') return;
        if (mode === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        } else {
            document.documentElement.removeAttribute('data-theme');
        }
        try {
            localStorage.setItem(k, mode);
        } catch (e) {}
        syncThemeControls(mode);
    };

    window.tnrToggleTheme = function () {
        window.tnrSetTheme(window.tnrGetTheme() === 'dark' ? 'light' : 'dark');
    };

    function syncThemeControls(mode) {
        var pairs = [
            ['tnr-theme-light', 'tnr-theme-dark'],
            ['tnr-theme-light-auth', 'tnr-theme-dark-auth'],
        ];
        pairs.forEach(function (ids) {
            var lightBtn = document.getElementById(ids[0]);
            var darkBtn = document.getElementById(ids[1]);
            if (lightBtn) {
                lightBtn.classList.toggle('active', mode === 'light');
                lightBtn.setAttribute('aria-pressed', mode === 'light' ? 'true' : 'false');
            }
            if (darkBtn) {
                darkBtn.classList.toggle('active', mode === 'dark');
                darkBtn.setAttribute('aria-pressed', mode === 'dark' ? 'true' : 'false');
            }
        });
        document.querySelectorAll('.mp-theme-row').forEach(function (btn) {
            btn.classList.toggle('is-dark', mode === 'dark');
            btn.setAttribute('aria-checked', mode === 'dark' ? 'true' : 'false');
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        try {
            var saved = localStorage.getItem(k);
            if (saved === 'dark' || saved === 'light') {
                window.tnrSetTheme(saved);
            } else {
                syncThemeControls(window.tnrGetTheme());
            }
        } catch (e) {
            syncThemeControls(window.tnrGetTheme());
        }

        function wire(lightId, darkId) {
            var lightBtn = document.getElementById(lightId);
            var darkBtn = document.getElementById(darkId);
            lightBtn?.addEventListener('click', function () {
                window.tnrSetTheme('light');
            });
            darkBtn?.addEventListener('click', function () {
                window.tnrSetTheme('dark');
            });
        }
        wire('tnr-theme-light', 'tnr-theme-dark');
        wire('tnr-theme-light-auth', 'tnr-theme-dark-auth');

        var profileModal = document.getElementById('tnr-my-profile-modal');
        var trigger = document.getElementById('tnr-profile-menu-trigger');
        var profileWrap = document.querySelector('[data-tnr-profile-wrap]');
        var profileDropdown = document.getElementById('tnr-profile-dropdown');
        var settingsTrigger = document.getElementById('tnr-settings-trigger');
        var settingsWrap = document.querySelector('[data-tnr-settings-wrap]');
        var settingsDropdown = document.getElementById('tnr-settings-dropdown');

        function isDropdownOpen() {
            return profileDropdown && !profileDropdown.classList.contains('profile-dropdown-hidden');
        }

        function closeDropdown() {
            if (!profileDropdown) return;
            profileDropdown.classList.add('profile-dropdown-hidden');
            trigger?.setAttribute('aria-expanded', 'false');
            profileWrap?.classList.remove('is-menu-open');
        }

        function isSettingsOpen() {
            return settingsDropdown && !settingsDropdown.classList.contains('settings-dropdown-hidden');
        }

        function closeSettingsDropdown() {
            if (!settingsDropdown) return;
            settingsDropdown.classList.add('settings-dropdown-hidden');
            settingsTrigger?.setAttribute('aria-expanded', 'false');
            settingsWrap?.classList.remove('is-menu-open');
        }

        function openSettingsDropdown() {
            if (!settingsDropdown) return;
            settingsDropdown.classList.remove('settings-dropdown-hidden');
            settingsTrigger?.setAttribute('aria-expanded', 'true');
            settingsWrap?.classList.add('is-menu-open');
        }

        function toggleSettingsDropdown() {
            if (isSettingsOpen()) closeSettingsDropdown();
            else openSettingsDropdown();
        }

        function openDropdown() {
            if (!profileDropdown) return;
            profileDropdown.classList.remove('profile-dropdown-hidden');
            trigger?.setAttribute('aria-expanded', 'true');
            profileWrap?.classList.add('is-menu-open');
        }

        function toggleDropdown() {
            if (isDropdownOpen()) closeDropdown();
            else openDropdown();
        }

        function closeProfileModalQuick() {
            if (!profileModal) return;
            profileModal.classList.add('mp-hidden');
            document.body.classList.remove('modal-open');
        }

        trigger?.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (profileModal && !profileModal.classList.contains('mp-hidden')) {
                closeProfileModalQuick();
                return;
            }
            closeSettingsDropdown();
            toggleDropdown();
        });

        settingsTrigger?.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            closeDropdown();
            toggleSettingsDropdown();
        });

        document.getElementById('tnr-settings-theme-toggle')?.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            window.tnrToggleTheme();
        });

        document.addEventListener('click', function (e) {
            if (isDropdownOpen() && !e.target.closest('[data-tnr-profile-wrap]')) {
                closeDropdown();
            }
            if (isSettingsOpen() && !e.target.closest('[data-tnr-settings-wrap]')) {
                closeSettingsDropdown();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            if (isDropdownOpen()) {
                closeDropdown();
                return;
            }
            if (isSettingsOpen()) {
                closeSettingsDropdown();
            }
        });

        @auth
            @if (auth()->user()->isReceptionist())
                // Log receptionist panel clicks (sidebar/topbar/buttons) into Activity Log.
                var clickLogUrlBase = "{{ route('activity-logs.click') }}";
                var throttleUntil = 0;
                document.addEventListener('click', function (event) {
                    var target = event.target;
                    if (!(target instanceof HTMLElement)) return;
                    var clickable = target.closest('a, button, [role="button"]');
                    if (!(clickable instanceof HTMLElement)) return;
                    if (!clickable.closest('.dashboard')) return;

                    var now = Date.now();
                    if (now < throttleUntil) return;
                    throttleUntil = now + 120;

                    var labelText = (clickable.textContent || '').replace(/\s+/g, ' ').trim();
                    var iconOnlyLabel = clickable.getAttribute('aria-label') || clickable.getAttribute('title') || '';
                    var label = labelText || iconOnlyLabel || 'Clicked UI element';
                    var href = clickable instanceof HTMLAnchorElement ? (clickable.getAttribute('href') || '') : '';
                    var id = clickable.id || '';
                    var className = typeof clickable.className === 'string' ? clickable.className : '';
                    var context = clickable.closest('.sidebar') ? 'sidebar' : (clickable.closest('.topbar') ? 'topbar' : 'panel');
                    var targetText = (id ? '#' + id + ' ' : '') + (className ? '.' + className.replace(/\s+/g, '.') : clickable.tagName.toLowerCase());

                    try {
                        var u = new URL(clickLogUrlBase, window.location.origin);
                        u.searchParams.set('label', 'Clicked ' + label.slice(0, 90));
                        u.searchParams.set('target', targetText.slice(0, 120));
                        u.searchParams.set('context', context);
                        if (href) u.searchParams.set('url', href.slice(0, 255));
                        fetch(u.toString(), { method: 'GET', credentials: 'same-origin', keepalive: true }).catch(function () {});
                    } catch (e) {}
                }, true);
            @endif
        @endauth
    });
})();
</script>
@include('partials.profile-edit-modal-script')
@include('partials.profile-transactions-script')
@auth
    @include('partials.logout-confirm-modal')
@endauth
@unless (request()->routeIs('login', 'password.*'))
    @include('partials.chatbot-assets')
@endunless
