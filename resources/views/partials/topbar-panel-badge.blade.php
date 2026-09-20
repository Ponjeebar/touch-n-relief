@if (auth()->user()->isAdmin())
    <div class="top-admin-badge" aria-label="Admin Panel">
        <span class="top-admin-icon"><i class="bi bi-shield-lock" aria-hidden="true"></i></span>
        <span class="top-admin-text">Admin Panel</span>
    </div>
@else
    <div class="top-admin-badge" aria-label="Receptionist Panel">
        <span class="top-admin-icon"><i class="bi bi-headset" aria-hidden="true"></i></span>
        <span class="top-admin-text">Receptionist Panel</span>
    </div>
@endif
