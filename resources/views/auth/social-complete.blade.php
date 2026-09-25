@php($brandName = 'TOUCHnRELIEF')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Complete account — {{ $brandName }}</title>
    @include('partials.theme-head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/login.css') }}?v={{ filemtime(public_path('css/login.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}?v={{ filemtime(public_path('css/theme.css')) }}">
</head>
<body class="auth-page social-complete-page" style="--auth-bg-image: url('{{ asset('images/login/background.jpg') }}');">
    @include('partials.landing-nav', ['navMode' => 'auth'])

    <main class="social-complete-card">
        <div class="social-complete-provider" aria-hidden="true">
            <i class="bi bi-{{ $pending['provider'] === 'google' ? 'google' : 'facebook' }}"></i>
        </div>
        <h1>Complete your account</h1>
        <p class="social-complete-copy">Signed in as <strong>{{ $pending['email'] }}</strong>. Add the required spa details to finish creating your customer account.</p>

        @if ($errors->any())
            <div class="social-complete-errors" role="alert">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('social.store') }}">
            @csrf
            <label for="social-contact">Contact number</label>
            <input id="social-contact" name="contact_number" type="tel" value="{{ old('contact_number') }}" placeholder="09XXXXXXXXX" maxlength="11" pattern="^09\d{9}$" inputmode="numeric" autocomplete="tel" required>

            <label for="social-birthday">Birthday</label>
            <input id="social-birthday" name="birthday" type="date" value="{{ old('birthday') }}" max="{{ now()->subYears(15)->format('Y-m-d') }}" autocomplete="bday" required>
            <p class="auth-field-hint">You must be at least 15 years old.</p>

            <fieldset class="auth-gender-field">
                <legend>Sex</legend>
                <div class="auth-gender-options">
                    <label class="auth-gender-option"><input type="radio" name="sex" value="male" @checked(old('sex') === 'male') required><span>Male</span></label>
                    <label class="auth-gender-option"><input type="radio" name="sex" value="female" @checked(old('sex') === 'female')><span>Female</span></label>
                </div>
            </fieldset>

            <label class="auth-terms-option">
                <input type="checkbox" name="terms_accepted" value="1" @checked(old('terms_accepted')) required>
                <span>I agree to the <a href="{{ route('terms-and-conditions') }}" target="_blank" rel="noopener">Terms and Conditions</a> and acknowledge the <a href="{{ route('privacy-policy') }}" target="_blank" rel="noopener">Privacy Policy</a>.</span>
            </label>

            <button type="submit">Create customer account</button>
            <a class="social-complete-cancel" href="{{ route('login') }}">Cancel</a>
        </form>
    </main>

    <script src="{{ asset('js/auth-toggle.js') }}?v={{ filemtime(public_path('js/auth-toggle.js')) }}"></script>
</body>
</html>
