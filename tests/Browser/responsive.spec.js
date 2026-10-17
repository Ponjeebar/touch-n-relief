import { expect, test } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { join } from 'node:path';
import { execFileSync } from 'node:child_process';

const password = 'AuditPassword9';
const viewports = [
    { name: 'mobile-360', width: 360, height: 800 },
    { name: 'mobile-390', width: 390, height: 844 },
    { name: 'tablet-portrait', width: 768, height: 1024 },
    { name: 'tablet-landscape', width: 1024, height: 768 },
    { name: 'desktop', width: 1366, height: 768 },
    { name: 'desktop-wide', width: 1920, height: 1080 },
];
const themes = ['light', 'dark'];
const accounts = {
    customer: { login: 'customer@browser.test', landing: '/', paths: ['/', '/booking', '/profile'] },
    receptionist: {
        login: 'receptionist@browser.test',
        landing: '/receptionist-dashboard',
        paths: [
            '/receptionist-dashboard',
            '/appointments',
            '/ongoing-sessions',
            '/completed-sessions',
            '/therapist-tracking',
            '/client-records',
            '/services',
        ],
    },
    admin: {
        login: 'admin@browser.test',
        landing: '/dashboard',
        paths: ['/dashboard', '/reporting', '/activity-log', '/users', '/landing-settings', '/system-settings', '/services'],
    },
};

async function setTheme(page, theme) {
    await page.addInitScript((mode) => {
        localStorage.setItem('tnr-theme-preference-version', 'light-default-v1');
        localStorage.setItem('tnr-theme', mode);
    }, theme);
}

async function login(page, account) {
    await page.goto('/login');
    const form = page.locator('form[action$="/login"]');
    await form.locator('input[name="login"]').fill(account.login);
    await form.locator('input[name="password"]').fill(password);
    await Promise.all([
        page.waitForURL((url) => !url.pathname.endsWith('/login')),
        form.locator('button[type="submit"]').click(),
    ]);
    await expect(page).toHaveURL(new RegExp(`${account.landing.replaceAll('/', '\\/')}(?:\\?.*)?$`));
}

async function dismissOptionalTour(page) {
    const close = page.locator('.driver-popover-close-btn');
    if (await close.isVisible().catch(() => false)) await close.click();
}

async function assertPageFits(page, path, theme, viewport, role, consoleErrors) {
    const response = await page.goto(path, { waitUntil: 'domcontentloaded' });
    expect(response?.status(), `${path} should not return an HTTP error`).toBeLessThan(400);
    await dismissOptionalTour(page);
    await page.waitForTimeout(150);

    const layout = await page.evaluate(() => ({
        documentWidth: document.documentElement.scrollWidth,
        viewportWidth: document.documentElement.clientWidth,
        bodyWidth: document.body.scrollWidth,
        theme: document.documentElement.getAttribute('data-theme') || 'light',
    }));
    expect(layout.documentWidth, `${path} has horizontal document overflow`).toBeLessThanOrEqual(layout.viewportWidth + 2);
    expect(layout.bodyWidth, `${path} has horizontal body overflow`).toBeLessThanOrEqual(layout.viewportWidth + 2);
    expect(layout.theme).toBe(theme);
    expect(consoleErrors, `${path} emitted browser console errors`).toEqual([]);

    if (role === 'admin' && path === '/system-settings' && viewport.width > 1024) {
        await page.locator('.sidebar').hover();
        await expect(page.getByText('Overview', { exact: true })).toBeVisible();
        await expect(page.getByText('Daily work', { exact: true })).toBeVisible();
        await expect(page.getByText('Administration', { exact: true })).toBeVisible();
        await expect(page.getByText('Content and settings', { exact: true })).toBeVisible();
    }

    const captureReviewSet = (viewport.name === 'mobile-390' && theme === 'dark')
        || (viewport.name === 'desktop' && theme === 'light');
    if (captureReviewSet) {
        const directory = join('storage', 'app', 'browser-audit', viewport.name, theme);
        mkdirSync(directory, { recursive: true });
        const name = path === '/' ? 'landing' : path.slice(1).replaceAll('/', '-');
        await page.screenshot({ path: join(directory, `${role}-${name}.png`), fullPage: true });
    }
}

for (const viewport of viewports) {
    for (const theme of themes) {
        for (const [role, account] of Object.entries(accounts)) {
            test(`${role} pages fit ${viewport.name} in ${theme} mode`, async ({ page }) => {
                await page.setViewportSize({ width: viewport.width, height: viewport.height });
                await setTheme(page, theme);
                const consoleErrors = [];
                page.on('console', (message) => {
                    if (message.type() === 'error') consoleErrors.push(message.text());
                });
                await login(page, account);

                for (const path of account.paths) {
                    consoleErrors.length = 0;
                    await assertPageFits(page, path, theme, viewport, role, consoleErrors);
                }
            });
        }
    }
}

test('package and membership section stays readable on mobile and desktop', async ({ page }) => {
    await login(page, accounts.customer);

    for (const setup of [
        { name: 'mobile-390-dark', viewport: { width: 390, height: 844 }, theme: 'dark' },
        { name: 'desktop-light', viewport: { width: 1366, height: 768 }, theme: 'light' },
    ]) {
        await page.setViewportSize(setup.viewport);
        await page.emulateMedia({ reducedMotion: 'reduce' });
        await page.goto(`/?ui_audit=packages-${setup.name}#packages`, { waitUntil: 'domcontentloaded' });
        await page.evaluate((theme) => {
            localStorage.setItem('tnr-theme-preference-version', 'light-default-v1');
            localStorage.setItem('tnr-theme', theme);
            if (theme === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
            else document.documentElement.removeAttribute('data-theme');
        }, setup.theme);
        await dismissOptionalTour(page);

        await expect.poll(() => page.locator('html').evaluate((element) => element.getAttribute('data-theme') || 'light')).toBe(setup.theme);

        const section = page.locator('#packages');
        await section.scrollIntoViewIfNeeded();
        const eyebrowContrast = await section.locator('.catalog-section-heading .section-eyebrow').evaluate((element) => {
            const parseRgb = (value) => value.match(/[\d.]+/g).slice(0, 3).map(Number);
            const luminance = (rgb) => {
                const channels = rgb.map((value) => {
                    const channel = value / 255;
                    return channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
                });
                return (0.2126 * channels[0]) + (0.7152 * channels[1]) + (0.0722 * channels[2]);
            };
            const foreground = luminance(parseRgb(getComputedStyle(element).color));
            const background = luminance(parseRgb(getComputedStyle(element.closest('.package-membership')).backgroundColor));
            return (Math.max(foreground, background) + 0.05) / (Math.min(foreground, background) + 0.05);
        });
        expect(eyebrowContrast).toBeGreaterThanOrEqual(4.5);
        const packageCount = await section.locator('.package-card').count();
        expect(packageCount).toBeGreaterThan(0);
        await expect(section.getByText('Included treatments')).toHaveCount(packageCount);
        expect(await section.getByText('Member rate').count()).toBeGreaterThan(0);
        await expect(section.locator('.membership-terms')).toContainText('days validity');
        await expect(section.locator('.membership-price .btn')).toBeVisible();

        const widths = await section.evaluate((element) => ({
            section: element.scrollWidth,
            viewport: document.documentElement.clientWidth,
        }));
        expect(widths.section).toBeLessThanOrEqual(widths.viewport + 2);

        const directory = join('storage', 'app', 'browser-audit', 'package-membership');
        mkdirSync(directory, { recursive: true });
        await section.screenshot({ path: join(directory, `${setup.name}.png`) });
    }
});

test('profile dialog traps keyboard focus and restores it when closed', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await setTheme(page, 'dark');
    await login(page, accounts.admin);

    const menuButton = page.locator('#tnr-profile-menu-trigger');
    await menuButton.focus();
    await page.keyboard.press('Enter');
    const openButton = page.locator('#tnr-profile-open-modal');
    await expect(openButton).toBeVisible();
    await openButton.focus();
    await page.keyboard.press('Enter');

    const dialog = page.locator('#tnr-my-profile-modal');
    await expect(dialog).toBeVisible();
    await expect.poll(() => dialog.evaluate((element) => element.contains(document.activeElement))).toBe(true);

    const focusable = dialog.locator('a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])');
    const last = focusable.last();
    await last.focus();
    await page.keyboard.press('Tab');
    await expect.poll(() => dialog.evaluate((element) => element.contains(document.activeElement))).toBe(true);

    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(menuButton).toBeFocused();
});

test('report period menus keep custom dates hidden until requested', async ({ page }) => {
    test.setTimeout(120000);
    await login(page, accounts.admin);

    for (const width of [360, 768, 1366]) {
        for (const theme of themes) {
            await page.setViewportSize({ width, height: 900 });
            await setTheme(page, theme);
            await page.goto('/reporting', { waitUntil: 'networkidle' });
            await dismissOptionalTour(page);
            const range = page.locator('#repCustomRange');
            const custom = page.locator('#repPeriod button[data-period="custom"]');
            await expect(range).toBeHidden();
            await custom.click();
            await expect(range).toBeVisible();
            await expect(custom).toHaveAttribute('aria-expanded', 'true');

            for (const period of ['daily', 'weekly', 'monthly', 'yearly']) {
                const summary = page.locator(`#repPeriod summary[data-period="${period}"]`);
                const menu = page.locator(`[data-period-options="${period}"]`);
                await summary.click();
                await expect(range).toBeHidden();
                await expect(menu).toBeVisible();
                const bounds = await menu.boundingBox();
                expect(bounds.x).toBeGreaterThanOrEqual(0);
                expect(bounds.x + bounds.width).toBeLessThanOrEqual(width);
                await menu.locator('button').first().click();
                await expect(page).toHaveURL(new RegExp(`period=${period}`));
                await expect(summary).toHaveClass(/active/);
                await expect(menu).toBeHidden();
                await expect(page.locator('#repExportLink')).toHaveAttribute('href', new RegExp(`period=${period}`));
            }

            const monthly = page.locator('summary[data-period="monthly"]');
            await monthly.focus();
            await page.keyboard.press('Enter');
            const monthMenu = page.locator('[data-period-options="monthly"]');
            await expect(monthMenu).toBeVisible();
            const scrollable = await monthMenu.evaluate((el) => el.scrollHeight > el.clientHeight);
            expect(scrollable).toBe(true);
            await page.keyboard.press('Escape');
            await expect(monthMenu).toBeHidden();
            await expect(monthly).toBeFocused();
            await monthly.click();
            await page.locator('h1').click();
            await expect(monthMenu).toBeHidden();
            await custom.click();
            await custom.click();
            await expect(range).toBeHidden();
        }
    }

    await page.goto('/reporting?period=custom&date_from=2026-10-01&date_to=2026-10-05', { waitUntil: 'networkidle' });
    await expect(page.locator('#repCustomRange')).toBeHidden();
    await page.locator('#repPeriod button[data-period="custom"]').click();
    await expect(page.locator('#repCustomRange')).toBeVisible();
    await expect(page.locator('#repCustomRange input[name="date_from"]')).toHaveValue('2026-10-01');
    await page.locator('#repCustomRange input[name="date_from"]').fill('2026-10-02');
    await page.locator('#repCustomRange button[type="submit"]').click();
    await expect(page).toHaveURL(/period=custom&date_from=2026-10-02&date_to=2026-10-05/);
    await expect(page.locator('#repCustomRange')).toBeHidden();
    await expect(page.locator('#repExportLink')).toHaveAttribute('href', /date_from=2026-10-02/);
});

test('report downloads keep the reporting overview visible', async ({ page }) => {
    await page.setViewportSize({ width: 1366, height: 768 });
    await setTheme(page, 'light');
    await login(page, accounts.admin);
    await page.goto('/reporting', { waitUntil: 'networkidle' });

    for (const selector of ['#repExportLink', '#repPdfLink', '#repBackupLink']) {
        const [download] = await Promise.all([
            page.waitForEvent('download'),
            page.locator(selector).click(),
        ]);

        expect(await download.suggestedFilename()).toBeTruthy();
        await expect(page).toHaveURL(/\/reporting(?:\?.*)?$/);
        await expect(page.locator('.page-transition-skeleton')).toBeHidden();
        await expect(page.locator('body')).not.toHaveAttribute('aria-busy', 'true');
    }
});

test('authentication panels expose only the visible form to keyboard focus', async ({ page }) => {
    for (const viewport of [{ width: 390, height: 844 }, { width: 1366, height: 768 }]) {
        const mobile = viewport.width <= 700;
        await page.setViewportSize(viewport);
        await page.goto('/login');

        const signUp = page.locator('.form-container.sign-up');
        const signIn = page.locator('.form-container.sign-in');
        const loginInput = page.locator('.auth-login-panel input[name="login"]');
        const loginPassword = page.locator('.auth-login-panel input[name="password"]');
        const registrationName = page.locator('#register-form input[name="name"]');

        await expect(signUp).toHaveAttribute('inert', '');
        await expect(signIn).not.toHaveAttribute('inert', '');
        await loginInput.focus();
        await page.keyboard.press('Tab');
        await expect(loginPassword).toBeFocused();
        await registrationName.focus();
        await expect(registrationName).not.toBeFocused();

        await page.locator(mobile ? '.auth-login-panel [data-auth-panel="register"]' : '#register').click();
        await expect(signUp).not.toHaveAttribute('inert', '');
        await expect(signIn).toHaveAttribute('inert', '');
        await expect(registrationName).toBeFocused();
        await loginInput.focus();
        await expect(loginInput).not.toBeFocused();

        await page.locator(mobile ? '#register-form [data-auth-panel="login"]' : '#login').click();
        await expect(loginInput).toBeFocused();
        await page.locator('[data-auth-forgot-open="true"]').click();
        await expect(page.locator('.auth-login-panel')).toHaveAttribute('inert', '');
        await expect(page.locator('.auth-forgot-panel')).not.toHaveAttribute('inert', '');
        await expect(page.locator('.auth-forgot-panel input[name="email"]')).toBeFocused();
    }
});

test('completed appointment rebooking stays usable on mobile and desktop', async ({ page }) => {
    await login(page, accounts.customer);

    for (const setup of [
        { viewport: { width: 390, height: 844 }, theme: 'dark' },
        { viewport: { width: 768, height: 1024 }, theme: 'light' },
        { viewport: { width: 1366, height: 768 }, theme: 'light' },
    ]) {
        await page.setViewportSize(setup.viewport);
        await page.evaluate((theme) => localStorage.setItem('tnr-theme', theme), setup.theme);
        await page.goto('/', { waitUntil: 'domcontentloaded' });
        await dismissOptionalTour(page);
        const visibleAppointmentsTrigger = page.locator('[data-tnr-open-transactions]:visible');
        if (await visibleAppointmentsTrigger.count() === 0) {
            await page.locator('[data-user-menu]:visible .nav-user').click();
        }
        await page.locator('[data-tnr-open-transactions]:visible').first().click();
        await page.locator('[data-txn-group="history"]').click();

        const card = page.locator('.txn-card').filter({ hasText: 'Hot Stone' });
        const rebook = card.locator('.txn-rebook-btn');
        await expect(rebook).toBeVisible();
        await expect(rebook).toHaveText(/Book again/);

        const href = await rebook.getAttribute('href');
        expect(href).toContain('service=Hot%20Stone');
        expect(href).toContain('therapist=Angela%20Fernandez');

        const [cardBox, buttonBox] = await Promise.all([card.boundingBox(), rebook.boundingBox()]);
        expect(cardBox).not.toBeNull();
        expect(buttonBox).not.toBeNull();
        expect(buttonBox.x).toBeGreaterThanOrEqual(cardBox.x);
        expect(buttonBox.x + buttonBox.width).toBeLessThanOrEqual(cardBox.x + cardBox.width + 1);

        await page.locator('[data-tnr-txn-close="true"]:visible').last().click();
    }
});

test('service then therapist then date and time stays consistent when choices change', async ({ page }, testInfo) => {
    await login(page, accounts.customer);
    await page.goto('/booking');
    const tourKey = await page.locator('#tnr-customer-tour-config').evaluate(config =>
        `tnr-system-tour:${config.dataset.customerTourUser}:${config.dataset.customerTourRole}:${config.dataset.customerTourPage}:v3`);
    await page.evaluate(key => localStorage.setItem(key, 'complete'), tourKey);
    for (const setup of [
        { width: 390, height: 844, theme: 'dark' },
        { width: 768, height: 1024, theme: 'light' },
        { width: 1366, height: 768, theme: 'light' },
    ]) {
        await page.setViewportSize(setup);
        await page.evaluate(theme => localStorage.setItem('tnr-theme', theme), setup.theme);
        await page.goto('/booking');
        const grid = page.locator('#booking-grid');
        await expect(grid).toHaveAttribute('data-mobile-step', 'service');
        await expect(page.locator('[data-booking-progress] span')).toHaveText(['Service', 'Therapist', 'Date and time']);
        await page.locator('.service-item').first().click();
        await expect(grid).toHaveAttribute('data-mobile-step', 'therapist');
        const therapist = page.locator('.therapist-item:not(.is-unavailable):not(.therapist-item-any)').first();
        await therapist.click();
        const chosen = await page.locator('input[name="therapist"]:checked').inputValue();
        await expect(grid).toHaveAttribute('data-mobile-step', 'schedule');
        await expect(page.locator('.booking-field-date')).toBeVisible();
        await expect(page.locator('.booking-field-time')).toBeVisible();
        const date = page.locator('#booking_date');
        const monday = await date.evaluate(input => {
            const value = new Date(input.min + 'T12:00:00');
            value.setDate(value.getDate() + ((8 - value.getDay()) % 7 || 7));
            return value.toISOString().slice(0, 10);
        });
        await date.fill(monday);
        await expect(page.locator('input[name="therapist"]:checked')).toHaveValue(chosen);
        await expect(page.locator('#time-slots .time-slot:not([disabled])').first()).toBeVisible();
        await page.locator('#time-slots .time-slot:not([disabled])').first().click();
        await expect(page.locator('#time_slot')).not.toHaveValue('');
        const nextMonday = new Date(monday + 'T12:00:00');
        nextMonday.setDate(nextMonday.getDate() + 7);
        await date.fill(nextMonday.toISOString().slice(0, 10));
        await expect(page.locator('#time_slot')).toHaveValue('');
        await expect(page.locator('input[name="service"]:checked')).toHaveCount(1);
        await expect(page.locator('input[name="therapist"]:checked')).toHaveValue(chosen);
        await page.locator('#booking-mobile-back').click();
        await expect(grid).toHaveAttribute('data-mobile-step', 'therapist');
        await page.locator('#booking-mobile-back').click();
        await expect(grid).toHaveAttribute('data-mobile-step', 'service');
        await page.locator('.service-item').nth(1).click();
        await expect(page.locator('input[name="therapist"]:checked')).toHaveCount(0);
        await expect(page.locator('#time_slot')).toHaveValue('');
        await page.locator('.therapist-item:not(.is-unavailable):not(.therapist-item-any)').first().click();
        await expect(grid).toHaveAttribute('data-mobile-step', 'schedule');
        await expect(date).toHaveValue('');
        await expect(page.locator('.booking-policy-note')).toContainText('at least 30 minutes');
        await page.screenshot({ path: testInfo.outputPath('booking-' + setup.width + '.png') });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2)).toBe(true);
    }
});

test('any available therapist reaches payment review without choosing a person', async ({ page }, testInfo) => {
    await login(page, accounts.customer);
    for (const setup of [
        { width: 390, height: 844, theme: 'dark' },
        { width: 768, height: 1024, theme: 'light' },
        { width: 1366, height: 768, theme: 'light' },
    ]) {
        await page.setViewportSize(setup);
        await page.evaluate(theme => localStorage.setItem('tnr-theme', theme), setup.theme);
        await page.goto('/booking');
        await dismissOptionalTour(page);
        await page.locator('.service-item').first().click();
        const option = page.locator('.therapist-item-any');
        await expect(option).toContainText('Any available therapist');
        await option.click();
        await expect(page.locator('#booking-grid')).toHaveAttribute('data-mobile-step', 'schedule');
        const date = page.locator('#booking_date');
        const monday = await date.evaluate(input => {
            const next = new Date(input.min + 'T12:00:00');
            next.setDate(next.getDate() + ((8 - next.getDay()) % 7 || 7));
            return next.toISOString().slice(0, 10);
        });
        await date.fill(monday);
        await expect(page.locator('#time-slots .time-slot:not([disabled])').first()).toBeVisible();
        await expect(option.locator('input')).toBeChecked();
        await page.locator('#time-slots .time-slot:not([disabled])').first().click();
        await page.locator('.booking-submit').click();
        await expect(page.locator('#bkConfirmModal')).not.toHaveClass(/bk-hidden/);
        await expect(page.locator('#bkSumTherapist')).toHaveText('Any available therapist (assigned when you reserve)');
        await expect(page.locator('#bkSumDate')).not.toBeEmpty();
        await expect(page.locator('#bkSumTime')).not.toBeEmpty();
        await page.screenshot({ path: testInfo.outputPath('any-therapist-' + setup.width + '.png') });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2)).toBe(true);
    }
});

test('date selection rejects an unavailable therapist and ignores an older schedule response', async ({ page }) => {
    await login(page, accounts.customer);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/booking');
    await dismissOptionalTour(page);
    await page.locator('.service-item').first().click();
    const candidate = page.locator('.therapist-item:not(.therapist-item-any)').first();
    await candidate.click();
    const name = await page.locator('input[name="therapist"]:checked').inputValue();
    const date = page.locator('#booking_date');
    let delayUnavailable = false;
    await page.route('**/booking/therapist-availability?*', async route => {
        const unavailable = new URL(route.request().url()).searchParams.get('booking_date') === '2030-01-01';
        if (unavailable && delayUnavailable) await new Promise(resolve => setTimeout(resolve, 500));
        await route.fulfill({ json: { therapists: {
            [name]: { bookable: !unavailable, status: unavailable ? 'off-duty' : 'available', label: unavailable ? 'Off duty' : '' },
        } } });
    });
    await date.fill('2030-01-01');
    await expect(page.locator('input[name="therapist"]:checked')).toHaveCount(0);
    await expect(page.locator('#time_slot')).toHaveValue('');
    await expect(page.locator('#time-slots')).toBeEmpty();
    await expect(page.locator('#booking-toast-msg')).toContainText('Choose a therapist available on this date');
    await date.fill('');
    await page.locator('#booking-mobile-back').click();
    await candidate.click();
    delayUnavailable = true;
    await date.fill('2030-01-01');
    await date.fill('2030-01-02');
    await page.waitForTimeout(700);
    await expect(page.locator('input[name="therapist"]:checked')).toHaveValue(name);
    await expect(candidate).not.toHaveClass(/is-unavailable/);
    await expect(page.locator('#bkConfirmModal')).toHaveClass(/bk-hidden/);
});

test('both staff roles can choose any available therapist for walk-ins', async ({ page }) => {
    for (const role of ['admin', 'receptionist']) {
        await login(page, accounts[role]);
        for (const viewport of [{ width: 390, height: 844 }, { width: 768, height: 1024 }, { width: 1366, height: 768 }]) {
            await page.setViewportSize(viewport);
            await page.goto('/appointments');
            await dismissOptionalTour(page);
            await page.locator('[data-mobile-add-appointment]:visible, .clients-add-appointment-btn:visible').first().click();
            await page.locator('[data-pick-client-type="walk_in"]').click();
            const therapist = page.locator('#add-therapist');
            await expect(therapist).toHaveValue('');
            await expect(therapist.locator('option:checked')).toHaveText('Any available therapist');
            await expect(page.locator('#add-therapist-hint')).toBeVisible();
            await page.locator('[data-add-booking-mode="immediate"]').click();
            await expect(therapist).toHaveValue('');
            await expect(therapist).toBeEnabled();
            await expect(page.locator('[data-add-payment-type="downpayment"]')).toBeDisabled();
            await expect(page.locator('[data-add-payment-method="paymongo"]')).toBeDisabled();
            await expect(page.locator('#add-appointment-save-btn')).toHaveText('Start walk-in now');
            expect(await page.locator('.add-appointment-modal-content').evaluate(el => el.scrollWidth <= el.clientWidth + 2)).toBe(true);
            await page.locator('#close-add-appointment-modal').click();
        }
        await page.context().clearCookies();
    }
});

test('staff immediate walk-in shows the calculated session and enforced payment controls', async ({ page }) => {
    await login(page, accounts.receptionist);

    for (const setup of [
        { viewport: { width: 390, height: 844 }, theme: 'dark' },
        { viewport: { width: 1366, height: 768 }, theme: 'light' },
    ]) {
        await page.setViewportSize(setup.viewport);
        await page.evaluate((theme) => localStorage.setItem('tnr-theme', theme), setup.theme);
        await page.goto('/appointments', { waitUntil: 'domcontentloaded' });
        await dismissOptionalTour(page);

        await page.locator('[data-mobile-add-appointment]:visible, .clients-add-appointment-btn:visible').first().click();
        await page.locator('[data-pick-client-type="registered"]').click();
        await page.locator('[data-add-booking-mode="immediate"]').click();
        await page.locator('#add-service').selectOption({ index: 1 });

        await expect(page.locator('#add-immediate-start-time')).toBeVisible();
        await expect(page.locator('#add-immediate-window')).toContainText('minutes');
        await expect(page.locator('input[name="immediate_confirmed"]')).toBeAttached();
        await expect(page.locator('input[name="immediate_confirmed"]')).toBeEnabled();
        await expect(page.locator('[data-add-payment-type="downpayment"]')).toBeDisabled();
        await expect(page.locator('[data-add-payment-type="full"]')).toHaveClass(/is-active/);
        await expect(page.locator('[data-add-payment-method="paymongo"]')).toBeDisabled();
        await expect(page.locator('[data-add-payment-method="cash_counter"]')).toHaveClass(/is-active/);
        await expect(page.locator('#add-appointment-save-btn')).toHaveText('Start walk-in now');

        await page.evaluate(() => window.openImmediatePaymentConfirmation());
        const paymentDialog = page.locator('#payment-start-modal');
        await expect(paymentDialog).toBeVisible();
        await expect(paymentDialog.locator('#payment-start-due')).not.toHaveText('₱0.00');
        await paymentDialog.locator('#payment-start-tendered').fill('1000');
        await expect(paymentDialog.locator('#payment-start-change')).not.toHaveText('₱0.00');
        const paymentModalFits = await paymentDialog.locator('.balance-modal-content').evaluate((element) => (
            element.scrollWidth <= element.clientWidth + 2
        ));
        expect(paymentModalFits).toBe(true);
        await paymentDialog.locator('[data-close-payment-start="true"]').last().click();

        const modalFits = await page.locator('.add-appointment-modal-content').evaluate((element) => (
            element.scrollWidth <= element.clientWidth + 2
        ));
        expect(modalFits).toBe(true);

        await page.locator('#close-add-appointment-modal').click();
    }
});

test('scheduled start confirms exact balance, traps focus, and restores the trigger', async ({ page }) => {
    execFileSync('php', ['tests/Support/refresh-browser-start.php'], { stdio: 'pipe' });
    await page.setViewportSize({ width: 390, height: 844 });
    await setTheme(page, 'dark');
    await login(page, accounts.receptionist);
    await page.goto('/appointments', { waitUntil: 'domcontentloaded' });
    await dismissOptionalTour(page);

    const trigger = page.locator('[data-open-payment-start="true"][data-service="Aromatherapy"]');
    await expect(page.locator('[data-pill-balance-due="true"]')).toContainText('Balance Due');
    await expect(trigger).toBeEnabled();
    await trigger.click();

    const dialog = page.locator('#payment-start-modal');
    await expect(dialog).toBeVisible();
    await expect(dialog.locator('#payment-start-total')).toHaveText('₱100.00');
    await expect(dialog.locator('#payment-start-paid')).toHaveText('₱50.00');
    await expect(dialog.locator('#payment-start-due')).toHaveText('₱50.00');
    await expect(dialog.locator('#payment-start-verification')).toContainText('Only the balance due is recorded as sales');
    await dialog.locator('#payment-start-tendered').fill('100');
    await expect(dialog.locator('#payment-start-change')).toHaveText('₱50.00');

    const last = dialog.locator('button:not([disabled]), input:not([disabled]):not([type="hidden"])').last();
    await last.focus();
    await page.keyboard.press('Tab');
    await expect.poll(() => dialog.evaluate((element) => element.contains(document.activeElement))).toBe(true);

    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(trigger).toBeFocused();
});

test('staff no-show confirmation stays usable on mobile, tablet, and desktop', async ({ page }) => {
    await login(page, accounts.receptionist);

    for (const setup of [
        { viewport: { width: 390, height: 844 }, theme: 'dark' },
        { viewport: { width: 768, height: 1024 }, theme: 'light' },
        { viewport: { width: 1366, height: 768 }, theme: 'light' },
    ]) {
        await page.setViewportSize(setup.viewport);
        await page.evaluate((theme) => localStorage.setItem('tnr-theme', theme), setup.theme);
        await page.goto('/appointments', { waitUntil: 'domcontentloaded' });
        await dismissOptionalTour(page);

        const trigger = page.locator('[data-open-no-show="true"]').first();
        await expect(trigger).toBeVisible();
        await trigger.click();

        const dialog = page.locator('#no-show-appointment-modal');
        await expect(dialog).toBeVisible();
        await expect(dialog.locator('#no-show-client')).toHaveText('Browser Customer');
        await expect(dialog.locator('#no-show-impact')).toContainText('Confirming will update the count');
        await expect(dialog.locator('button[type="submit"]')).toBeFocused();

        const box = await dialog.locator('.no-show-modal-content').boundingBox();
        expect(box).not.toBeNull();
        expect(box.x).toBeGreaterThanOrEqual(0);
        expect(box.x + box.width).toBeLessThanOrEqual(setup.viewport.width + 1);

        await page.keyboard.press('Escape');
        await expect(dialog).toBeHidden();
        await expect(trigger).toBeFocused();
    }
});
