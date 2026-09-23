@php
    $notifications = app(\App\Services\NotificationFeedService::class)->recentBookingNotifications(6);
    $unreadCount = count($notifications);
@endphp

<div class="topbar-notifications" data-topbar-notifications>
    <button
        class="icon-btn"
        type="button"
        aria-label="Notifications"
        aria-haspopup="true"
        aria-expanded="false"
        data-notif-toggle
    >
        <i class="bi bi-bell"></i>
        <span class="topbar-notif-badge" data-notif-badge data-staff-notif-badge>{{ $unreadCount }}</span>
    </button>

    <aside class="dashboard-notifications hidden-section" aria-label="Notifications panel" data-notif-panel>
        <div class="dashboard-notifications-head">
            <h3>Notifications</h3>
            <button class="icon-btn slim" type="button" aria-label="Mark all as read" data-notif-mark-read title="Mark all as read">
                <i class="bi bi-check2-all"></i>
            </button>
        </div>
        <div class="dashboard-notifications-list" data-staff-notifications-list>
            @forelse ($notifications as $note)
                @php($type = $note['type'] ?? 'system')
                <a
                    class="dashboard-note dashboard-note-{{ $type }} note-unread"
                    data-notif-at="{{ $note['notification_at'] ?? '' }}"
                    data-notif-key="{{ $note['notification_key'] ?? '' }}"
                    href="{{ $note['url'] ?? route('appointments.index') }}"
                >
                    <div class="dashboard-note-icon">
                        @if ($type === 'confirmed')
                            <i class="bi bi-check-circle"></i>
                        @elseif ($type === 'pending')
                            <i class="bi bi-hourglass-split"></i>
                        @elseif ($type === 'cancelled')
                            <i class="bi bi-x-circle"></i>
                        @elseif ($type === 'rescheduled')
                            <i class="bi bi-calendar2-event"></i>
                        @else
                            <i class="bi bi-bell"></i>
                        @endif
                    </div>
                    <div class="dashboard-note-body">
                        <div class="dashboard-note-title">{{ $note['title'] ?? 'Notification' }}</div>
                        <div class="dashboard-note-message">{{ $note['message'] ?? '' }}</div>
                    </div>
                </a>
            @empty
                <div class="appointments-notif-empty">No notifications.</div>
            @endforelse
        </div>
    </aside>
</div>

@once
    <script>
        if (document.body && !document.body.dataset.staffFeedPollUrl) {
            document.body.dataset.staffFeedPollUrl = @json(route('staff-feed.poll'));
        }
    </script>
    <script src="{{ asset('js/staff-feed-poll.js') }}?v={{ filemtime(public_path('js/staff-feed-poll.js')) }}"></script>
    <script>
        document.querySelectorAll('[data-topbar-notifications]').forEach((root) => {
            const toggleBtn = root.querySelector('[data-notif-toggle]');
            const panel = root.querySelector('[data-notif-panel]');
            const markReadBtn = root.querySelector('[data-notif-mark-read]');
            const unreadBadge = root.querySelector('[data-notif-badge]');
            const NOTIF_ALL_READ_KEY = 'tnrNotificationsAllReadAtV1';
            const NOTIF_READ_KEYS_KEY = 'tnrNotificationsReadKeysV1';

            if (!toggleBtn || !panel) return;

            function getReadKeys() {
                try {
                    const raw = localStorage.getItem(NOTIF_READ_KEYS_KEY);
                    const parsed = raw ? JSON.parse(raw) : [];
                    return new Set(Array.isArray(parsed) ? parsed.map(String) : []);
                } catch (e) {
                    return new Set();
                }
            }

            function saveReadKeys(keys) {
                try {
                    localStorage.setItem(NOTIF_READ_KEYS_KEY, JSON.stringify(Array.from(keys)));
                } catch (e) {}
            }

            function markNotificationReadByElement(el) {
                if (!(el instanceof HTMLElement)) return;
                const key = el.dataset.notifKey ?? '';
                if (!key) return;
                const keys = getReadKeys();
                keys.add(key);
                saveReadKeys(keys);
            }

            function updateBadgeAndButton() {
                const unreadCount = panel.querySelectorAll('.dashboard-note.note-unread').length;
                if (unreadBadge) {
                    unreadBadge.textContent = String(unreadCount);
                    unreadBadge.hidden = false;
                }
                if (markReadBtn) {
                    markReadBtn.disabled = unreadCount === 0;
                }
            }

            function applyReadStateFromLocalStorage() {
                const readKeys = getReadKeys();
                let raw = null;
                try {
                    raw = localStorage.getItem(NOTIF_ALL_READ_KEY);
                } catch (e) {
                    raw = null;
                }

                if (!raw && readKeys.size === 0) {
                    // If nothing was stored, keep server-rendered classes but sync badge.
                    updateBadgeAndButton();
                    return;
                }

                const readAtMs = raw ? new Date(raw).getTime() : Number.NaN;

                panel.querySelectorAll('[data-notif-at]').forEach((el) => {
                    const atRaw = el instanceof HTMLElement ? el.dataset.notifAt : '';
                    const key = el instanceof HTMLElement ? (el.dataset.notifKey ?? '') : '';
                    const atMs = atRaw ? new Date(atRaw).getTime() : Number.NaN;
                    const byTime = Number.isFinite(readAtMs) && Number.isFinite(atMs) && atMs <= readAtMs;

                    if (readKeys.has(key) || byTime) {
                        el.classList.remove('note-unread');
                        el.classList.add('note-read');
                    } else {
                        el.classList.remove('note-read');
                        el.classList.add('note-unread');
                    }
                });

                updateBadgeAndButton();
            }

            window.addEventListener('storage', (event) => {
                if (event.key !== NOTIF_ALL_READ_KEY) return;
                applyReadStateFromLocalStorage();
            });

            applyReadStateFromLocalStorage();

            toggleBtn.addEventListener('click', (event) => {
                event.stopPropagation();
                const willOpen = panel.classList.contains('hidden-section');
                panel.classList.toggle('hidden-section');
                toggleBtn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            });

            markReadBtn?.addEventListener('click', () => {
                try {
                    localStorage.setItem(NOTIF_ALL_READ_KEY, new Date().toISOString());
                } catch (e) {}

                applyReadStateFromLocalStorage();
                markReadBtn.setAttribute('aria-label', 'All notifications read');
                markReadBtn.title = 'All notifications read';
            });

            panel.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLElement)) return;
                const note = target.closest('[data-notif-key]');
                if (!(note instanceof HTMLElement)) return;
                markNotificationReadByElement(note);
                note.classList.remove('note-unread');
                note.classList.add('note-read');
                updateBadgeAndButton();
            });

            document.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLElement)) return;
                if (target.closest('[data-topbar-notifications]') === root) return;
                panel.classList.add('hidden-section');
                toggleBtn.setAttribute('aria-expanded', 'false');
            });

            document.addEventListener('keydown', (event) => {
                if (event.key !== 'Escape') return;
                panel.classList.add('hidden-section');
                toggleBtn.setAttribute('aria-expanded', 'false');
            });
        });
    </script>
@endonce
