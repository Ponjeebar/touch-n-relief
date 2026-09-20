@once
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.__tnrProfileEditModalBound) return;
        window.__tnrProfileEditModalBound = true;

        var profileModal = document.getElementById('tnr-my-profile-modal');
        if (!profileModal) return;

        function closeLandingUserMenu() {
            var userMenuWrap = document.querySelector('[data-user-menu]');
            if (!userMenuWrap) return;
            userMenuWrap.classList.remove('open');
            var btn = userMenuWrap.querySelector('.nav-user');
            btn?.setAttribute('aria-expanded', 'false');
        }

        function closeProfileDropdown() {
            var profileDropdown = document.getElementById('tnr-profile-dropdown');
            var trigger = document.getElementById('tnr-profile-menu-trigger');
            var profileWrap = document.querySelector('[data-tnr-profile-wrap]');
            if (!profileDropdown) return;
            profileDropdown.classList.add('profile-dropdown-hidden');
            trigger?.setAttribute('aria-expanded', 'false');
            profileWrap?.classList.remove('is-menu-open');
        }

        function closeProfileModal() {
            profileModal.classList.add('mp-hidden');
            document.body.classList.remove('modal-open');
        }

        function openProfileModal() {
            profileModal.classList.remove('mp-hidden');
            document.body.classList.add('modal-open');
        }

        document.querySelectorAll('#tnr-profile-open-modal, #landing-profile-open-modal, [data-open-profile-modal]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                closeProfileDropdown();
                closeLandingUserMenu();
                openProfileModal();
            });
        });

        document.querySelectorAll('[data-mp-close]').forEach(function (el) {
            el.addEventListener('click', function () {
                closeProfileModal();
            });
        });

        document.addEventListener('click', function (e) {
            if (profileModal.classList.contains('mp-hidden')) return;
            if (e.target.closest('#tnr-my-profile-modal')) return;
            closeProfileModal();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            if (!profileModal.classList.contains('mp-hidden')) {
                closeProfileModal();
            }
        });

        var pwdBlock = document.getElementById('mp-password-block');
        var pwdToggle = document.getElementById('mp-change-password-toggle');
        pwdToggle?.addEventListener('click', function () {
            pwdBlock?.classList.toggle('is-open');
            var open = pwdBlock?.classList.contains('is-open');
            pwdToggle?.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        var photoEditBtn = document.querySelector('.mp-avatar-edit');
        var photoInput = document.getElementById('mp-profile-photo-input');
        var photoPreview = document.getElementById('mp-profile-avatar-preview');
        var photoFallback = document.getElementById('mp-profile-avatar-fallback');

        photoEditBtn?.addEventListener('click', function () {
            photoInput?.click();
        });

        photoInput?.addEventListener('change', function () {
            var f = photoInput.files && photoInput.files[0];
            if (!f || !photoPreview) return;

            var url = URL.createObjectURL(f);
            photoPreview.src = url;
            photoPreview.classList.remove('mp-hidden-preview');
            photoFallback?.setAttribute('style', 'display:none;');
        });

        var birthdayInput = document.getElementById('mp-receptionist-birthday');
        var ageInput = document.getElementById('mp-receptionist-age');

        if (birthdayInput && ageInput) {
            var updateAge = function () {
                if (!birthdayInput.value) {
                    ageInput.value = '';
                    return;
                }

                var birthDate = new Date(birthdayInput.value + 'T00:00:00');
                if (Number.isNaN(birthDate.getTime())) {
                    ageInput.value = '';
                    return;
                }

                var today = new Date();
                var age = today.getFullYear() - birthDate.getFullYear();
                var monthDiff = today.getMonth() - birthDate.getMonth();
                var dayDiff = today.getDate() - birthDate.getDate();

                if (monthDiff < 0 || (monthDiff === 0 && dayDiff < 0)) {
                    age -= 1;
                }

                ageInput.value = age >= 0 ? String(age) : '';
            };

            birthdayInput.addEventListener('change', updateAge);
            birthdayInput.addEventListener('input', updateAge);
            updateAge();
        }

        @if ($errors->profile->any())
            openProfileModal();
        @endif

        @if (session('status') && request()->routeIs('landing'))
            var statusMsg = @json(session('status'));
            if (statusMsg) {
                var toast = document.createElement('div');
                toast.className = 'mp-status-toast';
                toast.setAttribute('role', 'status');
                toast.innerHTML = '<i class="bi bi-check-circle-fill" aria-hidden="true"></i><span></span><button type="button" class="mp-status-toast-close" aria-label="Close">&times;</button>';
                toast.querySelector('span').textContent = statusMsg;
                document.body.appendChild(toast);
                var hideToast = function () { toast.classList.add('hidden'); };
                toast.querySelector('.mp-status-toast-close')?.addEventListener('click', hideToast);
                window.setTimeout(hideToast, 3200);
            }
        @endif
    });
</script>
@endonce
