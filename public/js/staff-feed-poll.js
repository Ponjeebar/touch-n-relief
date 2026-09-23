(function () {
    const pollUrl = document.body?.dataset?.staffFeedPollUrl;
    if (!pollUrl) return;

    const NOTIF_ALL_READ_KEY = 'tnrNotificationsAllReadAtV1';
    const NOTIF_READ_KEYS_KEY = 'tnrNotificationsReadKeysV1';
    const POLL_INTERVAL_MS = 30000;

    function getReadKeys() {
        try {
            const raw = localStorage.getItem(NOTIF_READ_KEYS_KEY);
            const parsed = raw ? JSON.parse(raw) : [];
            return new Set(Array.isArray(parsed) ? parsed.map(String) : []);
        } catch (e) {
            return new Set();
        }
    }

    function getReadAtMs() {
        try {
            const raw = localStorage.getItem(NOTIF_ALL_READ_KEY);
            return raw ? new Date(raw).getTime() : Number.NaN;
        } catch (e) {
            return Number.NaN;
        }
    }

    function isNotificationRead(note) {
        const readKeys = getReadKeys();
        const readAtMs = getReadAtMs();
        const key = String(note.notification_key ?? '');
        const atMs = note.notification_at ? new Date(note.notification_at).getTime() : Number.NaN;
        const byTime = Number.isFinite(readAtMs) && Number.isFinite(atMs) && atMs <= readAtMs;

        return readKeys.has(key) || byTime;
    }

    function iconForType(type) {
        switch (type) {
            case 'confirmed':
                return 'bi-check-circle';
            case 'pending':
                return 'bi-hourglass-split';
            case 'cancelled':
                return 'bi-x-circle';
            case 'rescheduled':
                return 'bi-calendar2-event';
            default:
                return 'bi-bell';
        }
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderNotification(note) {
        const type = note.type ?? 'system';
        const readClass = isNotificationRead(note) ? 'note-read' : 'note-unread';
        const url = escapeHtml(note.url ?? '#');
        const title = escapeHtml(note.title ?? 'Notification');
        const message = escapeHtml(note.message ?? '');
        const notificationAt = note.notification_at ?? '';
        const notificationKey = note.notification_key ?? '';

        return `
            <a
                class="dashboard-note dashboard-note-${type} ${readClass}"
                data-notif-at="${notificationAt}"
                data-notif-key="${notificationKey}"
                href="${url}"
            >
                <div class="dashboard-note-icon">
                    <i class="bi ${iconForType(type)}"></i>
                </div>
                <div class="dashboard-note-body">
                    <div class="dashboard-note-title">${title}</div>
                    <div class="dashboard-note-message">${message}</div>
                </div>
            </a>
        `;
    }

    function renderNotifications(notifications) {
        const items = Array.isArray(notifications) ? notifications : [];

        document.querySelectorAll('[data-staff-notifications-list]').forEach((list) => {
            if (!(list instanceof HTMLElement)) return;

            if (items.length === 0) {
                list.innerHTML = '<div class="appointments-notif-empty">No notifications.</div>';
                return;
            }

            list.innerHTML = items.map(renderNotification).join('');
        });

        const unreadCount = items.filter((note) => !isNotificationRead(note)).length;

        document.querySelectorAll('[data-staff-notif-badge]').forEach((badge) => {
            badge.textContent = String(unreadCount);
            badge.hidden = false;
        });

        document.querySelectorAll('[data-notif-badge]').forEach((badge) => {
            badge.textContent = String(unreadCount);
            badge.hidden = false;
        });

        document.querySelectorAll('[data-mark-dashboard-read="true"], [data-notif-mark-read]').forEach((btn) => {
            if (btn instanceof HTMLButtonElement) {
                btn.disabled = unreadCount === 0;
            }
        });
    }

    function renderTodayAppointments(appointments) {
        const items = Array.isArray(appointments) ? appointments : [];

        document.querySelectorAll('[data-dashboard-today-appointments]').forEach((list) => {
            if (!(list instanceof HTMLElement)) return;

            const emptyMessage = list.querySelector('[data-dashboard-search-empty="true"]');
            const itemNodes = list.querySelectorAll('[data-dashboard-search-item="true"]');
            itemNodes.forEach((node) => node.remove());

            if (items.length === 0) {
                let emptyNode = list.querySelector('.session-list-empty:not([data-dashboard-search-empty="true"])');
                if (!emptyNode) {
                    emptyNode = document.createElement('p');
                    emptyNode.className = 'session-list-empty';
                    emptyNode.textContent = 'No appointments scheduled for today.';
                    list.insertBefore(emptyNode, emptyMessage ?? null);
                }
                emptyNode.hidden = false;
                return;
            }

            const staticEmpty = list.querySelector('.session-list-empty:not([data-dashboard-search-empty="true"])');
            if (staticEmpty instanceof HTMLElement) {
                staticEmpty.hidden = true;
            }

            items.forEach((appointment) => {
                const row = document.createElement('div');
                row.className = 'session-item';
                row.dataset.dashboardSearchItem = 'true';
                row.innerHTML = `
                    <div class="session-main">
                        <strong>${escapeHtml(appointment.client ?? '')}</strong>
                        <span>${escapeHtml(appointment.service ?? '')} · ${escapeHtml(appointment.therapist ?? '')}</span>
                    </div>
                    <div class="session-meta">
                        <span class="meta-chip time">${escapeHtml(appointment.time ?? '')}</span>
                        <span class="meta-chip ${escapeHtml(appointment.status_class ?? 'confirmed')}">${escapeHtml(appointment.status ?? '')}</span>
                    </div>
                `;
                list.insertBefore(row, emptyMessage ?? null);
            });
        });
    }

    function updateMetricValue(selector, value) {
        document.querySelectorAll(selector).forEach((el) => {
            if (el instanceof HTMLElement) {
                el.textContent = String(value);
            }
        });
    }

    async function pollStaffFeed() {
        try {
            const response = await fetch(pollUrl, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) return;

            const data = await response.json();
            renderNotifications(data.notifications ?? []);
            renderTodayAppointments(data.today_appointments ?? []);
            updateMetricValue('[data-upcoming-appointments-count]', data.upcoming_appointments ?? 0);
            updateMetricValue('[data-ongoing-sessions-count]', data.ongoing_sessions ?? 0);
        } catch (e) {
            // Ignore transient network errors.
        }
    }

    window.setInterval(pollStaffFeed, POLL_INTERVAL_MS);
})();
