@auth
    @if (auth()->user()->isUser() && ! auth()->user()->isWalkIn())
        <nav class="customer-mobile-shortcuts" aria-label="Customer mobile shortcuts">
            <a href="{{ route('landing') }}" data-customer-mobile-item="home" @if(request()->routeIs('landing')) aria-current="page" @endif>
                <span class="customer-mobile-shortcut-icon"><i class="bi bi-house-door" aria-hidden="true"></i></span>
                <span class="customer-mobile-shortcut-label">Home</span>
            </a>
            <a href="{{ route('landing').'#services' }}" data-customer-mobile-item="services" data-customer-tour="services">
                <span class="customer-mobile-shortcut-icon"><i class="bi bi-grid" aria-hidden="true"></i></span>
                <span class="customer-mobile-shortcut-label">Services</span>
            </a>
            <a href="{{ route('booking.index') }}" data-customer-mobile-item="book" data-customer-tour="book" @if(request()->routeIs('booking.index')) aria-current="page" @endif>
                <span class="customer-mobile-shortcut-icon"><i class="bi bi-calendar-plus" aria-hidden="true"></i></span>
                <span class="customer-mobile-shortcut-label">Book</span>
            </a>
            <button type="button" data-customer-mobile-item="appointments" data-tnr-open-transactions data-customer-tour="appointments" aria-label="View my appointments" aria-controls="tnr-transactions-modal" aria-expanded="false">
                <span class="customer-mobile-shortcut-icon"><i class="bi bi-calendar2-check" aria-hidden="true"></i></span>
                <span class="customer-mobile-shortcut-label">Appointments</span>
            </button>
            <a href="{{ route('profile.edit') }}" data-customer-mobile-item="profile" data-customer-tour="profile" @if(request()->routeIs('profile.edit')) aria-current="page" @endif>
                <span class="customer-mobile-shortcut-icon"><i class="bi bi-person" aria-hidden="true"></i></span>
                <span class="customer-mobile-shortcut-label">Profile</span>
            </a>
        </nav>
    @endif
@endauth
