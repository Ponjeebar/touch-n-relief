@php
    $tourUser = auth()->user();
    $tourRole = $tourUser?->isReceptionist() ? 'receptionist' : 'customer';
    $tourAllowed = $tourUser?->isReceptionist() || ($tourUser?->isUser() && ! $tourUser->isWalkIn());

    if (! $tourAllowed) {
        return;
    }

    $autoStartCustomerTour = (bool) session()->pull('customer_tour_pending', false);
    $tourPage = $tourPage ?? Route::currentRouteName() ?? 'page';
    $autoStartTour = $autoStartCustomerTour || ($tourRole === 'receptionist' && $tourPage === 'receptionist.dashboard');
@endphp
@once
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/driver.js@1.8.0/dist/driver.css">
<link rel="stylesheet" href="{{ asset('css/customer-tour.css') }}?v={{ filemtime(public_path('css/customer-tour.css')) }}">
<script
    id="tnr-customer-tour-config"
    src="https://cdn.jsdelivr.net/npm/driver.js@1.8.0/dist/driver.js.iife.js"
    data-customer-tour-user="{{ $tourUser->getKey() }}"
    data-customer-tour-role="{{ $tourRole }}"
    data-customer-tour-page="{{ $tourPage }}"
    data-customer-tour-auto-start="{{ $autoStartTour ? '1' : '0' }}"
    defer
></script>
<script src="{{ asset('js/customer-tour.js') }}?v={{ filemtime(public_path('js/customer-tour.js')) }}" defer></script>
@endonce
