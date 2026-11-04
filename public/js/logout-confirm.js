(function () {
    if (window.__tnrLogoutConfirm) {
        return;
    }
    window.__tnrLogoutConfirm = true;

    var modal = null;
    var pendingForm = null;
    var lastFocused = null;

    function isLogoutForm(form) {
        if (!(form instanceof HTMLFormElement)) {
            return false;
        }

        try {
            var url = new URL(form.getAttribute('action') || '', window.location.origin);
            return /\/logout\/?$/.test(url.pathname);
        } catch (e) {
            return false;
        }
    }

    function openModal(form, options) {
        if (!modal) {
            return;
        }

        pendingForm = form;
        options = options || {};
        modal.dataset.confirmation = options.tone || 'positive';
        modal.querySelector('.lo-title').textContent = options.title || 'Log out?';
        modal.querySelector('.lo-text').textContent = options.message || 'You will need to sign in again to access your account.';
        modal.querySelector('.lo-btn-confirm').textContent = options.confirmLabel || 'Yes, log out';
        modal.querySelector('.lo-btn-cancel').textContent = options.cancelLabel || 'Stay signed in';
        modal.querySelector('.lo-icon i').className = 'bi ' + (options.icon || 'bi-box-arrow-right');
        lastFocused = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        modal.classList.remove('lo-hidden');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('lo-modal-open');
        modal.querySelector('.lo-btn-cancel')?.focus();
    }

    window.tnrConfirmForm = function (form, options) {
        if (form instanceof HTMLFormElement) openModal(form, options);
    };

    function closeModal() {
        if (!modal) {
            return;
        }

        pendingForm = null;
        modal.classList.add('lo-hidden');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('lo-modal-open');
        lastFocused?.focus();
        lastFocused = null;
    }

    function confirmAction() {
        if (!pendingForm) {
            closeModal();
            return;
        }

        var form = pendingForm;
        pendingForm = null;
        closeModal();
        if (isLogoutForm(form)) form.dataset.logoutConfirmed = '1';
        form.submit();
    }

    document.addEventListener('DOMContentLoaded', function () {
        modal = document.getElementById('tnr-logout-confirm-modal');
        if (!modal) {
            return;
        }

        modal.querySelectorAll('[data-lo-close]').forEach(function (el) {
            el.addEventListener('click', closeModal);
        });

        modal.querySelector('[data-lo-confirm]')?.addEventListener('click', confirmAction);

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape' || modal.classList.contains('lo-hidden')) {
                return;
            }
            e.preventDefault();
            e.stopImmediatePropagation();
            closeModal();
        }, true);

        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (form instanceof HTMLFormElement && form.hasAttribute('data-action-confirm-title')) {
                e.preventDefault();
                openModal(form, {
                    title: form.dataset.actionConfirmTitle,
                    message: form.dataset.actionConfirmMessage,
                    confirmLabel: form.dataset.actionConfirmLabel,
                    cancelLabel: 'Go back',
                    tone: form.dataset.actionConfirmTone || 'caution',
                    icon: 'bi-arrow-counterclockwise'
                });
                return;
            }
            if (!isLogoutForm(form)) {
                return;
            }
            if (form.dataset.logoutConfirmed === '1') {
                return;
            }

            e.preventDefault();
            openModal(form);
        }, true);
    });
})();
