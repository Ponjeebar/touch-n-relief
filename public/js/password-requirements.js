(function () {
    document.querySelectorAll('[data-password-policy]').forEach(function (field) {
        var password = field.querySelector('[data-password-primary]');
        var confirmation = field.parentElement.querySelector('[data-password-confirmation]');
        var panel = field.querySelector('[data-password-requirements]');
        if (!password || !panel) return;

        function setRule(name, passed) {
            var row = panel.querySelector('[data-password-rule="' + name + '"]');
            if (!row) return;
            row.classList.toggle('passed', passed);
            var icon = row.querySelector('i');
            if (icon) icon.className = passed ? 'bi bi-check-circle-fill' : 'bi bi-circle';
        }

        function update() {
            var value = password.value || '';
            setRule('length', value.length >= 8);
            setRule('uppercase', /[A-Z]/.test(value));
            setRule('number', /\d/.test(value));
            setRule('match', value.length > 0 && confirmation && value === confirmation.value);
        }

        password.addEventListener('input', update);
        if (confirmation) confirmation.addEventListener('input', update);
        update();
    });
})();
