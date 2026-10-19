import { expect, test } from '@playwright/test';

test('administrator views customer records and sees read-only receptionist login details', async ({ page }, testInfo) => {
    await page.goto('/login');
    const login = page.locator('form[action$="/login"]');
    await login.locator('[name="login"]').fill('admin@browser.test');
    await login.locator('[name="password"]').fill('AuditPassword9');
    await login.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/\/dashboard$/);
    for (const width of [390, 768, 1366]) {
        await page.setViewportSize({ width, height: 900 });
        for (const theme of ['light', 'dark']) {
            await page.goto('/users?tab=customers', { waitUntil: 'networkidle' });
            await page.evaluate(mode => window.tnrSetTheme(mode), theme);
            await expect(page.locator('.cr-edit-customer-btn, #cr-edit-customer-modal')).toHaveCount(0);
            const view = page.getByRole('link', { name: 'View Record', exact: true }).first();
            await view.scrollIntoViewIfNeeded();
            await expect(view).toBeVisible();
            await expect(page.locator('.archive-customer-btn').first()).toBeVisible();
            await page.screenshot({ path: testInfo.outputPath(`customer-view-${width}-${theme}.png`) });
            await view.click();
            await expect(page).toHaveURL(/\/client-records\/\d+$/);
            await expect(page.getByRole('heading', { name: 'Client profile', exact: true })).toBeVisible();
            await page.goto('/users?tab=receptionists', { waitUntil: 'networkidle' });
            await page.locator('.edit-receptionist-btn').first().click();
            await expect(page.locator('#edit-modal-email')).toHaveAttribute('readonly', '');
            await expect(page.locator('#edit-modal-username')).toHaveAttribute('readonly', '');
            await expect(page.locator('#edit-modal-full-name')).toBeEditable();
            await expect(page.locator('#edit-modal-phone-number')).toBeEditable();
            await expect(page.locator('#edit-receptionist-form input[type="password"]')).toHaveCount(0);
        }
    }
});
