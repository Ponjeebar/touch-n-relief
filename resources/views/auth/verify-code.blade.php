<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify Email — TouchNRelief</title>
    @include('partials.theme-head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/login.css') }}?v={{ filemtime(public_path('css/login.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}?v={{ filemtime(public_path('css/theme.css')) }}">
</head>
<body class="auth-page auth-reset-page" style="--auth-bg-image: url('{{ asset('images/login/background.jpg') }}');">
    @include('partials.landing-nav', ['navMode' => 'auth'])
    <main class="container auth-reset-card auth-code-card" id="container">
        <div class="form-container sign-in">
            <form method="POST" action="{{ route('verification.verify', $verification) }}">
                @csrf
                <input type="hidden" name="purpose" value="{{ $verification->purpose }}">
                <div class="auth-reset-heading">
                    <span class="auth-reset-icon" aria-hidden="true"><i class="bi bi-envelope-check"></i></span>
                    <div>
                        <span class="auth-reset-eyebrow">Email verification</span>
                        <h1>Enter your code</h1>
                    </div>
                </div>
                <p class="auth-reset-copy">We sent a six digit code to <strong>{{ $verification->email }}</strong>. It expires in 10 minutes.</p>
                @if (session('status'))<p class="field-msg auth-forgot-success">{{ session('status') }}</p>@endif
                <label class="auth-field-label" for="verification-code">Verification code</label>
                <input id="verification-code" class="auth-code-input @error('code') invalid @enderror" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" placeholder="000000" autofocus required>
                @error('code')<p class="field-msg">{{ $message }}</p>@enderror
                <button type="submit">Verify code</button>
                <div class="auth-code-actions">
                    <button type="submit" class="link auth-forgot-back" formaction="{{ route('verification.resend', $verification) }}">Send a new code</button>
                    <a class="link" href="{{ route('login') }}">Back to Sign In</a>
                </div>
            </form>
        </div>
        <div class="toggle-container" aria-hidden="true"><div class="toggle"><div class="toggle-panel toggle-right">
            <img src="{{ asset('images/dashboard/logo.png') }}" alt="" class="auth-toggle-logo" width="220" height="220">
            <h1>Protecting your<br>TouchNRelief account</h1>
            <p>Do not share your verification code with anyone.</p>
        </div></div></div>
    </main>
</body>
</html>
