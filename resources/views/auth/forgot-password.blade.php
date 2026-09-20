<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password</title>
    @include('partials.theme-head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}">
</head>
<body class="auth-page" style="--auth-bg-image: url('{{ asset('images/login/background.jpg') }}');">
    @include('partials.landing-nav', ['navMode' => 'auth'])

    <div class="container">
        <div class="form-container sign-in" style="left: 0; width: 100%; position: relative;">
            <form method="POST" action="{{ route('password.email') }}">
                @csrf
                <h1>Forgot Password</h1>
                <p style="margin: 0 0 12px; color: #5d768f; font-size: 14px;">
                    Enter your email and we will send a password reset link.
                </p>

                @if (session('status'))
                    <p class="field-msg" style="color: #0a7a52;">{{ session('status') }}</p>
                @endif

                <input
                    type="email"
                    name="email"
                    placeholder="Enter E-mail"
                    value="{{ old('email') }}"
                    autocomplete="email"
                    class="@error('email') invalid @enderror"
                    required
                >
                @error('email')
                    <p class="field-msg">{{ $message }}</p>
                @enderror

                <button type="submit">Send Reset Link</button>
                <div class="mobile-toggle" style="display: flex;">
                    <a class="link" href="{{ route('login') }}">Back to Sign In</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
