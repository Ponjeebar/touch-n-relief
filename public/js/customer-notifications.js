(function () {
    const POLL_INTERVAL_MS = 30000;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        ?? document.querySelector('input[name="_token"]')?.value
        ?? '';

    const notificationsById = new Map();

    function seedNotifications(notifications) {
        notificationsById.clear();
        (Array.isArray(notifications) ? notifications : []).forEach((note) => {
            if (note && note.id != null) {
                notificationsById.set(String(note.id), note);
            }
        });
    }

    seedNotifications(window.__tnrCustomerNotifications ?? []);

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function iconClassForType(type) {
        switch (type) {
            case 'cancelled':
                return 'bi-x-circle';
            case 'rescheduled':
                return 'bi-calendar2-event';
            case 'reminder':
                return 'bi-alarm';
            default:
                return 'bi-bell';
        }
    }

    function typeLabel(type) {
        switch (type) {
            case 'cancelled':
                return 'Cancelled';
            case 'rescheduled':
                return 'Rescheduled';
            case 'reminder':
                return 'Reminder';
            default:
                return 'Update';
        }
    }

    function detailRows(details, type) {
        const rows = [];
        const push = (label, value, wide = false) => {
            const text = String(value ?? '').trim();
            if (text !== '') {
                rows.push({ label, value: text, wide });
            }
        };

        push('Service', details.service_name);
        push('Therapist', details.therapist_name);
        push('Date', details.booking_date_display);
        push('Time', details.time_slot);
        if (details.duration_minutes) {
            push('Duration', details.duration_minutes + ' min');
        }
        if (details.amount) {
            push('Amount', '₱' + Number(details.amount).toLocaleString());
        }

        if (type === 'cancelled') {
            push('Reason', details.cancellation_reason, true);
        }

        if (details.action_by) {
            push('Updated by', 'Our ' + details.action_by);
        }

        if (type === 'reminder') {
            push('Reminder', details.reminder_window === '2h' ? 'About 2 hours before' : 'About 24 hours before', true);
        }

        return rows;
    }

    function renderNotificationItem(note) {
        const type = note.type ?? 'system';
        const readClass = note.is_read ? '' : 'is-unread';

        return `
            <button
                type="button"
                class="customer-notif-item ${readClass}"
                data-customer-notif-item
                data-notification-id="${escapeHtml(note.id)}"
            >
                <span class="customer-notif-item-icon ${escapeHtml(type)}">
                    <i class="bi ${iconClassForType(type)}"></i>
                </span>
                <span class="customer-notif-item-body">
                    <span class="customer-notif-item-title">${escapeHtml(note.title ?? 'Notification')}</span>
                    <span class="customer-notif-item-message">${escapeHtml(note.message ?? '')}</span>
                    <span class="customer-notif-item-time">${escapeHtml(note.time ?? '')}</span>
                </span>
            </button>
        `;
    }

    function updateBadge(badge, unreadCount) {
        if (!(badge instanceof HTMLElement)) return;
        badge.textContent = String(unreadCount);
        badge.hidden = unreadCount <= 0;
    }

    function updateMarkAllButton(button, unreadCount) {
        if (!(button instanceof HTMLButtonElement)) return;
        button.disabled = unreadCount <= 0;
    }

    function renderList(list, notifications) {
        if (!(list instanceof HTMLElement)) return;

        seedNotifications(notifications);

        const items = Array.isArray(notifications) ? notifications : [];
        if (items.length === 0) {
            list.innerHTML = '<div class="customer-notif-empty">No notifications yet.</div>';
            return;
        }

        list.innerHTML = items.map(renderNotificationItem).join('');
    }

    let modalEl = null;

    function ensureModalOnBody() {
        if (modalEl instanceof HTMLElement) {
            return modalEl;
        }

        modalEl = document.querySelector('[data-customer-notif-modal]');
        if (modalEl instanceof HTMLElement && modalEl.parentElement !== document.body) {
            document.body.appendChild(modalEl);
        }

        return modalEl;
    }

    function bindModalEvents() {
        const modal = ensureModalOnBody();
        if (!(modal instanceof HTMLElement) || modal.dataset.bound === 'true') {
            return;
        }

        modal.dataset.bound = 'true';

        modal.querySelectorAll('[data-customer-notif-modal-close]').forEach((btn) => {
            btn.addEventListener('click', closeModal);
        });

        modal.querySelector('[data-customer-notif-modal-backdrop]')?.addEventListener('click', closeModal);

        modal.querySelector('.cn-modal__panel')?.addEventListener('click', (event) => {
            event.stopPropagation();
        });
    }

    function openModal(note) {
        const modal = ensureModalOnBody();
        if (!(modal instanceof HTMLElement)) return;

        bindModalEvents();

        const type = note.type ?? 'system';
        const panel = modal.querySelector('.cn-modal__panel');
        const status = modal.querySelector('[data-customer-notif-modal-status]');
        const title = modal.querySelector('[data-customer-notif-modal-title]');
        const noteWrap = modal.querySelector('[data-customer-notif-modal-note]');
        const message = modal.querySelector('[data-customer-notif-modal-message]');
        const scheduleChange = modal.querySelector('[data-customer-notif-schedule-change]');
        const scheduleFrom = modal.querySelector('[data-customer-notif-schedule-from]');
        const scheduleTo = modal.querySelector('[data-customer-notif-schedule-to]');
        const detailsEl = modal.querySelector('[data-customer-notif-modal-details]');
        const details = note.details ?? {};

        const knownTypes = ['cancelled', 'rescheduled', 'reminder'];
        if (panel instanceof HTMLElement) {
            panel.className = 'cn-modal__panel' + (knownTypes.includes(type) ? ' cn-modal__panel--' + type : '');
        }
        if (status instanceof HTMLElement) {
            status.textContent = typeLabel(type);
            status.className = 'cn-modal__status' + (knownTypes.includes(type) ? ' cn-modal__status--' + type : '');
        }
        if (title instanceof HTMLElement) {
            title.textContent = note.title ?? 'Notification';
        }
        if (message instanceof HTMLElement) {
            message.textContent = note.message ?? '';
        }
        if (noteWrap instanceof HTMLElement) {
            noteWrap.hidden = !(note.message ?? '').trim();
            const noteIcon = noteWrap.querySelector('.bi');
            if (noteIcon instanceof HTMLElement) {
                const icon = type === 'cancelled'
                    ? 'bi-x-circle'
                    : type === 'reminder'
                        ? 'bi-alarm'
                        : 'bi-info-circle';
                noteIcon.className = 'bi ' + icon;
            }
        }

        if (scheduleChange instanceof HTMLElement && scheduleFrom instanceof HTMLElement && scheduleTo instanceof HTMLElement) {
            const fromText = [details.previous_date_display, details.previous_time_slot].filter(Boolean).join(' · ');
            const toText = [details.booking_date_display, details.time_slot].filter(Boolean).join(' · ');
            const showSchedule = type === 'rescheduled' && fromText !== '' && toText !== '';

            scheduleChange.hidden = !showSchedule;
            if (showSchedule) {
                scheduleFrom.textContent = fromText;
                scheduleTo.textContent = toText;
            }
        }

        if (detailsEl instanceof HTMLElement) {
            const rows = detailRows(details, type);
            detailsEl.innerHTML = rows.map((row) => `
                <div${row.wide ? ' class="cn-modal__summary-wide"' : ''}>
                    <dt>${escapeHtml(row.label)}</dt>
                    <dd>${escapeHtml(row.value)}</dd>
                </div>
            `).join('');
        }

        modal.classList.add('is-open');
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('cn-modal-open');
    }

    function closeModal() {
        const modal = ensureModalOnBody();
        if (!(modal instanceof HTMLElement)) return;

        modal.classList.remove('is-open');
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('cn-modal-open');
    }

    async function postJson(url) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
            credentials: 'same-origin',
            body: '{}',
        });

        if (!response.ok) {
            throw new Error('Request failed');
        }

        return response.json();
    }

    function readUrlFor(root, notificationId) {
        const template = root.dataset.readUrlTemplate ?? '';
        return template.replace('__ID__', String(notificationId));
    }

    async function markNotificationRead(root, notificationId) {
        try {
            const data = await postJson(readUrlFor(root, notificationId));
            return typeof data.unread_count === 'number' ? data.unread_count : null;
        } catch (e) {
            return null;
        }
    }

    function ensurePanelPortaled(panel) {
        if (panel instanceof HTMLElement && panel.parentElement !== document.body) {
            document.body.appendChild(panel);
        }
    }

    function positionNotifPanel(toggleBtn, panel) {
        if (!(toggleBtn instanceof HTMLElement) || !(panel instanceof HTMLElement)) {
            return;
        }

        const rect = toggleBtn.getBoundingClientRect();
        const panelWidth = Math.min(360, window.innerWidth - 24);
        let left = rect.right - panelWidth;
        left = Math.max(12, Math.min(left, window.innerWidth - panelWidth - 12));

        panel.style.top = `${Math.round(rect.bottom + 10)}px`;
        panel.style.left = `${Math.round(left)}px`;
    }

    function bindRoot(root) {
        const toggleBtn = root.querySelector('[data-customer-notif-toggle]');
        const panel = root.querySelector('[data-customer-notif-panel]');
        const list = root.querySelector('[data-customer-notif-list]');
        const badge = root.querySelector('[data-customer-notif-badge]');
        const markAllBtn = root.querySelector('[data-customer-notif-mark-all]');
        const pollUrl = root.dataset.pollUrl ?? '';

        if (!(toggleBtn instanceof HTMLButtonElement) || !(panel instanceof HTMLElement) || !pollUrl) {
            return;
        }

        ensurePanelPortaled(panel);

        const syncPanelPosition = () => {
            if (!panel.classList.contains('hidden-section')) {
                positionNotifPanel(toggleBtn, panel);
            }
        };

        toggleBtn.addEventListener('click', (event) => {
            event.stopPropagation();
            const willOpen = panel.classList.contains('hidden-section');
            if (willOpen) {
                ensurePanelPortaled(panel);
                positionNotifPanel(toggleBtn, panel);
            }
            panel.classList.toggle('hidden-section');
            toggleBtn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });

        window.addEventListener('resize', syncPanelPosition);
        window.addEventListener('scroll', syncPanelPosition, true);

        markAllBtn?.addEventListener('click', async () => {
            const markAllUrl = root.dataset.markAllUrl ?? '';
            if (!markAllUrl) return;

            try {
                const data = await postJson(markAllUrl);
                const unreadCount = typeof data.unread_count === 'number' ? data.unread_count : 0;
                updateBadge(badge, unreadCount);
                updateMarkAllButton(markAllBtn, unreadCount);
                list?.querySelectorAll('[data-customer-notif-item]').forEach((item) => {
                    item.classList.remove('is-unread');
                });
                notificationsById.forEach((note, key) => {
                    notificationsById.set(key, { ...note, is_read: true });
                });
            } catch (e) {
                // Ignore transient errors.
            }
        });

        list?.addEventListener('click', async (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) return;

            const item = target.closest('[data-customer-notif-item]');
            if (!(item instanceof HTMLElement)) return;

            const notificationId = item.dataset.notificationId ?? '';
            const note = notificationsById.get(String(notificationId)) ?? {
                id: notificationId,
                title: 'Notification',
                message: '',
                details: {},
                type: 'system',
                is_read: false,
            };

            openModal(note);
            panel.classList.add('hidden-section');
            toggleBtn.setAttribute('aria-expanded', 'false');

            if (!note.is_read && note.id) {
                const unreadCount = await markNotificationRead(root, note.id);
                if (typeof unreadCount === 'number') {
                    updateBadge(badge, unreadCount);
                    updateMarkAllButton(markAllBtn, unreadCount);
                }
                item.classList.remove('is-unread');
                notificationsById.set(String(note.id), { ...note, is_read: true });
            }
        });

        document.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) return;
            if (target.closest('[data-customer-notifications]') === root) return;
            if (target.closest('[data-customer-notif-panel]') === panel) return;
            panel.classList.add('hidden-section');
            toggleBtn.setAttribute('aria-expanded', 'false');
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                panel.classList.add('hidden-section');
                toggleBtn.setAttribute('aria-expanded', 'false');
                closeModal();
            }
        });

        async function pollNotifications() {
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
                renderList(list, data.notifications ?? []);
                updateBadge(badge, data.unread_count ?? 0);
                updateMarkAllButton(markAllBtn, data.unread_count ?? 0);
            } catch (e) {
                // Ignore transient network errors.
            }
        }

        window.setInterval(pollNotifications, POLL_INTERVAL_MS);
    }

    document.addEventListener('DOMContentLoaded', () => {
        ensureModalOnBody();
        bindModalEvents();

        document.querySelectorAll('[data-customer-notifications]').forEach((root) => {
            if (root instanceof HTMLElement) {
                bindRoot(root);
            }
        });
    });
})();
