import { expect, test } from '@playwright/test';

for (const setup of [
    { width: 390, height: 844, theme: 'dark', role: 'receptionist' },
    { width: 768, height: 1024, theme: 'dark', role: 'receptionist' },
    { width: 1366, height: 768, theme: 'light', role: 'admin' },
]) {
    test(`client history status filters ${setup.width} ${setup.theme} ${setup.role}`, async ({ page }) => {
        await page.setViewportSize({ width: setup.width, height: setup.height });
        await page.addInitScript(theme => {
            localStorage.setItem('tnr-theme-preference-version', 'light-default-v1');
            localStorage.setItem('tnr-theme', theme);
        }, setup.theme);
        await page.goto('/login');
        const form = page.locator('form[action$="/login"]');
        await form.locator('[name="login"]').fill(`${setup.role}@browser.test`);
        await form.locator('[name="password"]').fill('AuditPassword9');
        await Promise.all([page.waitForURL(url => !url.pathname.endsWith('/login')), form.locator('[type="submit"]').click()]);
        await page.goto('/appointments');
        await page.goto('/client-records');
        const close = page.locator('.driver-popover-close-btn');
        if (await close.isVisible()) await close.click();
        await page.locator('.cr-customer-row-link').filter({ hasText: 'customer@browser.test' }).click();
        await page.locator('#txn-filter').selectOption('no-show');
        const visibleRows = page.locator('.cr-txn-row:visible');
        await expect(visibleRows).toHaveCount(1);
        await expect(visibleRows).toContainText('Foot Reflexology');
        await expect(visibleRows.locator('.cr-txn-status')).toHaveText('No Show');
        await page.locator('#txn-filter').selectOption('completed');
        await expect(visibleRows).toHaveCount(1);
        await expect(visibleRows).toContainText('Hot Stone');
        await expect(visibleRows.locator('.cr-txn-status')).toHaveText('Completed');
        await page.locator('#txn-filter').selectOption('all');
        await page.locator('#txn-search').fill('Foot Reflexology');
        await expect(visibleRows).toHaveCount(4);
        await page.locator('#cr-txn-pagination').getByRole('button', { name: 'Next', exact: true }).click();
        await expect(visibleRows).toHaveCount(1);
        await expect(visibleRows.locator('.cr-txn-status-no-show')).toHaveCount(1);
        await page.locator('#txn-filter').selectOption('no-show');
        await expect(visibleRows).toHaveCount(1);
        await expect(visibleRows.locator('.cr-txn-status')).toHaveText('No Show');
        await page.locator('#txn-search').fill('');
        await page.locator('#txn-filter').selectOption('all');
        await expect(page.locator('#cr-txn-pagination')).toBeVisible();
        await page.locator('#cr-txn-pagination').getByRole('button', { name: 'Next', exact: true }).click();
        await expect(page.locator('#cr-txn-pagination [aria-current="page"]')).toHaveText('2');
        await page.reload();
        await page.locator('#txn-filter').selectOption('no-show');
        await expect(visibleRows.locator('.cr-txn-status')).toHaveText('No Show');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 2)).toBe(true);
    });
}
