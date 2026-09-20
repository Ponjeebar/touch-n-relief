@if (session('booking_confirmed'))
    <script>
        (function () {
            var successModal = document.getElementById('bkSuccessModal');

            function closeSuccessModal() {
                if (!successModal) return;
                successModal.classList.add('bk-hidden');
                successModal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('bk-modal-open');
            }

            function openSuccessModal() {
                if (!successModal) return;
                successModal.classList.remove('bk-hidden');
                successModal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('bk-modal-open');
                successModal.querySelector('.bk-confirm')?.focus();
            }

            document.querySelectorAll('[data-bk-success-close]').forEach(function (el) {
                el.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeSuccessModal();
                });
            });

            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Escape') return;
                if (successModal && !successModal.classList.contains('bk-hidden')) {
                    closeSuccessModal();
                }
            });

            if (successModal) {
                openSuccessModal();
            }
        })();
    </script>
@endif
