import { expect, test } from '@playwright/test';
let sessionCookies;
async function boundExternalAssets(context) {
    await context.route(url => ['cdn.jsdelivr.net', 'fonts.googleapis.com', 'fonts.gstatic.com'].includes(url.hostname), async route => {
        try { await route.fulfill({ response: await route.fetch({ timeout: 5000 }) }); }
        catch { await route.abort(); }
    });
}
test.beforeEach(async ({ context }) => { await boundExternalAssets(context); });
test.afterEach(async ({ context }, testInfo) => {
    if (testInfo.status === 'passed') sessionCookies = await context.cookies();
});
test.beforeAll(async ({ browser }) => {
    const context = await browser.newContext();
    await boundExternalAssets(context);
    const page = await context.newPage();
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    const login = page.locator('form[action$="/login"]');
    await login.locator('[name="login"]').fill('customer@browser.test');
    await login.locator('[name="password"]').fill('AuditPassword9');
    await Promise.all([page.waitForURL(url => !url.pathname.endsWith('/login'), { waitUntil: 'domcontentloaded' }), login.locator('[type="submit"]').click()]);
    sessionCookies = await context.cookies();
    await context.close();
});
for (const theme of ['light', 'dark']) {
    for (const width of [390, 768, 1366]) {
        test('profile categories and editing ' + width + ' ' + theme, async ({ page }) => {
            await page.setViewportSize({ width, height: 900 });
            await page.addInitScript(mode => {
                localStorage.setItem('tnr-theme-preference-version', 'light-default-v1');
                localStorage.setItem('tnr-theme', mode);
            }, theme);
            await page.context().addCookies(sessionCookies);
            await page.goto('/profile', { waitUntil: 'domcontentloaded' });
            const tour = page.locator('.driver-popover-close-btn');
            if (await tour.isVisible()) await tour.click();
            const categories = page.getByRole('navigation', { name: 'Profile sections' });
            await expect(categories).toBeVisible();
            if (width > 900) {
                const navigationBox = await categories.boundingBox();
                const panelBox = await page.locator('.profile-shell').boundingBox();
                expect(navigationBox.x + navigationBox.width).toBeLessThan(panelBox.x);
                const first = await categories.getByRole('link').first().boundingBox();
                const second = await categories.getByRole('link').nth(1).boundingBox();
                expect(second.y).toBeGreaterThan(first.y);
            } else {
                await expect(categories.locator('details')).not.toHaveAttribute('open', '');
                await categories.locator('summary').click();
            }
            await categories.getByRole('link', { name: 'Account details', exact: true }).click();
            await expect(page).toHaveURL(/#account-details$/);
            await expect(page.locator('#personal-information #contact_number')).toBeVisible();
            await expect(page.locator('#account-details #email')).toBeVisible();
            await expect(page.locator('#name')).toHaveAttribute('readonly', '');
            const original = await page.locator('#name').inputValue();
            await page.locator('#basic-info-edit').click();
            await page.locator('#name').fill('Unsaved profile edit');
            await expect(page.locator('#email_current_password')).toBeDisabled();
            await expect(page.locator('#account-edit')).toBeDisabled();
            await page.locator('#basic-info-cancel').click();
            await expect(page.locator('#name')).toHaveValue(original);
            await page.locator('#account-edit').click();
            await expect(page.locator('#name')).toHaveAttribute('readonly', '');
            await expect(page.locator('#email_current_password')).toBeEnabled();
            await expect(page.locator('#basic-info-edit')).toBeDisabled();
            const email = await page.locator('#email').inputValue();
            await page.locator('#email').fill('unsaved@example.test');
            await page.locator('#email_current_password').fill('DoNotPersist9!');
            await page.locator('#account-cancel').click();
            await expect(page.locator('#email')).toHaveValue(email);
            await expect(page.locator('#email_current_password')).toHaveValue('');
            await page.locator('#password-edit').click();
            await expect(page.locator('#current_password')).toBeEnabled();
            await page.locator('#password-cancel').click();
            await expect(page.locator('#current_password')).toBeDisabled();
            await page.locator('#wellness-edit').click();
            await expect(page.locator('[data-wellness-chip="pressure_preference"]').first()).toBeEnabled();
            await page.locator('#wellness-cancel').click();
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 2)).toBeTruthy();
            if (width === 390 && theme === 'light') {
                await page.locator('#basic-info-edit').click();
                await page.locator('#name').fill(original + ' UI');
                await Promise.all([page.waitForResponse(response => response.request().method() === 'POST' && response.url().endsWith('/profile')), page.locator('#basic-info-save').click()]);
                await expect(page.locator('#name')).toHaveValue(original + ' UI');
                await expect(page.locator('#wp-sex-female')).toBeChecked();
                await expect(page.locator('#wp-pregnant-no')).toBeChecked();
                await page.locator('#basic-info-edit').click();
                await page.locator('#name').fill(original);
                await Promise.all([page.waitForResponse(response => response.request().method() === 'POST' && response.url().endsWith('/profile')), page.locator('#basic-info-save').click()]);
                await expect(page.locator('#name')).toHaveValue(original);
                await page.locator('#account-edit').click();
                await Promise.all([page.waitForResponse(response => response.request().method() === 'POST' && response.url().endsWith('/profile')), page.locator('#account-save').click()]);
                await expect(page.locator('#email')).toHaveValue(email);
                await expect(page.locator('#wp-sex-female')).toBeChecked();
            }
            await page.screenshot({ path: 'test-results/profile-' + width + '-' + theme + '.png', fullPage: true });
            await page.locator('.profile-txn-open').click();
            await expect(page.locator('#tnr-transactions-modal')).toBeVisible();
        });
    }
}
