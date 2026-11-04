import { expect, test } from '@playwright/test';
let sessionCookies;
async function selectSection(page, name) {
    const navigation = page.getByRole('navigation', { name: 'Profile sections' });
    await navigation.getByRole('link', { name, exact: true }).click();
    await expect(page.locator('[data-profile-panel]:visible')).toHaveCount(1);
}
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
test('profile section links, history, and validation return to the right fields', async ({ page }) => {
    await page.context().addCookies(sessionCookies);
    await page.goto('/profile#password-section', { waitUntil: 'domcontentloaded' });
    const skip = page.getByRole('button', { name: 'Skip tour', exact: true });
    if (await skip.isVisible()) await skip.click();
    await expect(page.locator('#password-section')).toBeVisible();
    await expect(page.locator('[data-profile-panel]:visible')).toHaveCount(1);
    await selectSection(page, 'Account details');
    await page.goBack();
    await expect(page.locator('#password-section')).toBeVisible();
    await page.goForward();
    await expect(page.locator('#account-details')).toBeVisible();
    await page.locator('#account-edit').click();
    await page.locator('#username').fill('invalid username');
    await page.locator('#account-save').click();
    await expect(page.locator('.profile-errors')).toBeVisible();
    await expect(page.locator('#account-details')).toBeVisible();
    await expect(page.locator('[data-profile-panel]:visible')).toHaveCount(1);
    await expect(page.locator('#username')).not.toHaveAttribute('readonly', '');
    await page.reload({ waitUntil: 'domcontentloaded' });
    await selectSection(page, 'Account details');
    await page.locator('#account-edit').click();
    await page.locator('#email').fill('unverified-profile@example.test');
    await Promise.all([page.waitForEvent('domcontentloaded'), page.locator('#account-save').click({ noWaitAfter: true })]);
    await expect(page.locator('.profile-errors')).toContainText('Current password is incorrect.');
    await expect(page.locator('#account-details')).toBeVisible();
    await page.reload({ waitUntil: 'domcontentloaded' });
    await selectSection(page, 'Password');
    await page.locator('#password-edit').click();
    await page.locator('#current_password').fill('IncorrectPassword9');
    await page.locator('#password').fill('NewPasswordForValidation9!');
    await page.locator('#password_confirmation').fill('NewPasswordForValidation9!');
    await page.locator('#password-save').click();
    await expect(page.locator('.profile-errors')).toContainText('Current password is incorrect.');
    await expect(page.locator('#password-section')).toBeVisible();
    await expect(page.locator('#current_password')).toBeEnabled();
    await expect(page.locator('[data-profile-panel]:visible')).toHaveCount(1);
    await page.locator('#password-cancel').click();
});
for (const theme of ['light', 'dark']) {
    for (const width of [320, 390, 768, 1024, 1440, 1920]) {
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
            await expect(page.locator('.landing-header')).toHaveCount(0);
            expect(await page.getByRole('link', { name: 'Back to home', exact: true }).evaluate(link => new URL(link.href).pathname)).toBe('/');
            const panel = await page.locator('.profile-shell').boundingBox();
            expect(panel.x).toBeGreaterThanOrEqual(0);
            expect(panel.x + panel.width).toBeLessThanOrEqual(width + 2);
            const sidebarBox = await page.locator('.profile-category-sidebar').boundingBox();
            expect(sidebarBox.x + sidebarBox.width).toBeLessThan(panel.x);
            for (const link of await categories.getByRole('link').all()) await expect(link).toBeInViewport();
            await page.screenshot({ path: 'test-results/profile-categories-' + width + '-' + theme + '.png' });
            await selectSection(page, 'Account details');
            await expect(page).toHaveURL(/#account-details$/);
            await expect(page.locator('#personal-information')).toBeHidden();
            await expect(page.locator('#account-details #email')).toBeVisible();
            await selectSection(page, 'Personal information');
            await expect(page.locator('#name')).toHaveAttribute('readonly', '');
            const original = await page.locator('#name').inputValue();
            await page.locator('#basic-info-edit').click();
            await page.locator('#name').fill('Unsaved profile edit');
            await expect(categories.locator('a[href="#password-section"]')).toHaveAttribute('aria-disabled', 'true');
            await expect(page.locator('#profile-category-edit-note')).toBeVisible();
            await expect(page.locator('#email_current_password')).toBeDisabled();
            await expect(page.locator('#account-edit')).toBeDisabled();
            await page.screenshot({ path: 'test-results/profile-editing-' + width + '-' + theme + '.png', fullPage: true });
            await page.locator('#basic-info-cancel').click();
            await expect(page.locator('#name')).toHaveValue(original);
            await selectSection(page, 'Account details');
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
            await selectSection(page, 'Password');
            await page.locator('#password-edit').click();
            await expect(page.locator('#current_password')).toBeEnabled();
            await page.locator('#password-cancel').click();
            await expect(page.locator('#current_password')).toBeDisabled();
            await page.screenshot({ path: 'test-results/profile-password-' + width + '-' + theme + '.png', fullPage: true });
            await selectSection(page, 'Wellness preferences');
            await page.locator('#wellness-edit').click();
            await expect(page.locator('[data-wellness-chip="pressure_preference"]').first()).toBeEnabled();
            await page.locator('#wellness-cancel').click();
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 2)).toBeTruthy();
            if (width === 390 && theme === 'light') {
                await selectSection(page, 'Personal information');
                await page.locator('#basic-info-edit').click();
                await page.locator('#name').fill(original + ' UI');
                await Promise.all([page.waitForEvent('domcontentloaded'), page.locator('#basic-info-save').click({ noWaitAfter: true })]);
                await expect(page.locator('#name')).toHaveValue(original + ' UI');
                await expect(page.locator('#wp-sex-female')).toBeChecked();
                await expect(page.locator('#wp-pregnant-no')).toBeChecked();
                await page.locator('#basic-info-edit').click();
                await page.locator('#name').fill(original);
                await Promise.all([page.waitForEvent('domcontentloaded'), page.locator('#basic-info-save').click({ noWaitAfter: true })]);
                await expect(page.locator('#name')).toHaveValue(original);
                await selectSection(page, 'Account details');
                await page.locator('#account-edit').click();
                await Promise.all([page.waitForEvent('domcontentloaded'), page.locator('#account-save').click({ noWaitAfter: true })]);
                await expect(page.locator('#email')).toHaveValue(email);
                await expect(page.locator('#wp-sex-female')).toBeChecked();
            }
            await page.screenshot({ path: 'test-results/profile-' + width + '-' + theme + '.png', fullPage: true });
            await selectSection(page, 'My Appointments');
            await page.locator('.profile-txn-open').click();
            await expect(page.locator('#tnr-transactions-modal')).toBeVisible();
        });
    }
}
