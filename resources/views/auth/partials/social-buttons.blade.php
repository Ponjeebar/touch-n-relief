@php
    $socialIntent = $socialIntent ?? 'login';
    $socialAction = $socialIntent === 'signup' ? 'Sign up' : 'Sign in';
    $providers = [
        'google' => ['label' => 'Google', 'icon' => 'bi-google'],
        'facebook' => ['label' => 'Facebook', 'icon' => 'bi-facebook'],
    ];
@endphp
<div class="auth-social" aria-label="{{ $socialAction }} with a social account">
    <div class="auth-social-buttons">
        @foreach ($providers as $provider => $details)
            @php
                $configured = filled(config("services.$provider.client_id"))
                    && filled(config("services.$provider.client_secret"));
            @endphp
            @if ($configured)
                <a class="auth-social-button auth-social-button-{{ $provider }}" href="{{ route('social.redirect', ['provider' => $provider, 'intent' => $socialIntent]) }}">
                    <i class="bi {{ $details['icon'] }}" aria-hidden="true"></i>
                    <span>{{ $socialAction }} with {{ $details['label'] }}</span>
                </a>
            @else
                <span class="auth-social-button is-disabled" aria-disabled="true" title="{{ $details['label'] }} login is not configured yet">
                    <i class="bi {{ $details['icon'] }}" aria-hidden="true"></i>
                    <span>{{ $socialAction }} with {{ $details['label'] }}</span>
                </span>
            @endif
        @endforeach
    </div>
    <div class="auth-divider"><span>or use email</span></div>
</div>
