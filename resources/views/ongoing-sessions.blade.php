<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    @include('partials.theme-head')
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Admin ongoing sessions">
    <title>Ongoing Sessions</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/ongoing-sessions.css') }}">
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
                @include('partials.sidebar-nav', ['active' => 'ongoing'])
                <div class="sidebar-footer">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="logout" type="submit"><span class="nav-icon"><i class="bi bi-box-arrow-right"></i></span><span class="nav-text">Logout</span></button>
                    </form>
                </div>
            </aside>

            <main class="main">
                <div class="topbar">
                    <div class="welcome">
                        @include('partials.topbar-panel-badge')
                        <h1>Ongoing Sessions</h1>
                        <div class="subtitle">
                            Monitor active massage sessions in real-time
                            <span class="subtitle-divider">·</span>
                            @include('partials.live-system-clock')
                        </div>
                    </div>
                    <div class="right">
                        @include('partials.topbar-notifications')
                        @include('partials.topbar-settings')
                        @include('partials.topbar-profile')
                    </div>
                </div>

                <section class="ongoing-wrap" id="ongoing-wrap">
                    @if (session('status'))
                        <p class="ongoing-flash ongoing-flash-success">{{ session('status') }}</p>
                    @endif
                    @if (session('error'))
                        <p class="ongoing-flash ongoing-flash-error">{{ session('error') }}</p>
                    @endif

                    <div id="ongoing-cards">
                        @forelse ($sessions as $session)
                            <article
                                class="ongoing-card"
                                data-session-card="true"
                                data-booking-id="{{ $session['id'] }}"
                                data-duration-seconds="{{ $session['duration_seconds'] }}"
                                data-session-start="{{ $session['session_start_iso'] }}"
                                data-session-end="{{ $session['session_end_iso'] }}"
                            >
                                <div class="ongoing-head">
                                    <div>
                                        <div class="ongoing-client-row">
                                            <h3>{{ $session['client'] }}</h3>
                                            @include('partials.client-source-pill', [
                                                'clientSource' => $session['client_source'] ?? 'online',
                                                'clientSourceLabel' => $session['client_source_label'] ?? 'Online Appointment',
                                            ])
                                        </div>
                                        <p>Therapist: {{ $session['therapist'] }}</p>
                                    </div>
                                    <form method="POST" action="{{ route('ongoing-sessions.complete', $session['id']) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button class="complete-btn" type="submit">
                                            <i class="bi bi-check-circle"></i>
                                            Complete Session
                                        </button>
                                    </form>
                                </div>

                                <div class="ongoing-meta-grid">
                                    <div class="meta-box">
                                        <span class="meta-label">Service</span>
                                        <strong>{{ $session['service'] }}</strong>
                                    </div>
                                    <div class="meta-box">
                                        <span class="meta-label">Duration</span>
                                        <strong>{{ $session['duration_minutes'] }} minutes</strong>
                                    </div>
                                    <div class="meta-box">
                                        <span class="meta-label">Elapsed Time</span>
                                        <strong data-session-elapsed="true">{{ $session['elapsed_minutes'] }} minutes</strong>
                                    </div>
                                    <div class="meta-box">
                                        <span class="meta-label">Remaining</span>
                                        <strong data-session-remaining="true"><i class="bi bi-clock"></i> {{ $session['remaining_minutes'] }} minutes</strong>
                                    </div>
                                </div>

                                <div class="progress-row">
                                    <span class="meta-label">Progress</span>
                                    <span class="progress-pct" data-session-progress-label="true">{{ $session['progress_pct'] }}%</span>
                                </div>
                                <div class="progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $session['progress_pct'] }}">
                                    <span data-session-progress-bar="true" style="width: {{ $session['progress_pct'] }}%"></span>
                                </div>

                                <div class="ongoing-footer">
                                    <span class="meta-label">Session Price</span>
                                    <strong>₱{{ $session['price'] }}</strong>
                                </div>
                            </article>
                        @empty
                            <p class="ongoing-empty" data-ongoing-empty="true">No ongoing sessions right now.</p>
                        @endforelse
                    </div>

                    @if ($sessions->hasPages())
                        <div class="ongoing-pagination">
                            {{ $sessions->withQueryString()->links() }}
                        </div>
                    @endif
                </section>
            </main>
        </div>
    </div>
    <script src="{{ asset('js/system-clock.js') }}"></script>
    <script>
        (() => {
            const pollUrl = @json($pollUrl ?? route('ongoing-sessions.poll'));
            const completedUrl = @json($completedUrl ?? route('completed-sessions.index'));
            const cardsRoot = document.getElementById('ongoing-cards');
            const systemClock = window.initSystemClock({
                serverNowIso: @json($serverNowIso ?? now()->toIso8601String()),
            });
            let pollInFlight = false;
            let redirectScheduled = false;

            const formatClock = (totalSeconds) => {
                const safe = Math.max(0, totalSeconds);
                const minutes = Math.floor(safe / 60);
                const seconds = safe % 60;

                if (minutes === 0) {
                    return `${seconds}s`;
                }

                return seconds > 0 ? `${minutes} min ${String(seconds).padStart(2, '0')}s` : `${minutes} minutes`;
            };

            const updateCard = (card, nowMs) => {
                const startMs = Date.parse(card.dataset.sessionStart || '');
                const durationSeconds = Number(card.dataset.durationSeconds || 0);

                if (!Number.isFinite(startMs) || durationSeconds <= 0) {
                    return false;
                }

                const elapsedSeconds = Math.min(
                    Math.max(0, Math.floor((nowMs - startMs) / 1000)),
                    durationSeconds
                );
                const remainingSeconds = Math.max(durationSeconds - elapsedSeconds, 0);
                const progressPct = Math.min(100, Math.round((elapsedSeconds / durationSeconds) * 100));

                const elapsedEl = card.querySelector('[data-session-elapsed]');
                const remainingEl = card.querySelector('[data-session-remaining]');
                const progressLabel = card.querySelector('[data-session-progress-label]');
                const progressBar = card.querySelector('[data-session-progress-bar]');
                const progressTrack = card.querySelector('.progress-track');

                if (elapsedEl) elapsedEl.textContent = formatClock(elapsedSeconds);
                if (remainingEl) {
                    remainingEl.innerHTML = `<i class="bi bi-clock"></i> ${formatClock(remainingSeconds)}`;
                }
                if (progressLabel) progressLabel.textContent = `${progressPct}%`;
                if (progressBar) progressBar.style.width = `${progressPct}%`;
                if (progressTrack) progressTrack.setAttribute('aria-valuenow', String(progressPct));

                return remainingSeconds === 0;
            };

            const tick = () => {
                const nowMs = systemClock.nowMs();
                let expiredCount = 0;
                const cards = cardsRoot?.querySelectorAll('[data-session-card]') ?? [];

                cards.forEach((card) => {
                    if (updateCard(card, nowMs)) {
                        expiredCount += 1;
                    }
                });

                if (expiredCount > 0) {
                    syncWithServer(true);
                }
            };

            const scheduleRedirect = (message) => {
                if (redirectScheduled) return;
                redirectScheduled = true;

                const flash = document.createElement('p');
                flash.className = 'ongoing-flash ongoing-flash-success';
                flash.textContent = message || 'Session ended. Opening completed transactions...';
                cardsRoot?.prepend(flash);

                window.setTimeout(() => {
                    window.location.href = completedUrl;
                }, 1200);
            };

            const removeCard = (bookingId) => {
                const card = cardsRoot?.querySelector(`[data-booking-id="${bookingId}"]`);
                card?.remove();

                const remaining = cardsRoot?.querySelectorAll('[data-session-card]').length ?? 0;
                if (remaining === 0 && !cardsRoot?.querySelector('[data-ongoing-empty]')) {
                    const empty = document.createElement('p');
                    empty.className = 'ongoing-empty';
                    empty.dataset.ongoingEmpty = 'true';
                    empty.textContent = 'No ongoing sessions right now.';
                    cardsRoot?.appendChild(empty);
                }
            };

            const syncWithServer = async (fromExpiry = false) => {
                if (pollInFlight) return;
                pollInFlight = true;

                try {
                    const response = await fetch(pollUrl, {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });

                    if (!response.ok) return;

                    const payload = await response.json();
                    systemClock.sync(payload.server_time);
                    const autoCompleted = Array.isArray(payload.auto_completed) ? payload.auto_completed : [];
                    const activeSessions = Array.isArray(payload.sessions) ? payload.sessions : [];
                    const activeIds = new Set(activeSessions.map((session) => String(session.id)));

                    autoCompleted.forEach((session) => {
                        removeCard(String(session.id));
                    });

                    cardsRoot?.querySelectorAll('[data-session-card]').forEach((card) => {
                        const bookingId = String(card.dataset.bookingId || '');
                        if (!activeIds.has(bookingId)) {
                            removeCard(bookingId);
                        }
                    });

                    activeSessions.forEach((session) => {
                        const card = cardsRoot?.querySelector(`[data-booking-id="${session.id}"]`);
                        if (!card) return;

                        if (session.session_end_iso) {
                            card.dataset.sessionEnd = session.session_end_iso;
                        }

                        updateCard(card, systemClock.nowMs());
                    });

                    const cardsLeft = cardsRoot?.querySelectorAll('[data-session-card]').length ?? 0;

                    if (autoCompleted.length > 0 && cardsLeft === 0) {
                        const names = autoCompleted.map((session) => session.client).filter(Boolean).join(', ');
                        scheduleRedirect(names
                            ? `${names} — session ended and moved to completed transactions.`
                            : 'Session ended and moved to completed transactions.');
                        return;
                    }

                    if (fromExpiry && autoCompleted.length > 0 && cardsLeft > 0) {
                        const flash = document.createElement('p');
                        flash.className = 'ongoing-flash ongoing-flash-success';
                        flash.textContent = 'A session ended and was moved to completed transactions.';
                        cardsRoot?.prepend(flash);
                    }
                } catch (error) {
                    // Keep local timer running even if sync fails briefly.
                } finally {
                    pollInFlight = false;
                }
            };

            tick();
            window.setInterval(tick, 1000);
            window.setInterval(() => syncWithServer(false), 15000);
        })();
    </script>
</body>
</html>
