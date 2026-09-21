(function () {
    const container = document.getElementById('container');
    const registerBtn = document.getElementById('register');
    const loginBtn = document.getElementById('login');

    if (!container) {
        return;
    }

    let switching = false;

    async function setPanel(showRegister) {
        if (switching || container.classList.contains('active') === showRegister) return;

        const mobile = window.matchMedia('(max-width: 700px)').matches;
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const outgoing = container.querySelector(showRegister ? '.sign-in form' : '.sign-up form');
        const incoming = container.querySelector(showRegister ? '.sign-up form' : '.sign-in .auth-login-panel');
        let exitAnimation;

        if (mobile && !reduceMotion && outgoing?.animate && incoming?.animate) {
            switching = true;
            const direction = showRegister ? -1 : 1;
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
            incoming.closest('.form-container')?.querySelector('h1')?.focus({ preventScroll: true });
        }
    }

    if (registerBtn) {
        registerBtn.addEventListener('click', function () {
            setPanel(true);
            container.classList.remove('sign-in-forgot-active');
        });
    }

    if (loginBtn) {
        loginBtn.addEventListener('click', function () {
            setPanel(false);
            container.classList.remove('sign-in-forgot-active');
        });
    }

    document.querySelectorAll('[data-auth-panel]').forEach(function (el) {
        el.addEventListener('click', function () {
            setPanel(el.getAttribute('data-auth-panel') === 'register');
            container.classList.remove('sign-in-forgot-active');
        });
    });

    var forgotOpenBtn = document.querySelector('[data-auth-forgot-open="true"]');
    var forgotCloseBtn = document.querySelector('[data-auth-forgot-close="true"]');

    forgotOpenBtn?.addEventListener('click', function (event) {
        event.preventDefault();
        container.classList.add('sign-in-forgot-active');
    });

    forgotCloseBtn?.addEventListener('click', function () {
        window.location.assign(forgotCloseBtn.getAttribute('data-login-url') || '/login');
    });

    var registerForm = document.querySelector('[data-auth-register-form="true"]');
    if (registerForm) {
        var passwordInput = registerForm.querySelector('input[name="password"]');
        var confirmInput = registerForm.querySelector('input[name="password_confirmation"]');

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
            if (!validatePasswordMatch()) {
                event.preventDefault();
                confirmInput.reportValidity();
                return;
            }

            if (!registerForm.checkValidity()) {
                event.preventDefault();
                registerForm.reportValidity();
            }
        });
    }
})();
