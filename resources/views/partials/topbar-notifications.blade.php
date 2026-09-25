@php
    $notificationFeed = app(\App\Services\NotificationFeedService::class);
    $notifications = $notificationFeed->recentBookingNotifications(6);
    $unreadCount = $notificationFeed->unreadCount();
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
        <span class="topbar-notif-badge" data-notif-badge data-staff-notif-badge @if ($unreadCount === 0) hidden @endif>{{ $unreadCount }}</span>
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
                    class="dashboard-note dashboard-note-{{ $type }} {{ ($note['is_read'] ?? false) ? 'note-read' : 'note-unread' }}"
                    data-notification-id="{{ $note['id'] ?? '' }}"
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
            document.body.dataset.staffNotificationsMarkAllUrl = @json(route('staff-notifications.mark-all-read'));
            document.body.dataset.staffNotificationReadUrlTemplate = @json(route('staff-notifications.read', ['staffNotification' => '__ID__']));
        }
    </script>
    <script src="{{ asset('js/staff-feed-poll.js') }}?v={{ filemtime(public_path('js/staff-feed-poll.js')) }}"></script>
    <script>
        document.querySelectorAll('[data-topbar-notifications]').forEach((root) => {
            const toggleBtn = root.querySelector('[data-notif-toggle]');
            const panel = root.querySelector('[data-notif-panel]');
            const markReadBtn = root.querySelector('[data-notif-mark-read]');
            const unreadBadge = root.querySelector('[data-notif-badge]');
            if (!toggleBtn || !panel) return;

            function updateBadgeAndButton() {
                const unreadCount = panel.querySelectorAll('.dashboard-note.note-unread').length;
                if (unreadBadge) {
                    unreadBadge.textContent = String(unreadCount);
                    unreadBadge.hidden = unreadCount === 0;
                }
                if (markReadBtn) {
                    markReadBtn.disabled = unreadCount === 0;
                }
            }

            updateBadgeAndButton();

            toggleBtn.addEventListener('click', (event) => {
                event.stopPropagation();
                const willOpen = panel.classList.contains('hidden-section');
                panel.classList.toggle('hidden-section');
                toggleBtn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            });

            markReadBtn?.addEventListener('click', () => {
                panel.querySelectorAll('.dashboard-note').forEach((note) => {
                    note.classList.remove('note-unread');
                    note.classList.add('note-read');
                });
                updateBadgeAndButton();
                markReadBtn.setAttribute('aria-label', 'All notifications read');
                markReadBtn.title = 'All notifications read';
            });

            panel.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLElement)) return;
                const note = target.closest('[data-notif-key]');
                if (!(note instanceof HTMLElement)) return;
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
