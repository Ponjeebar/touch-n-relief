@if (session('status'))
    <div class="toast-success" id="status-toast" role="status" aria-live="polite">
        <i class="bi bi-check-circle-fill"></i>
        <span>{{ session('status') }}</span>
        <button type="button" class="toast-close" aria-label="Close">&times;</button>
    </div>
    <script>
        (function () {
            const toast = document.getElementById('status-toast');
            if (!toast) return;
            const hide = () => toast.classList.add('hidden');
            toast.querySelector('.toast-close')?.addEventListener('click', hide);
            window.setTimeout(hide, 3500);
        })();
    </script>
@endif
