<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password</title>
    @include('partials.theme-head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/login.css') }}?v={{ filemtime(public_path('css/login.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}?v={{ filemtime(public_path('css/theme.css')) }}">
</head>
<body class="auth-page auth-reset-page" style="--auth-bg-image: url('{{ asset('images/login/background.jpg') }}');">
    @include('partials.landing-nav', ['navMode' => 'auth'])
    <div class="auth-theme-float" role="group" aria-label="Display theme">
        <button type="button" class="auth-theme-btn" id="tnr-theme-light-auth" aria-pressed="true" title="Light mode"><i class="bi bi-sun-fill" aria-hidden="true"></i></button>
        <button type="button" class="auth-theme-btn" id="tnr-theme-dark-auth" aria-pressed="false" title="Dark mode"><i class="bi bi-moon-stars-fill" aria-hidden="true"></i></button>
    </div>

    <main class="container auth-reset-card" id="container">
        <div class="form-container sign-in">
            <form method="POST" action="{{ route('password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div class="auth-reset-heading">
                    <span class="auth-reset-icon" aria-hidden="true"><i class="bi bi-shield-lock"></i></span>
                    <div>
                        <span class="auth-reset-eyebrow">Account security</span>
                        <h1>Choose a new password</h1>
                    </div>
                </div>
                <p class="auth-reset-copy">Create a strong password for your TouchNRelief account.</p>

                <label class="auth-field-label" for="reset-email">Email address</label>
                <input
                    id="reset-email"
                    type="email"
                    name="email"
                    value="{{ old('email', $email) }}"
                    autocomplete="email"
                    class="@error('email') invalid @enderror"
                    readonly
                    required
                >
                @error('email')
                    <p class="field-msg">{{ $message }}</p>
                @enderror

                <label class="auth-field-label" for="reset-password">New password</label>
                <div class="auth-password-wrap">
                    <input
                        id="reset-password"
                        type="password"
                        name="password"
                        placeholder="New Password"
                        autocomplete="new-password"
                        class="@error('password') invalid @enderror"
                        required
                    >
                    <button type="button" class="auth-password-toggle" data-pw-toggle aria-label="Show password" aria-pressed="false">
                        <i class="bi bi-eye" aria-hidden="true"></i>
                    </button>
                </div>
                @error('password')
                    <p class="field-msg">{{ $message }}</p>
                @enderror

                <label class="auth-field-label" for="reset-password-confirmation">Confirm new password</label>
                <div class="auth-password-wrap">
                    <input
                        id="reset-password-confirmation"
                        type="password"
                        name="password_confirmation"
                        placeholder="Confirm Password"
                        autocomplete="new-password"
                        required
                    >
                    <button type="button" class="auth-password-toggle" data-pw-toggle aria-label="Show password" aria-pressed="false">
                        <i class="bi bi-eye" aria-hidden="true"></i>
                    </button>
                </div>

                <p class="auth-reset-hint"><i class="bi bi-check-circle" aria-hidden="true"></i> Use at least 8 characters and avoid a password used on another site.</p>

                <button type="submit">Save new password</button>
                <div class="mobile-toggle" style="display: flex;">
                    <a class="link" href="{{ route('login') }}">Back to Sign In</a>
                </div>
            </form>
        </div>

        <div class="toggle-container" aria-hidden="true">
            <div class="toggle">
                <div class="toggle-panel toggle-right">
                    <div class="toggle-panel-logo-wrap">
                        <img src="{{ asset('images/dashboard/logo.png') }}" alt="" class="auth-toggle-logo" width="240" height="240">
                    </div>
                    <h1>Secure your<br>TouchNRelief account</h1>
                    <p class="auth-reset-panel-copy">After saving, sign in using your new password.</p>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
