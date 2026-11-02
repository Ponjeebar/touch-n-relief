import { expect, test } from '@playwright/test';

test.setTimeout(180_000);
test.use({ actionTimeout: 60_000, navigationTimeout: 60_000 });

for (const width of [390, 1366]) {
    test(`guest header keeps booking and login actions reachable at ${width}px`, async ({ page }, testInfo) => {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/');
        await expect(page.locator('#tnr-landing-theme-toggle, [data-landing-theme-toggle]')).toHaveCount(0);
        const actions = page.locator(width <= 1100 ? '.nav-guest-actions--mobile' : '.nav-guest-actions--desktop');
        if (width <= 1100) {
            await expect(actions).toBeHidden();
            await page.getByRole('button', { name: 'Open navigation menu', exact: true }).click();
        }
        const bookNow = actions.getByRole('link', { name: 'BOOK NOW', exact: true });
        const login = actions.getByRole('link', { name: 'Log In', exact: true });
        await expect(bookNow).toBeVisible();
        await expect(bookNow).toHaveAttribute('href', /\/booking$/);
        await expect(login).toBeVisible();
        if (width <= 1100) {
            await expect(page.locator('.nav-links').getByRole('link', { name: 'Services', exact: true })).toHaveCSS('color', 'rgb(255, 255, 255)');
            await expect(login).toHaveCSS('color', 'rgb(255, 255, 255)');
        }
        if (width > 1100) {
            const bookBox = await bookNow.boundingBox();
            const loginBox = await login.boundingBox();
            expect(loginBox.x).toBeGreaterThan(bookBox.x);
        }
        await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
        await page.screenshot({ path: testInfo.outputPath(`guest-header-${width}.png`), animations: 'disabled' });
    });
}

for (const width of [390, 768, 1366]) {
    test(`customer changes theme through account dropdown at ${width}px`, async ({ page }, testInfo) => {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/login');
        const form = page.locator('form[action$="/login"]');
        await form.locator('[name="login"]').fill('customer@browser.test');
        await form.locator('[name="password"]').fill('AuditPassword9');
        await Promise.all([
            page.waitForURL('http://127.0.0.1:8010/'),
            form.locator('[type="submit"]').click(),
        ]);
        await expect(page.locator('#tnr-landing-theme-toggle')).toHaveCount(0);
        const wrapper = page.locator(width <= 1100 ? '.nav-user-wrap--mobile' : '.nav-user-wrap--desktop');
        async function openAccount() {
            if (width <= 1100) await page.getByRole('button', { name: 'Open navigation menu', exact: true }).click();
            await wrapper.locator('.nav-user').click();
        }
        await openAccount();
        const toggle = wrapper.getByRole('menuitem', { name: 'Switch to dark mode', exact: true });
        await expect(toggle).toBeVisible();
        await toggle.focus();
        await page.keyboard.press('Enter');
        await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
        await expect(page.locator('[data-landing-theme-label]')).toHaveText(['Switch to light mode', 'Switch to light mode']);
        await page.reload();
        await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
        await openAccount();
        await page.screenshot({ path: testInfo.outputPath('theme-dropdown-dark.png') });
        await wrapper.getByRole('menuitem', { name: 'Switch to light mode', exact: true }).click();
        await expect(page.locator('html')).not.toHaveAttribute('data-theme', 'dark');
        await expect(page.locator('[data-landing-theme-label]')).toHaveText(['Switch to dark mode', 'Switch to dark mode']);
        await page.reload();
        await expect(page.locator('html')).not.toHaveAttribute('data-theme', 'dark');
    });
}
