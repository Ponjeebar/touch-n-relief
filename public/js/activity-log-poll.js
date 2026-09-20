(function () {
    const root = document.querySelector('[data-activity-log-page]');
    if (!root) return;

    const pollUrl = root.dataset.pollUrl;
    if (!pollUrl) return;

    const POLL_INTERVAL_MS = 30000;
    let pollInFlight = false;
    let lastLatestId = Number(root.dataset.latestId || 0);

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function roleClass(role) {
        if (role === 'admin') return 'activity-role-admin';
        if (role === 'receptionist') return 'activity-role-receptionist';
        if (role === 'user') return 'activity-role-user';
        return 'activity-role-other';
    }

    function renderRow(row, isNew) {
        return `
            <tr class="${isNew ? 'activity-row-new' : ''}" data-activity-id="${row.id}">
                <td class="activity-when">
                    <span class="activity-date">${escapeHtml(row.when_date)}</span>
                    <span class="activity-time">${escapeHtml(row.when_time)}</span>
                </td>
                <td>${escapeHtml(row.user_name)}</td>
                <td class="activity-role-cell">
                    <span class="activity-role ${roleClass(row.role)}">${escapeHtml(row.role_label)}</span>
                </td>
                <td>${escapeHtml(row.action_label)}</td>
                <td class="activity-desc">${escapeHtml(row.description)}</td>
            </tr>
        `;
    }

    function updateStats(stats) {
        if (!stats) return;

        const map = {
            total: stats.total,
            today: stats.today,
            receptionist_today: stats.receptionist_today,
            admin_today: stats.admin_today,
            customer_today: stats.customer_today,
        };

        Object.keys(map).forEach((key) => {
            const el = document.querySelector(`[data-activity-stat="${key}"]`);
            if (el) {
                el.textContent = Number(map[key] ?? 0).toLocaleString();
            }
        });
    }

    function updateTable(rows) {
        const tbody = document.querySelector('[data-activity-log-tbody]');
        const empty = document.querySelector('[data-activity-log-empty]');
        const wrap = document.querySelector('[data-activity-log-table-wrap]');

        if (!tbody) return;

        if (!Array.isArray(rows) || rows.length === 0) {
            if (wrap) wrap.hidden = true;
            if (empty) empty.hidden = false;
            tbody.innerHTML = '';
            return;
        }

        if (wrap) wrap.hidden = false;
        if (empty) empty.hidden = true;

        const newIds = new Set();
        if (lastLatestId > 0) {
            rows.forEach((row) => {
                if (Number(row.id) > lastLatestId) {
                    newIds.add(Number(row.id));
                }
            });
        }

        tbody.innerHTML = rows.map((row) => renderRow(row, newIds.has(Number(row.id)))).join('');

        if (newIds.size > 0) {
            window.setTimeout(() => {
                tbody.querySelectorAll('.activity-row-new').forEach((row) => {
                    row.classList.remove('activity-row-new');
                });
            }, 4000);
        }
    }

    async function pollActivityLog() {
        if (pollInFlight) return;
        pollInFlight = true;

        try {
            const url = new URL(pollUrl, window.location.origin);
            const params = new URLSearchParams(window.location.search);
            params.forEach((value, key) => url.searchParams.set(key, value));

            const response = await fetch(url.toString(), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) return;

            const data = await response.json();
            updateStats(data.stats);
            updateTable(data.rows);

            if (data.latest_id) {
                lastLatestId = Number(data.latest_id);
                root.dataset.latestId = String(lastLatestId);
            }
        } catch (e) {
            // Ignore transient network errors.
        } finally {
            pollInFlight = false;
        }
    }

    window.setInterval(pollActivityLog, POLL_INTERVAL_MS);
})();
