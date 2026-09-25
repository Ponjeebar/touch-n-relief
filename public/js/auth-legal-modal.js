(function () {
    var modal = document.querySelector('[data-auth-legal-modal]');
    if (!modal) return;

    var panel = modal.querySelector('.auth-legal-modal-panel');
    var title = modal.querySelector('[data-auth-legal-title]');
    var eyebrow = modal.querySelector('[data-auth-legal-eyebrow]');
    var closeButton = modal.querySelector('.auth-legal-modal-close');
    var previousFocus = null;
    var labels = {
        privacy: { title: 'Privacy Policy', eyebrow: 'Your information' },
        terms: { title: 'Terms and Conditions', eyebrow: 'Using TouchNRelief' }
    };

    function openModal(documentKey, trigger) {
        if (!labels[documentKey]) return;
        previousFocus = trigger;
        title.textContent = labels[documentKey].title;
        eyebrow.textContent = labels[documentKey].eyebrow;
        modal.querySelectorAll('[data-auth-legal-document]').forEach(function (document) {
            document.hidden = document.dataset.authLegalDocument !== documentKey;
        });
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('auth-legal-modal-open');
        panel.querySelector('.auth-legal-modal-body').scrollTop = 0;
        closeButton.focus();
    }

    function closeModal() {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('auth-legal-modal-open');
        if (previousFocus) previousFocus.focus();
    }

    document.querySelectorAll('[data-auth-legal-open]').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            openModal(trigger.dataset.authLegalOpen, trigger);
        });
    });
    modal.querySelectorAll('[data-auth-legal-close]').forEach(function (trigger) {
        trigger.addEventListener('click', closeModal);
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modal.hidden) closeModal();
    });
})();
