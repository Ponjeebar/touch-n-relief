(function () {
    const container = document.getElementById('container');
    const registerBtn = document.getElementById('register');
    const loginBtn = document.getElementById('login');

    if (!container) {
        return;
    }

    let switching = false;
    const signUpContainer = container.querySelector('.form-container.sign-up');
    const signInContainer = container.querySelector('.form-container.sign-in');
    const registerForm = container.querySelector('[data-auth-register-form="true"]');
    const loginPanel = container.querySelector('.auth-login-panel');
    const forgotPanel = container.querySelector('.auth-forgot-panel');
    const toggleLeft = container.querySelector('.toggle-left');
    const toggleRight = container.querySelector('.toggle-right');

    function setInteractive(element, interactive) {
        if (!(element instanceof HTMLElement)) return;
        element.toggleAttribute('inert', !interactive);
        element.setAttribute('aria-hidden', interactive ? 'false' : 'true');
    }

    function focusFirstField(panel) {
        const field = Array.from(panel?.querySelectorAll('input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled])') || [])
            .find(function (input) { return input.getClientRects().length > 0; });
        if (field instanceof HTMLElement) field.focus({ preventScroll: true });
    }

    function syncPanelAccessibility(moveFocus) {
        const showRegister = container.classList.contains('active');
        const showForgot = !showRegister && container.classList.contains('sign-in-forgot-active');

        setInteractive(signUpContainer, showRegister);
        setInteractive(signInContainer, !showRegister);
        setInteractive(registerForm, showRegister);
        setInteractive(loginPanel, !showRegister && !showForgot);
        setInteractive(forgotPanel, showForgot);
        setInteractive(toggleLeft, showRegister);
        setInteractive(toggleRight, !showRegister);

        if (moveFocus) {
            window.requestAnimationFrame(function () {
                focusFirstField(showRegister ? registerForm : (showForgot ? forgotPanel : loginPanel));
            });
        }
    }

    async function setPanel(showRegister) {
        if (switching || container.classList.contains('active') === showRegister) return;

        const mobile = window.matchMedia('(max-width: 700px)').matches;
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const outgoing = container.querySelector(showRegister ? '.sign-in form' : '.sign-up form');
        const incoming = container.querySelector(showRegister ? '.sign-up form' : '.sign-in .auth-login-panel');
        const direction = showRegister ? -1 : 1;
        let exitAnimation;

        if (mobile && !reduceMotion && outgoing?.animate && incoming?.animate) {
            switching = true;
            try {
                exitAnimation = outgoing.animate([
                    { opacity: 1, transform: 'translateX(0)' },
                    { opacity: 0, transform: `translateX(${direction * 20}px)` },
                ], { duration: 180, easing: 'ease-in', fill: 'forwards' });
                await exitAnimation.finished;
            } catch (_) {
                // A viewport change can cancel an in-progress animation.
            }
        }

        if (showRegister) {
            container.classList.add('active');
        } else {
            container.classList.remove('active');
        }
        container.classList.remove('sign-in-forgot-active');
        syncPanelAccessibility(false);
        exitAnimation?.cancel();

        if (switching) {
            if (mobile && !reduceMotion) {
                try {
                    await incoming.animate([
                        { opacity: 0, transform: `translateX(${-direction * 24}px)` },
                        { opacity: 1, transform: 'translateX(0)' },
                    ], { duration: 320, easing: 'cubic-bezier(.2,.75,.2,1)' }).finished;
                } catch (_) {
                    // Keep the selected panel visible if the animation is interrupted.
                }
            }
            switching = false;
        }

        syncPanelAccessibility(true);
    }

    if (registerBtn) {
        registerBtn.addEventListener('click', function () {
            setPanel(true);
        });
    }

    if (loginBtn) {
        loginBtn.addEventListener('click', function () {
            setPanel(false);
        });
    }

    const loginIdentifier = loginPanel?.querySelector('input[name="login"]');
    const loginPassword = loginPanel?.querySelector('input[name="password"]');

    loginIdentifier?.addEventListener('keydown', function (event) {
        if (event.key !== 'Tab' || event.shiftKey || event.altKey || event.ctrlKey || event.metaKey) return;
        if (!(loginPassword instanceof HTMLInputElement) || loginPassword.disabled) return;

        event.preventDefault();
        loginPassword.focus();
    });

    document.querySelectorAll('[data-auth-panel]').forEach(function (el) {
        el.addEventListener('click', function () {
            setPanel(el.getAttribute('data-auth-panel') === 'register');
        });
    });

    var forgotOpenBtn = document.querySelector('[data-auth-forgot-open="true"]');
    var forgotCloseBtn = document.querySelector('[data-auth-forgot-close="true"]');

    forgotOpenBtn?.addEventListener('click', function (event) {
        event.preventDefault();
        container.classList.add('sign-in-forgot-active');
        syncPanelAccessibility(true);
    });

    forgotCloseBtn?.addEventListener('click', function () {
        window.location.assign(forgotCloseBtn.getAttribute('data-login-url') || '/login');
    });

    forgotPanel?.addEventListener('submit', function (event) {
        if (forgotPanel.getAttribute('aria-busy') === 'true') { event.preventDefault(); return; }
        forgotPanel.setAttribute('aria-busy', 'true');
        const send = forgotPanel.querySelector('button[type="submit"]');
        send.textContent = 'Sending code…';
        send.setAttribute('aria-disabled', 'true');
    });

    window.addEventListener('pageshow', function (event) {
        if (!event.persisted || !forgotPanel) return;
        forgotPanel.removeAttribute('aria-busy');
        const send = forgotPanel.querySelector('button[type="submit"]');
        send.textContent = 'Send verification code';
        send.removeAttribute('aria-disabled');
    });

    if (registerForm) {
        var passwordInput = registerForm.querySelector('input[name="password"]');
        var confirmInput = registerForm.querySelector('input[name="password_confirmation"]');
        const steps = Array.from(registerForm.querySelectorAll('[data-register-step]'));
        const progress = Array.from(registerForm.querySelectorAll('[data-register-progress]'));
        const back = registerForm.querySelector('[data-register-back]');
        const next = registerForm.querySelector('[data-register-next]');
        const submit = registerForm.querySelector('[data-register-submit]');
        let currentStep = 0;
        let stepAnimation;
        let contactCheck;
        const contactFields = ['email', 'contact_number'];
        const contactStatus = registerForm.querySelector('[data-contact-check-status]');
        contactFields.forEach(function (name) {
            const input = registerForm.elements.namedItem(name);
            const error = registerForm.querySelector(`[data-contact-error="${name}"]`);
            input.setAttribute('aria-describedby', error.id);
            input.addEventListener('input', function () {
                input.classList.remove('invalid');
                input.removeAttribute('aria-invalid');
                error.hidden = true;
                contactStatus.hidden = true;
            });
        });

        function showStep(index, moveFocus) {
            const previousStep = currentStep;
            if (index !== currentStep) contactCheck?.abort();
            stepAnimation?.cancel();
            currentStep = index;
            registerForm.dataset.registrationStep = String(index);
            steps.forEach(function (step, i) { step.hidden = i !== index; });
            progress.forEach(function (item, i) {
                item.classList.toggle('is-complete', i < index);
                if (i === index) item.setAttribute('aria-current', 'step');
                else item.removeAttribute('aria-current');
            });
            back.hidden = index === 0;
            next.hidden = index === steps.length - 1;
            submit.hidden = index !== steps.length - 1;
            if (moveFocus && index !== previousStep && steps[index].animate
                && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                const direction = index > previousStep ? 1 : -1;
                stepAnimation = steps[index].animate([
                    { opacity: 0, transform: `translateX(${direction * 20}px)` },
                    { opacity: 1, transform: 'translateX(0)' },
                ], { duration: 240, easing: 'cubic-bezier(.2,.75,.2,1)' });
            }
            if (moveFocus) steps[index].querySelector('h2').focus({ preventScroll: true });
        }

        function firstInvalidField(panel) {
            return Array.from(panel.querySelectorAll('input, select, textarea'))
                .find(function (field) { return !field.checkValidity(); });
        }

        async function advanceStep() {
            if (contactCheck) return;
            const invalid = firstInvalidField(steps[currentStep]);
            if (invalid) { invalid.reportValidity(); return; }
            if (currentStep === 1) {
                const controller = new AbortController();
                const nextLabel = next.innerHTML;
                contactCheck = controller;
                const snapshot = ['name', ...contactFields].map(name => registerForm.elements.namedItem(name).value);
                const timeout = window.setTimeout(() => controller.abort(), 15000);
                next.disabled = true;
                next.textContent = 'Checking contact details...';
                steps[1].setAttribute('aria-busy', 'true');
                contactStatus.hidden = true;
                contactFields.forEach(function (name) {
                    registerForm.querySelector(`[data-contact-error="${name}"]`).hidden = true;
                });
                try {
                    const body = new FormData();
                    body.set('_token', registerForm.elements.namedItem('_token').value);
                    ['name', ...contactFields].forEach((name, index) => body.set(name, snapshot[index]));
                    const response = await fetch(registerForm.dataset.contactCheckUrl, {
                        method: 'POST', body, signal: controller.signal, credentials: 'same-origin',
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const result = await response.json().catch(() => ({}));
                    if (currentStep !== 1) return;
                    if (snapshot.some((value, index) => value !== registerForm.elements.namedItem(['name', ...contactFields][index]).value)) {
                        throw new Error('Your contact details changed. Select Next to check them again.');
                    }
                    if (response.status === 422 && result.errors) {
                        let firstError;
                        contactFields.forEach(function (name) {
                            if (!result.errors[name]?.[0]) return;
                            const input = registerForm.elements.namedItem(name);
                            const error = registerForm.querySelector(`[data-contact-error="${name}"]`);
                            error.textContent = result.errors[name][0];
                            error.hidden = false;
                            input.classList.add('invalid');
                            input.setAttribute('aria-invalid', 'true');
                            firstError ||= input;
                        });
                        if (firstError) { firstError.focus(); return; }
                        throw new Error('Review your name in Personal, then check your contact details again.');
                    }
                    if (!response.ok || result.valid !== true) {
                        throw new Error(response.status === 429 ? 'Too many checks. Wait a minute, then select Next again.'
                            : response.status === 419 ? 'Your session expired. Reload the page, then try again.'
                            : 'We could not check your contact details. Select Next to try again.');
                    }
                    showStep(2, true);
                } catch (error) {
                    if (currentStep === 1) {
                        contactStatus.textContent = error.name === 'AbortError' ? 'The check timed out. Select Next to try again.'
                            : error instanceof TypeError ? 'We could not connect. Check your connection, then select Next again.' : error.message;
                        contactStatus.hidden = false;
                    }
                } finally {
                    window.clearTimeout(timeout);
                    contactCheck = null;
                    next.disabled = false;
                    next.innerHTML = nextLabel;
                    steps[1].removeAttribute('aria-busy');
                }
                return;
            }
            if (currentStep < steps.length - 1) showStep(currentStep + 1, true);
        }

        if (steps.length && back && next && submit) {
            // Validate the relevant step before revealing a field that needs attention.
            registerForm.noValidate = true;
            registerForm.classList.add('auth-registration-wizard');
            container.classList.add('registration-wizard-ready');
            registerForm.querySelector('.auth-registration-progress').hidden = false;
            const errorStep = steps.findIndex(function (step) { return step.querySelector('.invalid'); });
            showStep(errorStep < 0 ? 0 : errorStep, false);
            back.addEventListener('click', function () { showStep(currentStep - 1, true); });
            next.addEventListener('click', advanceStep);
            registerForm.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' && !event.isComposing && event.target instanceof HTMLInputElement && currentStep < steps.length - 1) {
                    event.preventDefault();
                    advanceStep();
                }
            });
        }

        function clearPasswordMatchValidity() {
            if (confirmInput) {
                confirmInput.setCustomValidity('');
            }
        }

        function validatePasswordMatch() {
            if (!passwordInput || !confirmInput) {
                return true;
            }

            if (confirmInput.value !== passwordInput.value) {
                confirmInput.setCustomValidity('Passwords do not match.');
                return false;
            }

            confirmInput.setCustomValidity('');
            return true;
        }

        if (passwordInput) {
            passwordInput.addEventListener('input', clearPasswordMatchValidity);
        }

        if (confirmInput) {
            confirmInput.addEventListener('input', clearPasswordMatchValidity);
        }

        registerForm.addEventListener('submit', function (event) {
            if (steps.length && currentStep < steps.length - 1) {
                event.preventDefault();
                advanceStep();
                return;
            }

            validatePasswordMatch();
            const invalid = firstInvalidField(registerForm);
            if (invalid) {
                event.preventDefault();
                const index = steps.findIndex(function (step) { return step.contains(invalid); });
                if (index >= 0) showStep(index, false);
                invalid.reportValidity();
            }
        });
    }

    syncPanelAccessibility(false);
})();
