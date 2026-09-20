(function () {
    var initialized = new WeakSet();
    var capsLockOn = false;
    var activeInput = null;
    var activeHint = null;
    var activeWrap = null;

    function readCapsLock(event) {
        if (event && typeof event.getModifierState === 'function') {
            capsLockOn = event.getModifierState('CapsLock');
        }
    }

    function setVisible(input, hint, wrap, visible) {
        hint.hidden = !visible;

        if (wrap && wrap.classList) {
            wrap.classList.toggle('password-capslock-active', visible);
        }

        input.setAttribute('aria-describedby', visible ? hint.id : '');
    }

    function refreshActiveHint() {
        if (!activeInput || !activeHint) {
            return;
        }

        setVisible(activeInput, activeHint, activeWrap, capsLockOn);
    }

    document.addEventListener('keydown', function (event) {
        readCapsLock(event);
        refreshActiveHint();
    });

    document.addEventListener('keyup', function (event) {
        readCapsLock(event);
        refreshActiveHint();
    });

    function createHint() {
        var hint = document.createElement('p');
        hint.className = 'password-capslock-hint';
        hint.hidden = true;
        hint.setAttribute('role', 'status');
        hint.setAttribute('aria-live', 'polite');
        hint.innerHTML =
            '<span class="password-capslock-icon" aria-hidden="true">⇪</span>' +
            '<span>Caps Lock is on</span>';
        return hint;
    }

    function insertHint(input, hint) {
        var wrap = input.closest('.auth-password-wrap, .profile-password-wrap, .password-input-wrap, .password-field-wrap');
        if (wrap) {
            wrap.insertAdjacentElement('afterend', hint);
            return wrap;
        }

        var field = input.closest('.mp-field, .profile-field, .auth-field-block');
        if (field) {
            field.appendChild(hint);
            return field;
        }

        input.insertAdjacentElement('afterend', hint);
        return input.parentElement;
    }

    function setupPasswordInput(input) {
        if (!(input instanceof HTMLInputElement) || input.type !== 'password') {
            return;
        }

        if (initialized.has(input)) {
            return;
        }

        initialized.add(input);

        var hint = createHint();
        hint.id = 'capslock-hint-' + Math.random().toString(36).slice(2, 10);
        var wrap = insertHint(input, hint);

        input.addEventListener('focus', function (event) {
            activeInput = input;
            activeHint = hint;
            activeWrap = wrap;
            readCapsLock(event);
            refreshActiveHint();
        });

        input.addEventListener('blur', function () {
            if (activeInput === input) {
                activeInput = null;
                activeHint = null;
                activeWrap = null;
            }

            setVisible(input, hint, wrap, false);
        });
    }

    function scanRoot(root) {
        if (!(root instanceof Element || root instanceof Document)) {
            return;
        }

        if (root instanceof HTMLInputElement && root.type === 'password') {
            setupPasswordInput(root);
        }

        root.querySelectorAll('input[type="password"]').forEach(function (input) {
            setupPasswordInput(input);
        });
    }

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
