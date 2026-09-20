@php
    $brandName = 'TOUCHnRELIEF';
    $showRegister = request()->boolean('register') || $errors->getBag('register')->any() || session('open_register_tab');
    $showForgot = request()->routeIs('password.request') || request()->boolean('forgot');
    $returnTo = (string) session('url.intended', request()->query('return_to', ''));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Sign in') }} — {{ $brandName }}</title>
    @include('partials.theme-head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}">
</head>
<body class="auth-page" style="--auth-bg-image: url('{{ asset('images/login/background.jpg') }}');">
    @include('partials.landing-nav', ['navMode' => 'auth'])
    <div class="auth-theme-float" role="group" aria-label="Display theme">
        <button type="button" class="auth-theme-btn" id="tnr-theme-light-auth" aria-pressed="true" title="Light mode"><i class="bi bi-sun-fill" aria-hidden="true"></i></button>
        <button type="button" class="auth-theme-btn" id="tnr-theme-dark-auth" aria-pressed="false" title="Dark mode"><i class="bi bi-moon-stars-fill" aria-hidden="true"></i></button>
    </div>
    @if (session('status'))
        <div class="auth-flash" role="status">{{ session('status') }}</div>
    @endif

    <div class="container {{ $showRegister ? 'active' : '' }} {{ $showForgot ? 'sign-in-forgot-active' : '' }}" id="container">
        <div class="form-container sign-up">
            <form method="POST" action="{{ route('register') }}" id="register-form" data-auth-register-form="true">
                @csrf
                <input type="hidden" name="return_to" value="{{ $returnTo }}">
                <h1>Create Account</h1>
                <input
                    type="text"
                    name="name"
                    placeholder="Name"
                    value="{{ old('name') }}"
                    autocomplete="name"
                    class="@error('name', 'register') invalid @enderror"
                    required
                >
                @error('name', 'register')
                    <p class="field-msg">{{ $message }}</p>
                @enderror
                <input
                    type="text"
                    name="username"
                    placeholder="Username"
                    value="{{ old('username') }}"
                    autocomplete="username"
                    autocapitalize="none"
                    spellcheck="false"
                    minlength="3"
                    maxlength="30"
                    pattern="[A-Za-z0-9_-]+"
                    title="3–30 characters: letters, numbers, underscores, and hyphens only."
                    class="@error('username', 'register') invalid @enderror"
                    required
                >
                @error('username', 'register')
                    <p class="field-msg">{{ $message }}</p>
                @enderror
                <input
                    type="email"
                    name="email"
                    placeholder="Enter E-mail"
                    value="{{ old('email') }}"
                    autocomplete="email"
                    class="@error('email', 'register') invalid @enderror"
                    required
                >
                @error('email', 'register')
                    <p class="field-msg">{{ $message }}</p>
                @enderror
                <input
                    type="tel"
                    name="contact_number"
                    placeholder="Contact number"
                    value="{{ old('contact_number') }}"
                    autocomplete="tel"
                    maxlength="11"
                    pattern="^09\d{9}$"
                    title="Use 09XXXXXXXXX (11 digits)."
                    inputmode="numeric"
                    class="@error('contact_number', 'register') invalid @enderror"
                    required
                >
                @error('contact_number', 'register')
                    <p class="field-msg">{{ $message }}</p>
                @enderror
                <div class="auth-field-block">
                    <label class="auth-field-label" for="register-birthday">Birthday</label>
                    <input
                        type="date"
                        id="register-birthday"
                        name="birthday"
                        value="{{ old('birthday') }}"
                        max="{{ now()->subYears(15)->format('Y-m-d') }}"
                        autocomplete="bday"
                        class="@error('birthday', 'register') invalid @enderror"
                        required
                    >
                    @error('birthday', 'register')
                        <p class="field-msg">{{ $message }}</p>
                    @enderror
                    <p class="auth-field-hint">Must be 15 years old or above.</p>
                </div>
                <fieldset class="auth-gender-field @error('sex', 'register') invalid @enderror">
                    <legend>Sex</legend>
                    <div class="auth-gender-options">
                        <label class="auth-gender-option">
                            <input type="radio" name="sex" value="male" @checked(old('sex') === 'male') required>
                            <span>Male</span>
                        </label>
                        <label class="auth-gender-option">
                            <input type="radio" name="sex" value="female" @checked(old('sex') === 'female')>
                            <span>Female</span>
                        </label>
                    </div>
                </fieldset>
                @error('sex', 'register')
                    <p class="field-msg">{{ $message }}</p>
                @enderror
                <div class="auth-password-wrap">
                    <input
                        type="password"
                        name="password"
                        placeholder="Enter Password"
                        autocomplete="new-password"
                        minlength="8"
                        class="@error('password', 'register') invalid @enderror"
                        required
                    >
                    <button type="button" class="auth-password-toggle" data-pw-toggle aria-label="Show password" aria-pressed="false">
                        <i class="bi bi-eye" aria-hidden="true"></i>
                    </button>
                </div>
                @error('password', 'register')
                    <p class="field-msg">{{ $message }}</p>
                @enderror
                <div class="auth-password-wrap">
                    <input
                        type="password"
                        name="password_confirmation"
                        placeholder="Confirm Password"
                        autocomplete="new-password"
                        minlength="8"
                        required
                    >
                    <button type="button" class="auth-password-toggle" data-pw-toggle aria-label="Show password" aria-pressed="false">
                        <i class="bi bi-eye" aria-hidden="true"></i>
                    </button>
                </div>
                <button type="submit">Sign Up</button>
                <div class="mobile-toggle">
                    <span>Already have an account?</span>
                    <button type="button" class="link" data-auth-panel="login">Sign In</button>
                </div>
            </form>
        </div>

        <div class="form-container sign-in">
            <form method="POST" action="{{ route('login.attempt') }}" novalidate class="auth-login-panel">
                @csrf
                <h1>Sign In</h1>
                <input
                    type="text"
                    name="login"
                    placeholder="Username, name, or email"
                    value="{{ old('login') }}"
                    autocomplete="username"
                    class="@error('login') invalid @enderror"
                    required
                >
                @error('login')
                    <p class="field-msg">{{ $message }}</p>
                @enderror
                <div class="auth-password-wrap">
                    <input
                        type="password"
                        name="password"
                        placeholder="Enter Password"
                        autocomplete="current-password"
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
                <a href="{{ route('password.request') }}" data-auth-forgot-open="true">Forgot Password?</a>
                <button type="submit">Sign In</button>
                <div class="mobile-toggle">
                    <span>No account yet?</span>
                    <button type="button" class="link" data-auth-panel="register">Sign Up</button>
                </div>
            </form>

            <form method="POST" action="{{ route('password.email') }}" class="auth-forgot-panel">
                @csrf
                <h1>Forgot Password</h1>
                <p class="auth-forgot-copy">Enter your email and we will send a password reset link.</p>
                <input type="hidden" name="_auth_mode" value="forgot">
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
                @if (session('status'))
                    <p class="field-msg auth-forgot-success">{{ session('status') }}</p>
                @endif
                <button type="submit">Send Reset Link</button>
                <button type="button" class="link auth-forgot-back" data-auth-forgot-close="true" data-login-url="{{ route('login') }}">Back to Sign In</button>
            </form>
        </div>

        <div class="toggle-container">
            <div class="toggle">
                <div class="toggle-panel toggle-left">
                    <div class="toggle-panel-logo-wrap">
                        <img
                            src="{{ asset('images/dashboard/logo.png') }}"
                            alt="{{ $brandName }} logo"
                            class="auth-toggle-logo"
                            width="240"
                            height="240"
                            decoding="async"
                        >
                    </div>
                    <h1>Welcome to<br>{{ $brandName }}</h1>
                    <button type="button" class="hidden" id="login">Sign In</button>
                </div>
                <div class="toggle-panel toggle-right">
                    <div class="toggle-panel-logo-wrap">
                        <img
                            src="{{ asset('images/dashboard/logo.png') }}"
                            alt="{{ $brandName }} logo"
                            class="auth-toggle-logo"
                            width="240"
                            height="240"
                            decoding="async"
                        >
                    </div>
                    <h1>Join {{ $brandName }}</h1>
                    <button type="button" class="hidden" id="register">Sign Up</button>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/auth-toggle.js') }}"></script>
</body>
</html>
