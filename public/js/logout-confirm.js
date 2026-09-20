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

    function openModal(form) {
        if (!modal) {
            return;
        }

        pendingForm = form;
        lastFocused = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        modal.classList.remove('lo-hidden');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('lo-modal-open');
        modal.querySelector('.lo-btn-confirm')?.focus();
    }

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

    function confirmLogout() {
        if (!pendingForm) {
            closeModal();
            return;
        }

        var form = pendingForm;
        pendingForm = null;
        closeModal();
        form.dataset.logoutConfirmed = '1';
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

        modal.querySelector('[data-lo-confirm]')?.addEventListener('click', confirmLogout);

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape' || modal.classList.contains('lo-hidden')) {
                return;
            }
            closeModal();
        });

        document.addEventListener('submit', function (e) {
            var form = e.target;
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
