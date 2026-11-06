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
    const errorMessage = document.getElementById('verification-code-error');
    group.setAttribute('aria-describedby', 'verification-code-help verification-code-error');
    const normalize = value => value.replace(/[^0-9]/g, '');
    const sync = () => {
        code.value = digits.map(input => input.value).join('');
        digits.forEach(input => input.classList.toggle('is-filled', Boolean(input.value)));
        window.clearTimeout(submitTimer);
        if (automatic && !submitting && digits.every(input => /^[0-9]$/.test(input.value))) {
            status.textContent = 'Code complete. Checking shortly…';
            submitTimer = window.setTimeout(() => form.requestSubmit(submit), 450);
        } else if (automatic && !submitting) {
            status.textContent = '';
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

    function unlock() {
        submitting = false;
        form.removeAttribute('aria-busy');
        group.classList.remove('is-checking');
        digits.forEach(input => { input.readOnly = false; });
        form.querySelectorAll('button').forEach(button => button.removeAttribute('aria-disabled'));
    }

    async function checkCode() {
        const controller = new AbortController();
        const timeout = window.setTimeout(() => controller.abort(), 20000);
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
                signal: controller.signal,
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                const error = new Error(payload.errors?.code?.[0]
                    || (response.status === 429 ? 'Too many requests. Wait a minute before trying again.'
                        : response.status === 419 ? 'Your session expired. Request a new recovery code.'
                        : response.status === 404 ? 'This recovery request is no longer available. Request a new code.'
                        : 'The code could not be checked. Please try again.'));
                error.invalidCode = response.status === 422;
                throw error;
            }
            const destination = new URL(payload.redirect, window.location.origin);
            if (destination.origin !== window.location.origin || !destination.pathname.startsWith('/reset-password/')) {
                throw new Error('The next step could not be opened. Request a new recovery code.');
            }
            group.classList.remove('is-checking');
            group.classList.add('is-verified');
            status.classList.add('is-verified');
            status.textContent = 'Code verified. Opening password reset…';
            window.setTimeout(() => window.location.assign(destination.href), 650);
        } catch (error) {
            unlock();
            errorMessage.textContent = error.name === 'AbortError'
                ? 'Checking took too long. Check your connection and try again.'
                : error instanceof TypeError ? 'Could not connect. Check your connection and try again.' : error.message;
            errorMessage.hidden = false;
            digits.forEach(input => {
                if (error.invalidCode) input.setAttribute('aria-invalid', 'true');
            });
            status.textContent = error.invalidCode ? 'Check the code in your email and try again.' : 'Edit a digit to retry, or request a new code.';
            focusDigit(0);
        } finally {
            window.clearTimeout(timeout);
        }
    }

    form.addEventListener('submit', event => {
        window.clearTimeout(submitTimer);
        if (!automatic) return;
        if (submitting) { event.preventDefault(); return; }
        const resend = event.submitter?.hasAttribute('formaction');
        if (!resend) event.preventDefault();
        submitting = true;
        form.setAttribute('aria-busy', 'true');
        status.textContent = resend ? 'Sending a new code…' : 'Checking code…';
        group.classList.add('is-checking');
        digits.forEach(input => { input.readOnly = true; });
        form.querySelectorAll('button').forEach(button => { button.setAttribute('aria-disabled', 'true'); });
        errorMessage.hidden = true;
        if (!resend) checkCode();
    });

    window.addEventListener('pageshow', event => {
        if (!event.persisted || !automatic) return;
        unlock();
        group.classList.remove('is-verified');
        status.classList.remove('is-verified');
        status.textContent = 'Enter a code or request a new one if it has expired.';
    });

    document.querySelector('[data-code-fallback-label]').hidden = true;
    code.type = 'hidden';
    code.removeAttribute('autofocus');
    group.hidden = false;
    if (automatic) {
        submit.hidden = true;
        status.hidden = false;
        status.textContent = '';
        document.getElementById('verification-code-help').textContent = 'Type or paste your code. It will be checked automatically.';
    }
    if (code.value) distribute(code.value, 0);
    else focusDigit(0);
})();
