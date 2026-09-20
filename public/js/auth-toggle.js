(function () {
    const container = document.getElementById('container');
    const registerBtn = document.getElementById('register');
    const loginBtn = document.getElementById('login');

    if (!container) {
        return;
    }

    function setPanel(showRegister) {
        if (showRegister) {
            container.classList.add('active');
        } else {
            container.classList.remove('active');
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
