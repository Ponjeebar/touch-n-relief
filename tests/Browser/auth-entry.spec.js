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
            const identityBox = await login.getByLabel('Username, name, or email', { exact: true }).boundingBox();
            const signInBox = await login.getByRole('button', { name: 'Sign In', exact: true }).boundingBox();
            expect(Math.abs(signInBox.x - identityBox.x)).toBeLessThanOrEqual(1);
            expect(Math.abs(signInBox.width - identityBox.width)).toBeLessThanOrEqual(1);
            await page.screenshot({ path: testInfo.outputPath(`login-${width}.png`), animations: 'disabled' });
            await page.locator(width > 700 ? '#register' : '[data-auth-panel="register"]').click();
            const registration = page.locator('#register-form');
            await expect(registration.getByLabel('Full name', { exact: true })).toBeVisible();
            await registration.getByLabel('Full name', { exact: true }).fill('Maria Clara');
            await registration.getByLabel('Birthday', { exact: true }).fill('2000-01-01');
            await registration.getByText('Female', { exact: true }).click();
            await registration.getByRole('button', { name: 'Next', exact: false }).click();
            await expect(registration.getByLabel('Email address', { exact: true })).toBeVisible();
            const actions = await registration.locator('.auth-registration-actions').boundingBox();
            for (const button of await registration.locator('.auth-registration-actions button:visible').all()) {
                const box = await button.boundingBox();
                expect(box.x).toBeGreaterThanOrEqual(actions.x - 1);
                expect(box.x + box.width).toBeLessThanOrEqual(actions.x + actions.width + 1);
            }
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

test('auth header stays readable above long forms on short laptop screens', async ({ page }, testInfo) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    for (const theme of ['light', 'dark']) {
        await page.addInitScript(mode => {
            localStorage.setItem('tnr-theme-preference-version', 'light-default-v1');
            localStorage.setItem('tnr-theme', mode);
        }, theme);
        // 800x450 also exercises the effective viewport of a laptop at higher zoom.
        for (const [width, height] of [[1366, 768], [1280, 600], [800, 450], [390, 667]]) {
            await page.setViewportSize({ width, height });
            for (const register of [false, true]) {
                await page.goto(register ? '/login?register=1' : '/login');
                await page.evaluate(() => window.scrollTo(0, 0));
                const header = page.locator('.auth-page-header');
                const container = page.locator('#container');
                const headerBox = await header.boundingBox();
                const containerBox = await container.boundingBox();
                expect(containerBox.y).toBeGreaterThanOrEqual(headerBox.y + headerBox.height);
                for (const scroll of [0, 260, 10000]) {
                    await page.evaluate(y => window.scrollTo(0, y), scroll);
                    await expect(header.locator('.brand')).toBeInViewport();
                    expect(await header.locator('.brand').evaluate(brand => {
                        const rect = brand.getBoundingClientRect();
                        const hit = document.elementFromPoint(rect.x + rect.width / 2, rect.y + rect.height / 2);
                        return brand.contains(hit);
                    })).toBe(true);
                }
                await page.screenshot({ path: testInfo.outputPath(`header-${register ? 'signup' : 'login'}-${width}-${theme}.png`), animations: 'disabled' });
                expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
                if (register) {
                    const form = page.locator('#register-form');
                    await form.getByLabel('Full name', { exact: true }).fill('Maria Clara');
                    await form.getByLabel('Birthday', { exact: true }).fill('2000-01-01');
                    await form.getByText('Female', { exact: true }).click();
                    await form.getByRole('button', { name: 'Next' }).click();
                    await form.getByLabel('Email address', { exact: true }).fill('preview@example.test');
                    await form.getByLabel('Contact number', { exact: true }).fill('09999998765');
                    await form.getByRole('button', { name: 'Next' }).click();
                    const submit = form.getByRole('button', { name: 'Sign Up', exact: true });
                    await submit.scrollIntoViewIfNeeded();
                    await expect(submit).toBeInViewport();
                    await expect(header.locator('.brand')).toBeInViewport();
                    const actionBox = await submit.boundingBox();
                    const stickyBox = await header.boundingBox();
                    expect(actionBox.y).toBeGreaterThanOrEqual(stickyBox.y + stickyBox.height);
                    await page.screenshot({ path: testInfo.outputPath(`signup-final-${width}-${theme}.png`), animations: 'disabled' });
                }
            }
        }
    }
});
