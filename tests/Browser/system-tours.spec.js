import { expect, test } from '@playwright/test';

test.setTimeout(300_000);
test.use({ actionTimeout: 30_000, navigationTimeout: 60_000 });

async function signIn(page, role) {
    await page.route(/https:\/\/(fonts\.googleapis\.com|fonts\.gstatic\.com)\//, route => route.abort());
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.addInitScript(() => {
        document.addEventListener('DOMContentLoaded', () => {
            const config = document.getElementById('tnr-customer-tour-config');
            if (config) localStorage.setItem(`tnr-system-tour:${config.dataset.customerTourUser}:${config.dataset.customerTourRole}:${config.dataset.customerTourPage}:v3`, 'complete');
        });
    });
    await page.goto('/login');
    const form = page.locator('form[action$="/login"]');
    await form.locator('[name="login"]').fill(`${role}@browser.test`);
    await form.locator('[name="password"]').fill('AuditPassword9');
    await form.locator('button[type="submit"]').click();
    await expect(page).not.toHaveURL(/\/login$/);
}

async function walk(page, width) {
    const titles = [];
    await expect(page.locator('.driver-popover')).toBeVisible();
    for (let index = 0; index < 35; index++) {
        if (!await page.locator('.driver-popover').isVisible()) break;
        titles.push(await page.locator('.driver-popover-title').innerText());
        const bounds = await page.locator('.driver-popover').boundingBox();
        expect(bounds.x).toBeGreaterThanOrEqual(0);
        expect(bounds.x + bounds.width).toBeLessThanOrEqual(width + 2);
        expect(bounds.y).toBeGreaterThanOrEqual(0);
        const active = page.locator('.driver-active-element:not(#driver-dummy-element)');
        if (await active.count()) {
            await expect(active).toBeVisible();
            expect(await active.evaluate(el => !el.closest('[hidden], [aria-hidden="true"]'))).toBe(true);
        }
        await page.locator('.driver-popover-next-btn').click();
    }
    await expect(page.locator('.driver-popover')).toHaveCount(0);
    return titles;
}

const sharedPages = [
    ['/appointments?search=tour-no-matches', 'Review full details'],
    ['/ongoing-sessions', 'Live session board'],
    ['/completed-sessions', 'Completed transaction table'],
    ['/therapist-tracking', 'Therapist directory'],
    ['/client-records', 'Customer list'],
    ['/services', 'Membership'],
];
for (const role of ['admin', 'receptionist']) {
    test(`${role} page guides match the current workspaces and do not submit records`, async ({ page }, testInfo) => {
        await signIn(page, role);
        const mutations = [];
        page.on('request', req => {
            if (!['GET', 'HEAD'].includes(req.method()) && new URL(req.url()).pathname !== '/activity-log/click') mutations.push(req.url());
        });
        const pages = role === 'admin' ? [
            ['/dashboard', 'Today at TouchNRelief'], ...sharedPages,
            ['/users', 'Receptionists and customers'], ['/users?tab=customers', 'Find an account'],
            ['/reporting', 'Reporting period'], ['/landing-settings', 'Contact, hours, and social accounts'],
            ['/system-settings', 'Application recovery export'], ['/activity-log', 'Recorded events'],
            ['/therapist-tracking?manage=1', 'Published team profiles'],
        ] : [['/receptionist-dashboard', 'Operational summary'], ...sharedPages];
        for (const width of [390, 768, 1366]) {
            await page.setViewportSize({ width, height: 900 });
            await page.evaluate(theme => localStorage.setItem('tnr-theme', theme), width === 768 ? 'dark' : 'light');
            for (const [url, expected] of pages) {
                await page.goto(url);
                await expect(page.locator('#tnr-customer-tour-config')).toHaveAttribute('data-customer-tour-role', role);
                await page.locator('#tnr-profile-menu-trigger').click();
                await page.getByRole('menuitem', { name: 'Take a tour', exact: true }).click();
                const titles = await walk(page, width);
                expect(titles, `${role} ${url} at ${width}`).toContain(expected === 'Therapist directory' && width === 390 ? 'Mobile therapist cards' : expected);
                await expect(page.locator('[data-tour-demo]')).toHaveCount(0);
                if (url === '/client-records') {
                    const customer = page.locator('.cr-customer-row-link').first();
                    await expect(customer).toBeVisible();
                    await customer.click();
                    await page.locator('#tnr-profile-menu-trigger').click();
                    await page.getByRole('menuitem', { name: 'Take a tour', exact: true }).click();
                    expect(await walk(page, width)).toContain('Profile and wellness information');
                }
            }
        }
        expect(mutations).toEqual([]);
        await page.locator('#tnr-profile-menu-trigger').click();
        await page.getByRole('menuitem', { name: 'Take a tour', exact: true }).click();
        await page.screenshot({ path: testInfo.outputPath(`${role}-tour.png`) });
        await page.keyboard.press('Escape');
        await expect(page.locator('.driver-popover')).toHaveCount(0);
        await expect(page.locator('#tnr-profile-menu-trigger')).toBeFocused();
    });
}

test('customer tours preserve booking choices, profile edits, and the appointments modal', async ({ page }, testInfo) => {
    await signIn(page, 'customer');
    const mutations = [];
    page.on('request', req => {
        if (!['GET', 'HEAD'].includes(req.method())) mutations.push(req.url());
    });
    for (const width of [320, 390, 768, 1366]) {
        await page.setViewportSize({ width, height: 900 });
        await page.evaluate(theme => localStorage.setItem('tnr-theme', theme), width <= 390 ? 'dark' : 'light');
        await page.goto('/booking');
        const before = await page.locator('#booking-form').evaluate(form => Array.from(new FormData(form).entries()));
        await page.locator('.booking-guide-button').click();
        const titles = await walk(page, width);
        expect(titles).toContain('Choose a service or package');
        if (width <= 600) expect(titles).toContain('Booking steps');
        expect(await page.locator('#booking-form').evaluate(form => Array.from(new FormData(form).entries()))).toEqual(before);
        await page.goto('/profile');
        const disclosure = page.locator('.profile-category-disclosure');
        const wasOpen = await disclosure.evaluate(el => el.open);
        await page.getByRole('button', { name: 'Profile guide', exact: true }).click();
        expect(await walk(page, width)).toContain('Profile sections');
        expect(await disclosure.evaluate(el => el.open)).toBe(wasOpen);
        await page.goto('/');
        if (width <= 1100) await page.locator('.mobile-nav-toggle').click();
        await page.locator('.nav-user:visible').click();
        await page.getByRole('menuitem', { name: 'Take a tour', exact: true }).click();
        const homeTitles = await walk(page, width);
        expect(homeTitles).toContain('Membership information');
        await page.locator(width <= 1100 ? '.customer-mobile-shortcuts [data-tnr-open-transactions]' : '.nav-appointments-direct').click();
        const modal = page.locator('#tnr-transactions-modal');
        await modal.locator('[data-tour-scope="appointments"]').click();
        await page.keyboard.press('Tab');
        expect(await page.evaluate(() => !!document.activeElement.closest('.driver-popover'))).toBe(true);
        await page.screenshot({ path: testInfo.outputPath(`appointments-tour-${width}.png`) });
        await page.keyboard.press('Escape');
        await expect(page.locator('.driver-popover')).toHaveCount(0);
        await expect(modal).toBeVisible();
        await expect(modal.locator('[data-tour-scope="appointments"]')).toBeFocused();
        await modal.locator('[data-tour-scope="appointments"]').click();
        expect(await walk(page, width)).toContain('Arrival policy');
    }
    expect(mutations).toEqual([]);
});
