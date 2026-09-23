<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password</title>
    @include('partials.theme-head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}?v={{ filemtime(public_path('css/theme.css')) }}">
</head>
<body class="auth-page" style="--auth-bg-image: url('{{ asset('images/login/background.jpg') }}');">
    @include('partials.landing-nav', ['navMode' => 'auth'])

    <div class="container">
        <div class="form-container sign-in" style="left: 0; width: 100%; position: relative;">
            <form method="POST" action="{{ route('password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <h1>Reset Password</h1>

                <input
                    type="email"
                    name="email"
                    placeholder="Enter E-mail"
                    value="{{ old('email', $email) }}"
                    autocomplete="email"
                    class="@error('email') invalid @enderror"
                    required
                >
                @error('email')
                    <p class="field-msg">{{ $message }}</p>
                @enderror

                <div class="auth-password-wrap">
                    <input
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

                <div class="auth-password-wrap">
                    <input
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

                <button type="submit">Reset Password</button>
                <div class="mobile-toggle" style="display: flex;">
                    <a class="link" href="{{ route('login') }}">Back to Sign In</a>
                </div>
            </form>
        </div>
    </div>
    <script src="{{ asset('js/auth-toggle.js') }}"></script>
</body>
</html>
