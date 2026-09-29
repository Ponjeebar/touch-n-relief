@auth
    @if (auth()->user()->isUser() && ! auth()->user()->isWalkIn())
        <nav class="customer-mobile-shortcuts" aria-label="Customer mobile shortcuts">
            <a href="{{ route('landing') }}" @if(request()->routeIs('landing')) aria-current="page" @endif>
                <i class="bi bi-house-door" aria-hidden="true"></i><span>Home</span>
            </a>
            <a href="{{ route('landing').'#services' }}" data-customer-tour="services">
                <i class="bi bi-grid" aria-hidden="true"></i><span>Services</span>
            </a>
            <a href="{{ route('booking.index') }}" data-customer-tour="book" @if(request()->routeIs('booking.index')) aria-current="page" @endif>
                <i class="bi bi-calendar-plus" aria-hidden="true"></i><span>Book</span>
            </a>
            <button type="button" data-tnr-open-transactions data-customer-tour="appointments" aria-label="View my appointments">
                <i class="bi bi-calendar2-check" aria-hidden="true"></i><span>Appointments</span>
            </button>
            <a href="{{ route('profile.edit') }}" data-customer-tour="profile" @if(request()->routeIs('profile.edit')) aria-current="page" @endif>
                <i class="bi bi-person-circle" aria-hidden="true"></i><span>Profile</span>
            </a>
        </nav>
    @endif
@endauth
