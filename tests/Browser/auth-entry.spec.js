import { expect, test } from '@playwright/test';

test.setTimeout(180_000);
test.use({ actionTimeout: 30_000 });

for (const theme of ['light', 'dark']) {
    test(`account entry forms stay usable in ${theme} mode`, async ({ page }, testInfo) => {
        await page.emulateMedia({ reducedMotion: 'reduce' });
        await page.addInitScript(mode => {
            localStorage.setItem('tnr-theme-preference-version', 'light-default-v1');
            localStorage.setItem('tnr-theme', mode);
        }, theme);
        for (const width of [320, 390, 768, 1440]) {
            await page.setViewportSize({ width, height: 900 });
            await page.goto('/login');
            const login = page.locator('.auth-login-panel');
            await expect(login.getByLabel('Username, name, or email', { exact: true })).toBeVisible();
            await login.getByLabel('Username, name, or email', { exact: true }).fill('customer@browser.test');
            await login.getByLabel('Password', { exact: true }).fill('AuditPassword9');
            await login.getByRole('button', { name: 'Show password', exact: true }).click();
            await expect(login.getByLabel('Password', { exact: true })).toHaveAttribute('type', 'text');
            await login.getByRole('button', { name: 'Hide password', exact: true }).click();
            await page.screenshot({ path: testInfo.outputPath(`login-${width}.png`), animations: 'disabled' });
            await page.locator(width > 700 ? '#register' : '[data-auth-panel="register"]').click();
            const registration = page.locator('#register-form');
            await expect(registration.getByLabel('Full name', { exact: true })).toBeVisible();
            await registration.getByLabel('Full name', { exact: true }).fill('Maria Clara');
            await registration.getByLabel('Birthday', { exact: true }).fill('2000-01-01');
            await registration.getByText('Female', { exact: true }).click();
            await registration.getByRole('button', { name: 'Next', exact: false }).click();
            await expect(registration.getByLabel('Email address', { exact: true })).toBeVisible();
            await registration.getByRole('button', { name: 'Back', exact: true }).click();
            await expect(registration.getByLabel('Full name', { exact: true })).toHaveValue('Maria Clara');
            await page.screenshot({ path: testInfo.outputPath(`signup-${width}.png`), animations: 'disabled' });
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
            await page.locator(width > 700 ? '#login' : '.sign-up [data-auth-panel="login"]').click();
            await expect(login.getByLabel('Password', { exact: true })).toBeVisible();
            await login.getByRole('link', { name: 'Forgot Password?', exact: true }).click();
            await expect(page.locator('.auth-forgot-panel')).toHaveAttribute('aria-hidden', 'false');
            await page.locator('[data-auth-forgot-close]').click();
            await expect(login).toHaveAttribute('aria-hidden', 'false');
        }
        await loginSuccessfully(page);
    });
}

async function loginSuccessfully(page) {
    const form = page.locator('.auth-login-panel');
    await form.getByLabel('Username, name, or email', { exact: true }).fill('customer@browser.test');
    await form.getByLabel('Password', { exact: true }).fill('AuditPassword9');
    await form.getByRole('button', { name: 'Sign In', exact: true }).click({ noWaitAfter: true });
    await expect(page).not.toHaveURL(/\/login/, { timeout: 30_000 });
}
