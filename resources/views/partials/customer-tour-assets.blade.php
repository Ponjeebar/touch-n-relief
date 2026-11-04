@php
    $tourUser = auth()->user();
    $tourRole = $tourUser?->isAdmin() ? 'admin' : ($tourUser?->isReceptionist() ? 'receptionist' : 'customer');
    $tourAllowed = $tourUser?->isAdmin() || $tourUser?->isReceptionist() || ($tourUser?->isUser() && ! $tourUser->isWalkIn());

    if (! $tourAllowed) {
        return;
    }

    $autoStartCustomerTour = (bool) session()->pull('customer_tour_pending', false);
    $autoStartReceptionistTour = $tourRole === 'receptionist'
        && (bool) session('receptionist_tour_enabled', false);
    $tourPage = $tourPage ?? Route::currentRouteName() ?? 'page';
    $autoStartTour = $autoStartCustomerTour || $autoStartReceptionistTour;
    $tourSettings = app(\App\Services\SiteSettingsService::class);
@endphp
@once
<link rel="stylesheet" href="{{ asset('vendor/driver/driver.css') }}?v=1.8.0">
<link rel="stylesheet" href="{{ asset('css/customer-tour.css') }}?v={{ filemtime(public_path('css/customer-tour.css')) }}">
<script
    id="tnr-customer-tour-config"
    src="{{ asset('vendor/driver/driver.js.iife.js') }}?v=1.8.0"
    data-customer-tour-user="{{ $tourUser->getKey() }}"
    data-customer-tour-role="{{ $tourRole }}"
    data-customer-tour-page="{{ $tourPage }}"
    data-customer-tour-auto-start="{{ $autoStartTour ? '1' : '0' }}"
    data-payment-hold-minutes="{{ $tourSettings->paymentHoldMinutes() }}"
    data-late-grace-minutes="{{ $tourSettings->lateGraceMinutes() }}"
    defer
></script>
<script src="{{ asset('js/customer-tour.js') }}?v={{ filemtime(public_path('js/customer-tour.js')) }}" defer></script>
@endonce
