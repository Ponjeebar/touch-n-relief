@php
    $navMode = $navMode ?? 'full';
    $hideHeader = $hideHeader ?? false;
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
@unless ($hideHeader)
<header class="landing-header {{ $navMode !== 'auth' ? 'nav-wide' : '' }} {{ ($navMode === 'auth' || ($solidNav ?? false)) ? 'nav-solid' : '' }} {{ ($navMode === 'auth') ? 'auth-page-header' : '' }}">
    <div class="nav-container">
        <div class="nav-shell">
            <a href="{{ $onLanding ? '#top' : route('landing') }}" class="brand-wrap brand-link">
                <img src="{{ asset('images/dashboard/logo.png') }}" alt="Buenos Touche logo" class="brand-logo">
                <div class="brand">TouchNRelief</div>
            </a>
            @if ($navMode !== 'auth')
                <nav class="nav-links" id="public-mobile-navigation">
                @if ($navMode === 'full')
                    <a href="{{ $onLanding ? '#services' : route('landing').'#services' }}" class="landing-section-link" data-landing-section="services"><span>Services</span></a>
                    <a href="{{ $onLanding ? '#therapists' : route('landing').'#therapists' }}" class="landing-section-link" data-landing-section="therapists"><span>Therapists</span></a>
                    <a href="{{ $onLanding ? '#about' : route('landing').'#about' }}" class="landing-section-link" data-landing-section="about"><span>About</span></a>
                    <a href="{{ $onLanding ? '#contact' : route('landing').'#contact' }}" class="landing-section-link" data-landing-section="contact"><span>Contact</span></a>
                @elseif (in_array($navMode, ['booking', 'profile'], true))
                    <a href="{{ route('landing') }}">Home</a>
                @endif
                @if (in_array($navMode, ['full', 'booking', 'profile'], true))
                    @auth
                        @include('partials.landing-user-menu', ['navUserWrapperClass' => 'nav-user-wrap--mobile'])
                    @else
                        <div class="nav-guest-actions nav-guest-actions--mobile">
                            <a href="{{ route('booking.index') }}" class="nav-guest-action nav-book-now">BOOK NOW</a>
                            <a href="{{ route('login') }}" class="nav-guest-action nav-login">Log In</a>
                        </div>
                    @endauth
                @endif
                </nav>
            @endif
            <div class="nav-header-actions">
                @if (in_array($navMode, ['full', 'booking', 'profile'], true))
                    @auth
                        @if ($user->isUser() && ! $user->isWalkIn())
                            <button type="button" class="nav-appointments-direct" data-tnr-open-transactions>My Appointments</button>
                            @include('partials.customer-notifications')
                        @endif
                        @include('partials.landing-user-menu', ['navUserWrapperClass' => 'nav-user-wrap--desktop'])
                    @else
                        <div class="nav-guest-actions nav-guest-actions--desktop">
                            <a href="{{ route('booking.index') }}" class="nav-guest-action nav-book-now">BOOK NOW</a>
                            <a href="{{ route('login') }}" class="nav-guest-action nav-login">Log In</a>
                        </div>
                    @endauth
                @endif
                @if ($navMode !== 'auth')
                    <button type="button" class="mobile-nav-toggle" aria-controls="public-mobile-navigation" aria-expanded="false" aria-label="Open navigation menu">
                        <span class="mobile-nav-icon" aria-hidden="true"></span>
                        <span>Menu</span>
                    </button>
                @endif
            </div>
        </div>
    </div>
</header>
@endunless
@include('partials.customer-mobile-nav')
@once
    <script src="{{ asset('js/mobile-navigation.js') }}?v={{ filemtime(public_path('js/mobile-navigation.js')) }}" defer></script>
    <script src="{{ asset('js/landing-theme.js') }}?v={{ filemtime(public_path('js/landing-theme.js')) }}" defer></script>
@endonce
