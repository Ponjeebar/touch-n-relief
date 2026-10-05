import { expect, test } from '@playwright/test';
let sessionCookies;
test.beforeAll(async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    await page.goto('/login');
    const login = page.locator('form[action$="/login"]');
    await login.locator('[name="login"]').fill('customer@browser.test');
    await login.locator('[name="password"]').fill('AuditPassword9');
    await Promise.all([page.waitForURL(url => !url.pathname.endsWith('/login')), login.locator('[type="submit"]').click()]);
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
            await page.goto('/profile');
            const tour = page.locator('.driver-popover-close-btn');
            if (await tour.isVisible()) await tour.click();
            await expect(page.getByRole('navigation', { name: 'Profile sections' })).toBeVisible();
            await expect(page.locator('#personal-information #contact_number')).toBeVisible();
            await expect(page.locator('#account-details #email')).toBeVisible();
            await expect(page.locator('#name')).toHaveAttribute('readonly', '');
            const original = await page.locator('#name').inputValue();
            await page.locator('#basic-info-edit').click();
            await page.locator('#name').fill('Unsaved profile edit');
            await expect(page.locator('#email_current_password')).toBeEnabled();
            await page.locator('#basic-info-cancel').click();
            await expect(page.locator('#name')).toHaveValue(original);
            await page.locator('#password-edit').click();
            await expect(page.locator('#current_password')).toBeEnabled();
            await page.locator('#password-cancel').click();
            await expect(page.locator('#current_password')).toBeDisabled();
            await page.locator('#wellness-edit').click();
            await expect(page.locator('[data-wellness-chip="pressure_preference"]').first()).toBeEnabled();
            await page.locator('#wellness-cancel').click();
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 2)).toBeTruthy();
            await page.screenshot({ path: 'test-results/profile-' + width + '-' + theme + '.png', fullPage: true });
            await page.locator('.profile-txn-open').click();
            await expect(page.locator('#tnr-transactions-modal')).toBeVisible();
        });
    }
}
