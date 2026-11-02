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
    const trigger = page.locator('[data-open-membership-modal]').first();
    const editor = page.locator('.membership-modal').first();
    for (const width of [390, 768, 1366]) {
        await page.setViewportSize({ width, height: 900 });
        await expect(page.locator('.page-transition-skeleton')).toBeHidden();
        await page.locator('.membership-admin-card').first().screenshot({ path: testInfo.outputPath(`membership-action-${width}.png`), animations: 'disabled' });
    }
    await trigger.focus();
    await page.keyboard.press('Enter');
    await expect(editor).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(editor).toBeHidden();
    await expect(trigger).toBeFocused();
    await trigger.click();
    await editor.getByRole('button', { name: 'Cancel', exact: true }).click();
    await expect(editor).toBeHidden();
    await trigger.click();
    const form = editor.locator('form');
    const original = {};
    for (const name of ['name', 'description', 'price_amount', 'validity_days', 'benefits', 'is_active']) {
        original[name] = await form.locator(`[name="${name}"]`).inputValue();
    }
    await form.getByLabel('Description', { exact: true }).fill('Updated through the membership editor.');
    await form.getByRole('button', { name: 'Save membership' }).click({ timeout: 60_000 });
    await expect(page.locator('#status-toast')).toContainText('Membership plan updated successfully.');
    await expect(page.locator('.membership-admin-card').first()).toContainText('Updated through the membership editor.');
    await trigger.click();
    await form.getByLabel('Description', { exact: true }).fill(original.description);
    await form.getByRole('button', { name: 'Save membership' }).click({ timeout: 60_000 });
    await expect(page.locator('#status-toast')).toContainText('Membership plan updated successfully.');
    await trigger.click();
    for (const width of [390, 768, 1366]) {
        await page.setViewportSize({ width, height: 900 });
        await editor.scrollIntoViewIfNeeded();
        expect(await form.evaluate(el => el.scrollWidth <= el.clientWidth)).toBe(true);
        await expect(form.getByRole('button', { name: 'Save membership' })).toBeVisible();
        for (const theme of ['light', 'dark']) {
            await page.evaluate(theme => window.tnrSetTheme(theme), theme);
            await editor.locator('.membership-modal-panel').screenshot({ path: testInfo.outputPath(`membership-${width}-${theme}.png`) });
        }
        await page.evaluate(() => window.tnrSetTheme('light'));
    }
    await editor.getByRole('button', { name: 'Close membership editor' }).click();
    await expect(editor).toBeHidden();
    await expect(trigger).toBeFocused();
    await trigger.click();
    await editor.locator('[data-close-membership-modal]').first().click({ position: { x: 2, y: 2 } });
    await expect(editor).toBeHidden();
    await trigger.click();
    await form.getByLabel('Price (PHP)', { exact: true }).fill('0');
    await form.evaluate(form => { form.noValidate = true; });
    await form.getByRole('button', { name: 'Save membership' }).click({ timeout: 60_000 });
    await expect(editor).toBeVisible();
    await expect(editor.locator('[role="alert"]')).toBeVisible();
    await expect(form.getByLabel('Price (PHP)', { exact: true })).toHaveValue('0');
    await expect(page.locator('#service-modal')).toBeHidden();
});
