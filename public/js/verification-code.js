(function () {
    const code = document.getElementById('verification-code');
    const group = document.querySelector('[data-code-digits]');
    if (!code || !group) return;

    const digits = Array.from(group.querySelectorAll('input'));
    const form = code.form;
    const automatic = form.hasAttribute('data-recovery-code');
    const submit = form.querySelector('[data-code-submit]');
    const status = form.querySelector('[data-code-status]');
    let submitTimer;
    let submitting = false;
    const normalize = value => value.replace(/[^0-9]/g, '');
    const sync = () => {
        code.value = digits.map(input => input.value).join('');
        digits.forEach(input => input.classList.toggle('is-filled', Boolean(input.value)));
        window.clearTimeout(submitTimer);
        if (automatic && !submitting && digits.every(input => /^[0-9]$/.test(input.value))) {
            status.textContent = 'Code complete. Checking shortly…';
            submitTimer = window.setTimeout(() => form.requestSubmit(submit), 450);
        } else if (automatic && !submitting) {
            status.textContent = 'Enter all six digits to continue.';
        }
    };
    function focusDigit(index) {
        digits[index].focus();
        digits[index].select();
    }
    function distribute(value, index) {
        const numbers = normalize(value).slice(0, 6);
        if (!numbers) return;
        const start = numbers.length === 6 ? 0 : index;
        for (let offset = 0; offset < numbers.length && start + offset < 6; offset++) {
            digits[start + offset].value = numbers[offset];
            digits[start + offset].removeAttribute('aria-invalid');
        }
        document.getElementById('verification-code-error')?.setAttribute('hidden', '');
        sync();
        focusDigit(Math.min(start + numbers.length, 5));
    }
    digits.forEach((input, index) => {
        input.required = true;
        input.addEventListener('focus', () => input.select());
        input.addEventListener('beforeinput', event => {
            if (event.data && event.data.length > 1) {
                event.preventDefault();
                distribute(event.data, index);
            }
        });
        input.addEventListener('input', () => {
            input.removeAttribute('aria-invalid');
            document.getElementById('verification-code-error')?.setAttribute('hidden', '');
            const value = normalize(input.value);
            input.value = '';
            if (value) distribute(value, index);
            else sync();
        });
        input.addEventListener('paste', event => {
            event.preventDefault();
            distribute(event.clipboardData.getData('text'), index);
        });
        input.addEventListener('keydown', event => {
            if (event.key === 'Backspace' && !input.value && index > 0) {
                event.preventDefault();
                digits[index - 1].value = '';
                sync();
                focusDigit(index - 1);
            } else if (event.key === 'ArrowLeft' && index > 0) {
                event.preventDefault();
                focusDigit(index - 1);
            } else if (event.key === 'ArrowRight' && index < 5) {
                event.preventDefault();
                focusDigit(index + 1);
            }
        });
    });

    form.addEventListener('submit', event => {
        window.clearTimeout(submitTimer);
        if (!automatic) return;
        if (submitting) { event.preventDefault(); return; }
        const resend = event.submitter?.hasAttribute('formaction');
        submitting = true;
        form.setAttribute('aria-busy', 'true');
        status.textContent = resend ? 'Sending a new code…' : 'Checking code…';
        group.classList.add('is-checking');
        digits.forEach(input => { input.readOnly = true; });
        form.querySelectorAll('button').forEach(button => { button.setAttribute('aria-disabled', 'true'); });
    });

    window.addEventListener('pageshow', event => {
        if (!event.persisted || !automatic) return;
        submitting = false;
        form.removeAttribute('aria-busy');
        group.classList.remove('is-checking');
        digits.forEach(input => { input.readOnly = false; });
        form.querySelectorAll('button').forEach(button => button.removeAttribute('aria-disabled'));
        status.textContent = 'Enter a code or request a new one if it has expired.';
    });

    document.querySelector('[data-code-fallback-label]').hidden = true;
    code.type = 'hidden';
    code.removeAttribute('autofocus');
    group.hidden = false;
    if (automatic) {
        submit.hidden = true;
        status.hidden = false;
        status.textContent = 'Enter all six digits to continue.';
        document.getElementById('verification-code-help').textContent = 'Type or paste your code. It will be checked automatically.';
    }
    if (code.value) distribute(code.value, 0);
    else focusDigit(0);
})();
