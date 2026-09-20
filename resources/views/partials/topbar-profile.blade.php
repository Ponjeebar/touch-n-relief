@once
    <link rel="stylesheet" href="{{ asset('css/profile-app-modal.css') }}">
@endonce
@php
    $u = auth()->user();
    $label = $u->name ?? $u->email;
    $profilePhotoUrl = public_storage_url($u->profile_photo_path);
@endphp
<div class="profile-wrap" data-tnr-profile-wrap>
    <button
        type="button"
        class="profile profile-menu-trigger"
        id="tnr-profile-menu-trigger"
        aria-expanded="false"
        aria-haspopup="menu"
        aria-controls="tnr-profile-dropdown"
    >
        <div class="avatar" aria-hidden="true">
            @if ($profilePhotoUrl)
                <img src="{{ $profilePhotoUrl }}" alt="{{ $label }} profile photo">
            @else
                {{ strtoupper(substr((string) $label, 0, 1)) }}
            @endif
        </div>
        <div class="profile-name">{{ $label }}</div>
        <span class="profile-chevron" aria-hidden="true"><i class="bi bi-chevron-down"></i></span>
    </button>

    <div
        class="profile-dropdown profile-dropdown-hidden"
        id="tnr-profile-dropdown"
        role="menu"
        aria-labelledby="tnr-profile-menu-trigger"
    >
        <button type="button" class="profile-dropdown-item" id="tnr-profile-open-modal" role="menuitem">
            <span class="profile-dropdown-icon" aria-hidden="true"><i class="bi bi-person"></i></span>
            <span>Profile</span>
        </button>
        @if ($u->isAdmin())
            <a href="{{ route('activity-logs.index') }}" class="profile-dropdown-item" role="menuitem">
                <span class="profile-dropdown-icon" aria-hidden="true"><i class="bi bi-clock-history"></i></span>
                <span>Activity Log</span>
            </a>
        @endif
        @if (! $u->isAdmin() && ! $u->isReceptionist())
            <button type="button" class="profile-dropdown-item" data-tnr-open-transactions role="menuitem">
                <span class="profile-dropdown-icon" aria-hidden="true"><i class="bi bi-receipt"></i></span>
                <span>Transactions</span>
            </button>
        @endif
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="profile-dropdown-item profile-dropdown-item-logout" role="menuitem">
                <span class="profile-dropdown-icon" aria-hidden="true"><i class="bi bi-box-arrow-right"></i></span>
                <span>Logout</span>
            </button>
        </form>
    </div>

    @include('partials.profile-edit-modal')
</div>

@include('partials.profile-transactions-modal')
