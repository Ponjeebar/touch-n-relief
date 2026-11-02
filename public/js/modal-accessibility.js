(function () {
    'use strict';

    var dialogs = new Set();
    var returnFocus = new WeakMap();
    var lastControl = null;
    var focusableSelector = [
        'a[href]',
        'button:not([disabled])',
        'input:not([disabled]):not([type="hidden"])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        '[tabindex]:not([tabindex="-1"])'
    ].join(',');

    function isVisible(dialog) {
        return dialog.getClientRects().length > 0
            && window.getComputedStyle(dialog).visibility !== 'hidden';
    }

    function controls(dialog) {
        return Array.from(dialog.querySelectorAll(focusableSelector)).filter(function (element) {
            return element.getClientRects().length > 0 && element.getAttribute('aria-hidden') !== 'true';
        });
    }

    function sync(dialog) {
        var open = isVisible(dialog);
        var wasOpen = dialog.dataset.tnrDialogOpen === 'true';
        dialog.dataset.tnrDialogOpen = open ? 'true' : 'false';
        document.body.classList.toggle('has-open-dialog', Array.from(dialogs).some(isVisible));

        if (open && !wasOpen) {
            var active = document.activeElement;
            var previous = active instanceof HTMLElement && !dialog.contains(active) ? active : lastControl;
            returnFocus.set(dialog, previous);
            window.requestAnimationFrame(function () {
                if (!isVisible(dialog) || dialog.contains(document.activeElement)) return;
                controls(dialog)[0]?.focus();
            });
        }

        if (!open && wasOpen) {
            var configuredTarget = dialog.dataset.returnFocus
                ? document.querySelector(dialog.dataset.returnFocus)
                : null;
            var target = configuredTarget instanceof HTMLElement
                ? configuredTarget
                : returnFocus.get(dialog);
            if (target instanceof HTMLElement && target.isConnected) target.focus({ preventScroll: true });
            returnFocus.delete(dialog);
        }
    }

    function register(dialog) {
        if (!(dialog instanceof HTMLElement) || dialogs.has(dialog)) return;
        dialogs.add(dialog);
        dialog.dataset.tnrDialogOpen = isVisible(dialog) ? 'true' : 'false';
        document.body.classList.toggle('has-open-dialog', Array.from(dialogs).some(isVisible));
        var observer = new MutationObserver(function () { sync(dialog); });
        for (var element = dialog; element && element !== document.body; element = element.parentElement) {
            observer.observe(element, {
                attributes: true,
                attributeFilter: ['class', 'hidden', 'style']
            });
        }
    }

    function rememberControl(event) {
        var control = event.target instanceof Element ? event.target.closest('button, a, [role="button"]') : null;
        if (control instanceof HTMLElement) lastControl = control;
    }

    document.addEventListener('pointerdown', rememberControl, true);
    document.addEventListener('click', rememberControl, true);

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Tab') return;

        var openDialogs = Array.from(dialogs).filter(isVisible);
        var dialog = openDialogs[openDialogs.length - 1];
        if (!dialog) return;

        var items = controls(dialog);
        if (items.length === 0) {
            event.preventDefault();
            dialog.setAttribute('tabindex', '-1');
            dialog.focus();
            return;
        }

        var first = items[0];
        var last = items[items.length - 1];
        if (event.shiftKey && (document.activeElement === first || !dialog.contains(document.activeElement))) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[role="dialog"][aria-modal="true"]').forEach(register);
    });
})();
