<div class="nav-user-wrap {{ $navUserWrapperClass ?? '' }}" data-user-menu>
    <button type="button" class="nav-user" title="{{ $user?->name }}" aria-haspopup="menu" aria-expanded="false">
        <span class="nav-avatar" aria-hidden="true">
            @if ($profilePhotoUrl)
                <img src="{{ $profilePhotoUrl }}" alt="{{ $user?->name ?: 'User' }} profile photo" onerror="this.remove(); this.parentElement.textContent='{{ $initials }}';">
            @else
                {{ $initials }}
            @endif
        </span>
        <span class="nav-username">{{ $user?->name ?: 'User' }}</span>
        <span class="nav-caret" aria-hidden="true">&#9662;</span>
    </button>
    <div class="nav-user-menu" role="menu">
        @if ($user->isAdmin())
            <a role="menuitem" href="{{ route('dashboard') }}">Dashboard</a>
            <button type="button" role="menuitem" class="nav-user-menu-btn" data-open-profile-modal>Edit Profile</button>
        @else
            <a role="menuitem" href="{{ route('profile.edit') }}">Edit Profile</a>
        @endif
        @if (! $user->isAdmin() && ! $user->isReceptionist())
            <button type="button" role="menuitem" class="nav-user-menu-btn" data-tnr-open-transactions>Transactions</button>
        @endif
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" role="menuitem" class="nav-user-logout">Logout</button>
        </form>
    </div>
</div>
