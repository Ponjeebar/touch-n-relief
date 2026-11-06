<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $verification->purpose === \App\Models\AuthVerificationCode::PURPOSE_PASSWORD_RESET ? 'Verify recovery code' : 'Verify email' }} — TouchNRelief</title>
    @include('partials.theme-head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/login.css') }}?v={{ filemtime(public_path('css/login.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}?v={{ filemtime(public_path('css/theme.css')) }}">
</head>
<body class="auth-page auth-entry-page auth-reset-page auth-code-page">
    @include('partials.landing-nav', ['navMode' => 'auth', 'authEntryHeader' => true])
    <main class="container auth-reset-card auth-code-card" id="container">
        <div class="form-container sign-in">
            <form method="POST" action="{{ $verificationSubmitUrl ?? route('verification.verify', $verification) }}" @if($verification->purpose === \App\Models\AuthVerificationCode::PURPOSE_PASSWORD_RESET) data-recovery-code @endif>
                @csrf
                <input type="hidden" name="purpose" value="{{ $verification->purpose }}">
                <div class="auth-reset-heading">
                    <span class="auth-reset-icon" aria-hidden="true"><i class="bi bi-envelope-check"></i></span>
                    <div>
                        <span class="auth-reset-eyebrow">{{ $verification->purpose === \App\Models\AuthVerificationCode::PURPOSE_PASSWORD_RESET ? 'Password recovery' : 'Email verification' }}</span>
                        <h1>Enter your code</h1>
                    </div>
                </div>
                <p class="auth-reset-copy">We sent a six-digit code to <strong class="auth-code-email">{{ $verification->email }}</strong><span class="auth-code-expiry">The code expires in 10 minutes.</span></p>
                @if (session('status'))<p class="field-msg auth-forgot-success">{{ session('status') }}</p>@endif
                <label class="auth-field-label" for="verification-code" data-code-fallback-label>Verification code</label>
                <input id="verification-code" class="auth-code-input @error('code') invalid @enderror" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" placeholder="000000" autofocus required>
                <fieldset class="auth-code-digits" data-code-digits hidden aria-describedby="verification-code-help @error('code') verification-code-error @enderror">
                    <legend>Verification code</legend>
                    <div class="auth-code-digit-row">
                        @for ($digit = 1; $digit <= 6; $digit++)
                            <input type="text" inputmode="numeric" maxlength="1" pattern="[0-9]" autocomplete="{{ $digit === 1 ? 'one-time-code' : 'off' }}" aria-label="Digit {{ $digit }} of 6" @error('code') aria-invalid="true" @enderror>
                        @endfor
                    </div>
                </fieldset>
                <p class="auth-code-help" id="verification-code-help">Enter the six digits from your email. You can also paste the full code.</p>
                <p class="auth-code-status" data-code-status role="status" aria-live="polite" hidden></p>
                <p class="field-msg" id="verification-code-error" role="alert" @if(!$errors->has('code')) hidden @endif>{{ $errors->first('code') }}</p>
                <button type="submit" data-code-submit>Verify code</button>
                <p class="auth-reset-hint">Do not share your verification code with anyone.</p>
                <div class="auth-code-actions">
                    <button type="submit" class="link auth-forgot-back" formnovalidate formaction="{{ $verificationResendUrl ?? route('verification.resend', $verification) }}">Send a new code</button>
                    <a class="link" href="{{ $verificationBackUrl ?? route('login') }}">{{ $verificationBackLabel ?? 'Back to Sign In' }}</a>
                </div>
            </form>
        </div>

        <div class="toggle-container" aria-hidden="true"><div class="toggle"><div class="toggle-panel toggle-right">
            <img src="{{ asset('images/dashboard/logo.png') }}" alt="" class="auth-toggle-logo" width="220" height="220">
            <h1>Protecting your<br>TouchNRelief account</h1>
            <p>Do not share your verification code with anyone.</p>
        </div></div></div>
    </main>
    <script src="{{ asset('js/verification-code.js') }}?v={{ filemtime(public_path('js/verification-code.js')) }}" defer></script>
</body>
</html>
