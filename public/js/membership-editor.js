(function () {
    const dialogs = Array.from(document.querySelectorAll('.membership-modal'));
    if (!dialogs.length) return;

    function open(dialog) {
        if (!dialog) return;
        dialog.classList.remove('hidden-section');
        dialog.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        dialog.querySelector('.membership-modal-body').scrollTop = 0;
        dialog.querySelector('input[name="name"]')?.focus({ preventScroll: true });
    }

    function close(dialog) {
        dialog.classList.add('hidden-section');
        dialog.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        document.querySelector(dialog.dataset.returnFocus)?.focus({ preventScroll: true });
    }

    document.querySelectorAll('[data-open-membership-modal]').forEach(function (button) {
        button.addEventListener('click', function () {
            open(document.getElementById(button.dataset.openMembershipModal));
        });
    });
    dialogs.forEach(function (dialog) {
        dialog.querySelectorAll('[data-close-membership-modal]').forEach(function (button) {
            button.addEventListener('click', function () { close(dialog); });
        });
    });
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        const dialog = dialogs.find(dialog => !dialog.classList.contains('hidden-section'));
        if (dialog) close(dialog);
    });
    open(dialogs.find(dialog => dialog.hasAttribute('data-membership-errors')));
})();
