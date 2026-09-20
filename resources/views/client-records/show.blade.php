<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    @include('partials.theme-head')
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Client record">
    <title>Client Record</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/client-records.css') }}">
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}">
</head>
<body>
    <div class="app-shell">
        <div class="dashboard">
            <aside class="sidebar">
                <div class="brand">
                    <span class="brand-logo" aria-hidden="false">
                        <img src="{{ asset('images/dashboard/logo.png') }}" alt="TOUCHnRELIEF logo" class="brand-logo-img">
                    </span>
                    <span class="brand-copy">
                        <span class="brand-text">TOUCHnRELIEF</span>
                        <span class="brand-subtext">Appointment and Record Management System</span>
                    </span>
                </div>
                @include('partials.sidebar-nav', ['active' => 'client-records'])
                <div class="sidebar-footer">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="logout" type="submit"><span class="nav-icon"><i class="bi bi-box-arrow-right"></i></span><span class="nav-text">Logout</span></button>
                    </form>
                </div>
            </aside>

            <main class="main cr-record-main">
                <div class="topbar">
                    <div class="welcome">
                        @include('partials.topbar-panel-badge')
                        <div class="cr-topline">
                            <a class="cr-back" href="{{ route('client-records.index') }}">
                                <i class="bi bi-arrow-left"></i>
                                Back
                            </a>
                            <h1>Client Record</h1>
                        </div>
                    </div>
                    <div class="right">
                        <div class="actions" aria-label="Client record actions">
                            @include('partials.topbar-notifications')
                            @include('partials.topbar-settings')
                        </div>
                        @include('partials.topbar-profile')
                    </div>
                </div>

                @if (session('status'))
                    <div class="toast-success" id="status-toast" role="status" aria-live="polite">
                        <i class="bi bi-check-circle-fill"></i>
                        <span>{{ session('status') }}</span>
                        <button type="button" class="toast-close" aria-label="Close">&times;</button>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="cr-alert cr-alert-error" role="alert">
                        @foreach ($errors->all() as $err)
                            <div>{{ $err }}</div>
                        @endforeach
                    </div>
                @endif

                <section class="cr-grid" aria-label="Client record layout">
                    <article class="cr-card cr-profile">
                        <div class="cr-profile-top">
                            <div class="cr-avatar" aria-hidden="true">
                                @if (!empty($client['photo_url']))
                                    <img src="{{ $client['photo_url'] }}" alt="{{ $client['full_name'] }} profile photo" />
                                @else
                                    <span class="cr-avatar-fallback">{{ strtoupper(substr($client['full_name'], 0, 1)) }}</span>
                                @endif
                            </div>
                            <div class="cr-name">
                                <h2>{{ $client['full_name'] }}</h2>
                                <div class="cr-tag">{{ $client['tagline'] }}</div>
                            </div>
                        </div>

                        <div class="cr-stats">
                            <div class="cr-stat">
                                <div class="k">DOB</div>
                                <div class="v">{{ $client['dob'] }}</div>
                            </div>
                            <div class="cr-stat">
                                <div class="k">Age</div>
                                <div class="v">{{ $client['age'] }}</div>
                            </div>
                        </div>

                    </article>

                    <article class="cr-card cr-user-profile">
                        <div class="cr-card-head">
                            <h3>Client profile</h3>
                        </div>
                        <div class="cr-contact-fields">
                            <div class="cr-kv">
                                <div class="k">Name</div>
                                <div class="v">{{ $userProfile['name'] }}</div>
                            </div>
                            <div class="cr-kv">
                                <div class="k">Username</div>
                                <div class="v">{{ $userProfile['username'] }}</div>
                            </div>
                            <div class="cr-kv">
                                <div class="k">Email</div>
                                <div class="v">{{ $userProfile['email'] }}</div>
                            </div>
                            <div class="cr-kv">
                                <div class="k">Contact number</div>
                                <div class="v">{{ $userProfile['contact_number'] }}</div>
                            </div>
                            <div class="cr-kv cr-kv-span2">
                                <div class="k">Account created</div>
                                <div class="v">{{ $userProfile['account_created'] }}</div>
                            </div>
                            <div class="cr-kv">
                                <div class="k">Sex</div>
                                <div class="v">{{ $userProfile['sex'] }}</div>
                            </div>
                            <div class="cr-kv">
                                <div class="k">Therapist preference</div>
                                <div class="v">{{ $userProfile['therapist_gender_preference'] }}</div>
                            </div>
                            <div class="cr-kv">
                                <div class="k">Currently pregnant</div>
                                <div class="v">{{ $userProfile['pregnancy'] }}</div>
                            </div>
                            <div class="cr-kv">
                                <div class="k">Pressure preference</div>
                                <div class="v">{{ $userProfile['pressure_preference'] }}</div>
                            </div>
                        </div>
                    </article>

                    <article class="cr-card cr-medications">
                        <div class="cr-card-head">
                            <h3>Current medications</h3>
                        </div>
                        <ul class="cr-list">
                            @forelse ($medications as $med)
                                <li><i class="bi bi-capsule" aria-hidden="true"></i><span>{{ $med }}</span></li>
                            @empty
                                <li class="cr-list-empty">
                                    <span class="cr-note-empty">No current medications recorded for this client.</span>
                                </li>
                            @endforelse
                        </ul>
                    </article>

                    @include('client-records.partials.transaction-history', ['transactions' => $transactions])
                </section>

            </main>
        </div>
    </div>

    <script>
        const statusToast = document.getElementById('status-toast');
        if (statusToast) {
            const closeBtn = statusToast.querySelector('.toast-close');
            const hideToast = () => statusToast.classList.add('hidden');
            closeBtn?.addEventListener('click', hideToast);
            window.setTimeout(hideToast, 3500);
        }
    </script>
</body>
</html>

