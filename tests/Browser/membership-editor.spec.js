import { expect, test } from '@playwright/test';

test('administrator edits membership in the Services CMS', async ({ page }, testInfo) => {
    test.setTimeout(180_000);
    page.setDefaultNavigationTimeout(60_000);
    await page.goto('/login');
    await page.locator('[name="login"]').fill('admin@browser.test');
    await page.locator('.auth-login-panel [name="password"]').fill('AuditPassword9');
    await page.locator('.auth-login-panel button[type="submit"]').click({ timeout: 60_000 });
    await expect(page).toHaveURL(/\/dashboard$/, { timeout: 60_000 });
    await page.goto('/services');
    const editor = page.locator('.membership-editor').first();
    await editor.locator('summary').click();
    const form = editor.locator('form');
    const original = {};
    for (const name of ['name', 'description', 'price_amount', 'validity_days', 'benefits', 'is_active']) {
        original[name] = await form.locator(`[name="${name}"]`).inputValue();
    }
    await form.getByLabel('Description', { exact: true }).fill('Updated through the membership editor.');
    await form.getByRole('button', { name: 'Save membership' }).click({ timeout: 60_000 });
    await expect(page.locator('#status-toast')).toContainText('Membership plan updated successfully.');
    await expect(page.locator('.membership-admin-card').first()).toContainText('Updated through the membership editor.');
    await editor.locator('summary').click();
    await form.getByLabel('Description', { exact: true }).fill(original.description);
    await form.getByRole('button', { name: 'Save membership' }).click({ timeout: 60_000 });
    await expect(page.locator('#status-toast')).toContainText('Membership plan updated successfully.');
    await editor.locator('summary').click();
    for (const width of [390, 768, 1366]) {
        await page.setViewportSize({ width, height: 900 });
        await editor.scrollIntoViewIfNeeded();
        expect(await form.evaluate(el => el.scrollWidth <= el.clientWidth)).toBe(true);
        await expect(form.getByRole('button', { name: 'Save membership' })).toBeVisible();
        await editor.screenshot({ path: testInfo.outputPath(`membership-${width}.png`) });
    }
});
