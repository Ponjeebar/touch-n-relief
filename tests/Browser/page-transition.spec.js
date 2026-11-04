import { expect, test } from '@playwright/test';

test.setTimeout(180_000);

async function login(page, role = 'customer') {
    await page.goto('/login');
    await page.locator('.auth-login-panel [name="login"]').fill(`${role}@browser.test`);
    await page.locator('.auth-login-panel [name="password"]').fill('AuditPassword9');
    await page.locator('.auth-login-panel button[type="submit"]').click({ noWaitAfter: true });
    await expect(page).not.toHaveURL(/\/login$/, { timeout: 30_000 });
    const skip = page.getByRole('button', { name: 'Skip tour', exact: true });
    if (await skip.isVisible()) await skip.click();
}

async function inspectSlowNavigation(page, path, trigger, mode, testInfo, screenshot) {
    const linkElement = await trigger.elementHandle();
    // Hold the destination in an iframe so Chrome's navigation interception does
    // not suspend inspection of the parent document's loading UI.
    await trigger.evaluate(link => {
        const frame = document.createElement('iframe');
        frame.name = 'loading-preview';
        frame.hidden = true;
        document.body.appendChild(frame);
        document.addEventListener('click', function redirectPreview(event) {
            if (event.target.closest('a') === link) link.target = frame.name;
        }, { once: true });
    });
    let release;
    const pending = new Promise(resolve => { release = resolve; });
    let finish;
    let intercepted = false;
    const finished = new Promise(resolve => { finish = resolve; });
    const handler = async route => {
        intercepted = true;
        try { await pending; await route.continue(); }
        finally { finish(); }
    };
    await page.route('**' + path, handler);
    const overlay = page.locator('.page-transition-skeleton');
    try {
        await trigger.click({ noWaitAfter: true });
        await expect(overlay).toBeVisible();
        await expect(overlay).toHaveAttribute('data-loading-layout', mode);
        await expect(overlay.getByRole('status')).toContainText('Loading');
        await expect(page.locator('body')).toHaveAttribute('aria-busy', 'true');
        await expect.poll(() => overlay.locator('.page-skeleton-logo').evaluate(img => img.complete && img.naturalWidth > 0)).toBe(true);
        expect(await overlay.evaluate(el => el.scrollWidth <= innerWidth + 1)).toBe(true);
        await expect(overlay.locator('.page-skeleton-line').first()).toHaveCSS('animation-name', 'none');
        await page.screenshot({ path: testInfo.outputPath(screenshot), animations: 'disabled' });
    } finally {
        release();
        if (intercepted) await finished;
        await page.unroute('**' + path, handler);
        await page.evaluate(() => window.dispatchEvent(new PageTransitionEvent('pageshow', { persisted: true })));
        await linkElement.evaluate(link => { link.removeAttribute('target'); });
    }
    await expect(overlay).toBeHidden();
    await expect(page.locator('body')).not.toHaveAttribute('aria-busy', 'true');
    if (mode === 'profile' && !(await trigger.isVisible())) {
        await page.locator('.nav-user-wrap--desktop .nav-user').click();
    }
    await trigger.click({ noWaitAfter: true });
    await expect(page).toHaveURL(new RegExp(path + '$'), { timeout: 30_000 });
    await expect(page.locator('.page-transition-skeleton')).toBeHidden();
}

test('guest loading uses the real brand and account layout, and resets on return', async ({ page }, testInfo) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/');
    await inspectSlowNavigation(page, '/login', page.locator('.nav-guest-actions--desktop .nav-login'), 'auth', testInfo, 'account-loading.png');
    await page.goBack();
    await expect(page.locator('.page-transition-skeleton')).toBeHidden();
    await expect(page.locator('body')).not.toHaveAttribute('aria-busy', 'true');
    await page.locator('.landing-section-link[data-landing-section="services"]').click();
    await expect(page.locator('.page-transition-skeleton')).toBeHidden();
});

test('customer loading follows booking and profile destinations in both themes', async ({ page }, testInfo) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await login(page);
    for (const width of [320, 768, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/');
        await page.evaluate(dark => {
            if (dark) document.documentElement.setAttribute('data-theme', 'dark');
            else document.documentElement.removeAttribute('data-theme');
        }, width === 320);
        const booking = page.locator('a[href$="/booking"]:visible').first();
        await inspectSlowNavigation(page, '/booking', booking, 'booking', testInfo, `booking-loading-${width}.png`);
        // Profile entry points differ between the booking desktop and mobile views.
        await page.goto('/profile');
        await inspectSlowNavigation(page, '/', page.getByRole('link', { name: 'Back to home', exact: true }), 'home', testInfo, `home-loading-${width}.png`);
        if (width > 1100) await page.locator('.nav-user-wrap--desktop .nav-user').click();
        const profile = page.locator('a[href$="/profile"]:visible').first();
        await inspectSlowNavigation(page, '/profile', profile, 'profile', testInfo, `profile-loading-${width}.png`);
    }
});

test('staff loading shows records without invented dashboard metrics', async ({ page }, testInfo) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await login(page, 'admin');
    await page.setViewportSize({ width: 1440, height: 900 });
    const operations = page.locator('.nav-group-toggle').filter({ hasText: 'Operations' });
    const appointments = page.locator('.nav-link[href$="/appointments"]').first();
    if (!(await appointments.isVisible()) && await operations.count()) await operations.click();
    await inspectSlowNavigation(page, '/appointments', appointments, 'staff', testInfo, 'staff-records-loading.png');
});
