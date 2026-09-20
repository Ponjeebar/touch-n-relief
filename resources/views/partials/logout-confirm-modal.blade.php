@once
    <link rel="stylesheet" href="{{ asset('css/logout-confirm.css') }}">
@endonce

<div class="lo-modal lo-hidden" id="tnr-logout-confirm-modal" role="dialog" aria-modal="true" aria-labelledby="tnr-logout-confirm-title" aria-hidden="true">
    <div class="lo-modal-backdrop" data-lo-close="true"></div>
    <div class="lo-dialog" role="document">
        <div class="lo-icon" aria-hidden="true">
            <i class="bi bi-box-arrow-right"></i>
        </div>
        <h2 class="lo-title" id="tnr-logout-confirm-title">Log out?</h2>
        <p class="lo-text">Are you sure you want to log out? You will need to sign in again to access your account.</p>
        <div class="lo-actions">
            <button type="button" class="lo-btn lo-btn-cancel" data-lo-close="true">Stay signed in</button>
            <button type="button" class="lo-btn lo-btn-confirm" data-lo-confirm="true">Yes, log out</button>
        </div>
    </div>
</div>

@once
    <script src="{{ asset('js/logout-confirm.js') }}" defer></script>
@endonce
