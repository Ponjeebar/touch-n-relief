import { expect, test } from '@playwright/test';
import { readFile } from 'node:fs/promises';

test('administrator downloads recovery JSON from settings without saving edits', async ({ page }, testInfo) => {
    await page.goto('/login');
    const form = page.locator('form[action$="/login"]');
    await form.locator('[name="login"]').fill('admin@browser.test');
    await form.locator('[name="password"]').fill('AuditPassword9');
    await form.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.goto('/reporting');
    await expect(page.locator('#repBackupLink')).toHaveCount(0);
    await page.goto('/system-settings');
    const link = page.getByRole('link', { name: 'Download recovery export', exact: true });
    const retention = page.locator('[name="backup_retention_days"]');
    const savedRetention = await retention.inputValue();
    let settingsWrites = 0;
    page.on('request', request => {
        if (request.url().endsWith('/system-settings') && request.method() !== 'GET') settingsWrites++;
    });
    for (const width of [390, 768, 1366]) {
        await page.setViewportSize({ width, height: 900 });
        for (const theme of ['light', 'dark']) {
            await page.evaluate(mode => window.tnrSetTheme(mode), theme);
            await link.scrollIntoViewIfNeeded();
            await expect(link).toBeVisible();
            const bounds = await link.boundingBox();
            expect(bounds.x).toBeGreaterThanOrEqual(0);
            expect(bounds.x + bounds.width).toBeLessThanOrEqual(width);
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2)).toBe(true);
            await page.screenshot({ path: testInfo.outputPath(`backup-${width}-${theme}.png`) });
        }
    }
    await retention.fill(savedRetention === '21' ? '22' : '21');
    await link.focus();
    const downloaded = page.waitForEvent('download');
    await page.keyboard.press('Enter');
    const download = await downloaded;
    expect(download.suggestedFilename()).toMatch(/\.json$/);
    const payload = JSON.parse(await readFile(await download.path(), 'utf8'));
    expect(payload.format_version).toBe(2);
    expect(payload.tables).toHaveProperty('users');
    expect(payload.tables).toHaveProperty('payment_ledger_entries');
    expect(settingsWrites).toBe(0);
    await expect(page).toHaveURL(/\/system-settings$/);
    await expect(page.locator('.page-transition-skeleton')).toBeHidden();
    await page.reload();
    await expect(retention).toHaveValue(savedRetention);
});
