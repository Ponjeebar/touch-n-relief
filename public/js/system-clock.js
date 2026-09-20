/**
 * Keep browser UI aligned with the Laravel server clock.
 */
window.initSystemClock = (options = {}) => {
    const serverNowIso = options.serverNowIso;
    const targets = document.querySelectorAll(options.selector || '[data-live-clock]');

    if (!serverNowIso) {
        return {
            nowMs: () => Date.now(),
            sync: () => {},
            getOffsetMs: () => 0,
        };
    }

    let offsetMs = Date.parse(serverNowIso) - Date.now();

    const format = (date) => date.toLocaleString(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        second: '2-digit',
        hour12: true,
    });

    const tick = () => {
        const now = new Date(Date.now() + offsetMs);
        targets.forEach((element) => {
            element.textContent = format(now);
        });
    };

    tick();
    window.setInterval(tick, 1000);

    return {
        nowMs: () => Date.now() + offsetMs,
        sync: (iso) => {
            if (!iso) {
                return;
            }

            offsetMs = Date.parse(iso) - Date.now();
            tick();
        },
        getOffsetMs: () => offsetMs,
    };
};
