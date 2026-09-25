@php
    $socialIntent = $socialIntent ?? 'login';
    $socialAction = $socialIntent === 'signup' ? 'Sign up' : 'Sign in';
    $providers = [
        'google' => ['label' => 'Google'],
        'facebook' => ['label' => 'Facebook'],
    ];
@endphp
<div class="auth-social auth-social-{{ $socialIntent }}" aria-label="{{ $socialAction }} with a social account">
    <div class="auth-social-buttons">
        @foreach ($providers as $provider => $details)
            @php
                $configured = filled(config("services.$provider.client_id"))
                    && filled(config("services.$provider.client_secret"));
            @endphp
            @if ($configured)
                <a class="auth-social-button auth-social-button-{{ $provider }}" href="{{ route('social.redirect', ['provider' => $provider, 'intent' => $socialIntent]) }}" aria-label="{{ $socialAction }} with {{ $details['label'] }}">
                    @include('auth.partials.social-provider-icon', ['provider' => $provider])
                    <span>{{ $socialAction }} with {{ $details['label'] }}</span>
                </a>
            @else
                <span class="auth-social-button auth-social-button-{{ $provider }} is-disabled" aria-disabled="true" title="{{ $details['label'] }} login is not configured yet">
                    @include('auth.partials.social-provider-icon', ['provider' => $provider])
                    <span>{{ $socialAction }} with {{ $details['label'] }}</span>
                </span>
            @endif
        @endforeach
    </div>
    <div class="auth-divider"><span>or use email</span></div>
</div>
