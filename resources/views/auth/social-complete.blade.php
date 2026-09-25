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
        <header class="social-complete-header">
            <div class="social-complete-provider">
                <i class="bi bi-{{ $pending['provider'] === 'google' ? 'google' : 'facebook' }}" aria-hidden="true"></i>
                <span>{{ ucfirst($pending['provider']) }} account verified</span>
            </div>
            <h1>Complete your account</h1>
            <p class="social-complete-copy">Add the remaining details required for booking your spa appointments.</p>
        </header>

        <section class="social-account-summary" aria-label="Verified social account">
            <div class="social-account-avatar" aria-hidden="true">{{ strtoupper(substr($pending['name'], 0, 1)) }}</div>
            <div class="social-account-identity">
                <strong>{{ $pending['name'] }}</strong>
                <span>{{ $pending['email'] }}</span>
            </div>
            <span class="social-account-status"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Verified</span>
        </section>

        @if ($errors->any())
            <div class="social-complete-errors" role="alert">
                <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                <div>
                    <strong>Check the highlighted information.</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('social.store') }}">
            @csrf
            <div class="social-complete-fields">
                <div class="social-complete-field">
                    <label for="social-contact">Contact number</label>
                    <input id="social-contact" name="contact_number" type="tel" value="{{ old('contact_number') }}" placeholder="09XXXXXXXXX" maxlength="11" pattern="^09\d{9}$" inputmode="numeric" autocomplete="tel" class="@error('contact_number') invalid @enderror" aria-describedby="social-contact-hint" required>
                    <small id="social-contact-hint">Use an 11-digit Philippine mobile number.</small>
                </div>

                <div class="social-complete-field">
                    <label for="social-birthday">Birthday</label>
                    <input id="social-birthday" name="birthday" type="date" value="{{ old('birthday') }}" max="{{ now()->subYears(15)->format('Y-m-d') }}" autocomplete="bday" class="@error('birthday') invalid @enderror" aria-describedby="social-birthday-hint" required>
                    <small id="social-birthday-hint">You must be at least 15 years old.</small>
                </div>
            </div>

            <fieldset class="auth-gender-field @error('sex') invalid @enderror">
                <legend>Sex</legend>
                <div class="auth-gender-options">
                    <label class="auth-gender-option"><input type="radio" name="sex" value="male" @checked(old('sex') === 'male') required><span>Male</span></label>
                    <label class="auth-gender-option"><input type="radio" name="sex" value="female" @checked(old('sex') === 'female')><span>Female</span></label>
                </div>
            </fieldset>

            <label class="auth-terms-option @error('terms_accepted') invalid @enderror">
                <input type="checkbox" name="terms_accepted" value="1" @checked(old('terms_accepted')) required>
                <span>I agree to the <a href="{{ route('terms-and-conditions') }}" target="_blank" rel="noopener">Terms and Conditions</a> and acknowledge the <a href="{{ route('privacy-policy') }}" target="_blank" rel="noopener">Privacy Policy</a>.</span>
            </label>

            <div class="social-complete-actions">
                <button type="submit"><i class="bi bi-person-check" aria-hidden="true"></i> Finish sign up</button>
                <a class="social-complete-cancel" href="{{ route('login') }}">Cancel and return to sign in</a>
            </div>
        </form>
    </main>

    <script src="{{ asset('js/auth-toggle.js') }}?v={{ filemtime(public_path('js/auth-toggle.js')) }}"></script>
</body>
</html>
