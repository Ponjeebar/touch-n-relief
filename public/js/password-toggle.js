(function () {
    var WRAP_SELECTOR = '.password-field-wrap, .auth-password-wrap, .profile-password-wrap, .password-input-wrap';
    var TOGGLE_SELECTOR = '[data-pw-toggle], .password-toggle-btn, .auth-password-toggle, .profile-password-toggle';

    function getWrap(input) {
        var parent = input.parentElement;

        return parent && parent.matches(WRAP_SELECTOR) ? parent : null;
    }

    function findToggle(wrap) {
        return wrap ? wrap.querySelector(TOGGLE_SELECTOR) : null;
    }

    function setToggleState(btn, visible) {
        btn.setAttribute('aria-pressed', visible ? 'true' : 'false');
        btn.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');

        var icon = btn.querySelector('i');
        if (icon) {
            icon.classList.toggle('bi-eye', !visible);
            icon.classList.toggle('bi-eye-slash', visible);
        }
    }

    function bindToggle(btn, input) {
        if (btn.dataset.pwBound === 'true') {
            return;
        }

        btn.dataset.pwBound = 'true';

        btn.addEventListener('click', function () {
            if (btn.disabled || input.disabled) {
                return;
            }

            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            setToggleState(btn, show);
        });
    }

    function createToggle(wrap, input) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'password-toggle-btn';
        btn.setAttribute('data-pw-toggle', '');
        btn.setAttribute('aria-label', 'Show password');
        btn.setAttribute('aria-pressed', 'false');
        btn.innerHTML = '<i class="bi bi-eye" aria-hidden="true"></i>';
        wrap.appendChild(btn);
        bindToggle(btn, input);
    }

    function wrapInput(input) {
        if (!(input instanceof HTMLInputElement) || input.type !== 'password') {
            return;
        }

        if (input.dataset.pwInit === 'true') {
            return;
        }

        input.dataset.pwInit = 'true';

        var wrap = getWrap(input);

        if (!wrap) {
            wrap = document.createElement('div');
            wrap.className = 'password-field-wrap';
            input.parentNode.insertBefore(wrap, input);
            wrap.appendChild(input);
        }

        var toggle = findToggle(wrap);

        if (!toggle) {
            createToggle(wrap, input);
            return;
        }

        bindToggle(toggle, input);
    }

    function scanRoot(root) {
        if (!(root instanceof Element || root instanceof Document)) {
            return;
        }

        if (root instanceof HTMLInputElement && root.type === 'password') {
            wrapInput(root);
        }

        root.querySelectorAll('input[type="password"]').forEach(wrapInput);
    }

    window.tnrResetPasswordVisibility = function (root) {
        var scope = root instanceof Element ? root : document;

        scope.querySelectorAll('input[type="password"], input[type="text"][autocomplete="current-password"], input[type="text"][autocomplete="new-password"]').forEach(function (input) {
            if (!(input instanceof HTMLInputElement)) {
                return;
            }

            if (input.type !== 'password') {
                input.type = 'password';
            }

            var wrap = getWrap(input);
            var btn = wrap ? findToggle(wrap) : null;

            if (btn) {
                setToggleState(btn, false);
            }
        });
    };

    function init() {
        scanRoot(document);

        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (node instanceof HTMLElement) {
                        scanRoot(node);
                    }
                });
            });
        });

        if (document.body) {
            observer.observe(document.body, { childList: true, subtree: true });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
