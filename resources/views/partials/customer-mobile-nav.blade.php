@auth
    @if (auth()->user()->isUser() && ! auth()->user()->isWalkIn())
        <nav class="customer-mobile-shortcuts" aria-label="Customer mobile shortcuts">
            <a href="{{ route('landing') }}" @if(request()->routeIs('landing')) aria-current="page" @endif>
                <i class="bi bi-house-door" aria-hidden="true"></i><span>Home</span>
            </a>
            <a href="{{ route('landing').'#services' }}">
                <i class="bi bi-grid" aria-hidden="true"></i><span>Services</span>
            </a>
            <a href="{{ route('booking.index') }}" @if(request()->routeIs('booking.index')) aria-current="page" @endif>
                <i class="bi bi-calendar-plus" aria-hidden="true"></i><span>Book</span>
            </a>
            <button type="button" data-tnr-open-transactions aria-label="View transactions">
                <i class="bi bi-receipt" aria-hidden="true"></i><span>Bookings</span>
            </button>
            <a href="{{ route('profile.edit') }}" @if(request()->routeIs('profile.edit')) aria-current="page" @endif>
                <i class="bi bi-person-circle" aria-hidden="true"></i><span>Profile</span>
            </a>
        </nav>
    @endif
@endauth
