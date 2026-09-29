@php
    $requestId = request()->attributes->get('request_id');
    $canRetry = request()->isMethod('GET');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') — TouchNRelief</title>
    <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('favicon-64.png') }}">
    <link rel="stylesheet" href="{{ asset('css/error-pages.css') }}?v={{ filemtime(public_path('css/error-pages.css')) }}">
    <script>
        try {
            if (localStorage.getItem('tnr-theme') === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        } catch (error) {}
    </script>
</head>
<body>
    <header class="error-header">
        <a href="{{ route('landing') }}" class="error-brand" aria-label="Return to TouchNRelief home">
            <img src="{{ asset('images/dashboard/logo.png') }}" alt="" width="48" height="48">
            <span>TouchNRelief</span>
        </a>
    </header>

    <main class="error-main">
        <div class="error-status" aria-hidden="true">@yield('code')</div>
        <section class="error-content" aria-labelledby="error-title">
            <p class="error-eyebrow">@yield('eyebrow', 'Request interrupted')</p>
            <h1 id="error-title">@yield('title')</h1>
            <p class="error-message">@yield('message')</p>
            @hasSection('guidance')
                <p class="error-guidance">@yield('guidance')</p>
            @endif

            <div class="error-actions">
                <a href="{{ route('landing') }}" class="error-action-primary">@yield('primary_action', 'Return to home')</a>
                @if ($canRetry)
                    <a href="{{ url()->current() }}" class="error-action-secondary">Try again</a>
                @endif
            </div>

            @if (is_string($requestId) && $requestId !== '')
                <p class="error-reference">Reference ID: <code>{{ $requestId }}</code></p>
            @endif
        </section>
    </main>
</body>
</html>
