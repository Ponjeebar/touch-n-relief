@php
    $navMode = $navMode ?? 'full';
    $onLanding = request()->routeIs('landing');
@endphp
<header class="landing-header {{ ($navMode === 'auth' || ($solidNav ?? false)) ? 'nav-solid' : '' }} {{ ($navMode === 'auth') ? 'auth-page-header' : '' }}">
    <div class="nav-container">
        <div class="nav-shell">
            <a href="{{ $onLanding ? '#top' : route('landing') }}" class="brand-wrap brand-link">
                <img src="{{ asset('images/dashboard/logo.png') }}" alt="Buenos Touche logo" class="brand-logo">
                <div class="brand">TouchNRelief</div>
            </a>
            <button type="button" class="mobile-nav-toggle" aria-controls="public-mobile-navigation" aria-expanded="false" aria-label="Open navigation menu">
                <span class="mobile-nav-icon" aria-hidden="true"></span>
                <span>Menu</span>
            </button>
            <nav class="nav-links" id="public-mobile-navigation">
                @if ($navMode === 'full')
                    <a href="{{ $onLanding ? '#services' : route('landing').'#services' }}">Services</a>
                    <a href="{{ $onLanding ? '#therapists' : route('landing').'#therapists' }}">Therapists</a>
                    <a href="{{ $onLanding ? '#about' : route('landing').'#about' }}">About</a>
                    <a href="{{ $onLanding ? '#contact' : route('landing').'#contact' }}">Contact</a>
                @elseif ($navMode === 'booking')
                    <a href="{{ route('landing') }}">Home</a>
                    <a href="{{ route('profile.edit') }}">Profile</a>
                    <button type="button" class="nav-txn-link" data-tnr-open-transactions>Transactions</button>
                    @include('partials.customer-notifications')
                @else
                    <a href="{{ route('landing') }}">Home</a>
                @endif
                @if ($navMode === 'full')
                    @auth
                        @php
                            $user = auth()->user();
                            $source = trim($user?->name ?: $user?->email ?: 'User');
                            $profilePhotoUrl = public_storage_url($user?->profile_photo_path);
                            $initials = collect(preg_split('/\s+/', $source) ?: [])
                                ->filter()
                                ->take(2)
                                ->map(fn (string $part) => strtoupper(substr($part, 0, 1)))
                                ->implode('');
                            $initials = $initials ?: 'U';
                        @endphp
                        @if ($user->isUser() && ! $user->isWalkIn())
                            @include('partials.customer-notifications')
                        @endif
                        <div class="nav-user-wrap" data-user-menu>
                            <button type="button" class="nav-user" title="{{ $user?->name }}" aria-haspopup="menu" aria-expanded="false">
                                <span class="nav-avatar" aria-hidden="true">
                                    @if ($profilePhotoUrl)
                                        <img
                                            src="{{ $profilePhotoUrl }}"
                                            alt="{{ $user?->name ?: 'User' }} profile photo"
                                            onerror="this.remove(); this.parentElement.textContent='{{ $initials }}';"
                                        >
                                    @else
                                        {{ $initials }}
                                    @endif
                                </span>
                                <span class="nav-username">{{ $user?->name ?: 'User' }}</span>
                                <span class="nav-caret" aria-hidden="true">▾</span>
                            </button>
                            <div class="nav-user-menu" role="menu">
                                @if (auth()->user()->isAdmin())
                                    <a role="menuitem" href="{{ route('dashboard') }}">Dashboard</a>
                                @endif
                                @if (auth()->user()->isAdmin())
                                    <button type="button" role="menuitem" class="nav-user-menu-btn" id="landing-profile-open-modal">Edit Profile</button>
                                @else
                                    <a role="menuitem" href="{{ route('profile.edit') }}">Edit Profile</a>
                                @endif
                                @if (! auth()->user()->isAdmin() && ! auth()->user()->isReceptionist())
                                    <button type="button" role="menuitem" class="nav-user-menu-btn" data-tnr-open-transactions>Transactions</button>
                                @endif
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" role="menuitem" class="nav-user-logout">Logout</button>
                                </form>
                            </div>
                        </div>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-outline">Log In</a>
                    @endauth
                @elseif ($navMode === 'booking')
                    <form method="POST" action="{{ route('logout') }}" style="margin:0">
                        @csrf
                        <button type="submit" class="btn btn-outline" style="cursor:pointer">Logout</button>
                    </form>
                @endif
            </nav>
        </div>
    </div>
</header>
@once
    <script src="{{ asset('js/mobile-navigation.js') }}" defer></script>
@endonce
