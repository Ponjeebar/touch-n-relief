<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle }} — TouchNRelief</title>
    @include('partials.theme-head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
    <link rel="stylesheet" href="{{ asset('css/legal.css') }}?v={{ filemtime(public_path('css/legal.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}">
</head>
<body class="legal-page">
    @include('partials.landing-nav', ['navMode' => 'auth'])
    <main class="legal-shell">
        <a class="legal-back" href="{{ url()->previous() === url()->current() ? route('login') : url()->previous() }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back</a>
        <header class="legal-heading">
            <span>{{ $eyebrow }}</span>
            <h1>{{ $pageTitle }}</h1>
            <p>{{ $intro }}</p>
            <small>Effective September 25, 2026</small>
        </header>
        <div class="legal-content">
            @foreach ($sections as [$heading, $body])
                <section>
                    <h2>{{ $heading }}</h2>
                    <p>{{ $body }}</p>
                </section>
            @endforeach
        </div>
    </main>
</body>
</html>
