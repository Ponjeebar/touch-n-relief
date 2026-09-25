@php
    $navMode = $navMode ?? 'full';
    $onLanding = request()->routeIs('landing');
    $user = auth()->user();
    $source = trim($user?->name ?: $user?->email ?: 'User');
    $profilePhotoUrl = public_storage_url($user?->profile_photo_path);
    $initials = collect(preg_split('/\s+/', $source) ?: [])
        ->filter()
        ->take(2)
        ->map(fn (string $part) => strtoupper(substr($part, 0, 1)))
        ->implode('') ?: 'U';
@endphp
<header class="landing-header {{ ($navMode === 'auth' || ($solidNav ?? false)) ? 'nav-solid' : '' }} {{ ($navMode === 'auth') ? 'auth-page-header' : '' }}">
    <div class="nav-container">
        <div class="nav-shell">
            <a href="{{ $onLanding ? '#top' : route('landing') }}" class="brand-wrap brand-link">
                <img src="{{ asset('images/dashboard/logo.png') }}" alt="Buenos Touche logo" class="brand-logo">
                <div class="brand">TouchNRelief</div>
            </a>
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
                        @include('partials.landing-user-menu', ['navUserWrapperClass' => 'nav-user-wrap--mobile'])
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
            <div class="nav-header-actions">
                @if ($navMode === 'full')
                    @auth
                        @if ($user->isUser() && ! $user->isWalkIn())
                            @include('partials.customer-notifications')
                        @endif
                        @include('partials.landing-user-menu', ['navUserWrapperClass' => 'nav-user-wrap--desktop'])
                    @endauth
                @endif
                <button type="button" class="mobile-nav-toggle" aria-controls="public-mobile-navigation" aria-expanded="false" aria-label="Open navigation menu">
                    <span class="mobile-nav-icon" aria-hidden="true"></span>
                    <span>Menu</span>
                </button>
            </div>
        </div>
    </div>
</header>
@include('partials.customer-mobile-nav')
@once
    <script src="{{ asset('js/mobile-navigation.js') }}" defer></script>
@endonce
