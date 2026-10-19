import { expect, test } from '@playwright/test';

for (const role of ['admin', 'receptionist']) {
    test(`${role} opens appointment details from the row without intercepting actions`, async ({ page }, testInfo) => {
        await page.addInitScript(() => {
            document.addEventListener('DOMContentLoaded', () => {
                const config = document.getElementById('tnr-customer-tour-config');
                if (config) localStorage.setItem(`tnr-system-tour:${config.dataset.customerTourUser}:${config.dataset.customerTourRole}:${config.dataset.customerTourPage}:v3`, 'complete');
            });
        });
        await page.goto('/login');
        const login = page.locator('form[action$="/login"]');
        await login.locator('[name="login"]').fill(`${role}@browser.test`);
        await login.locator('[name="password"]').fill('AuditPassword9');
        await login.locator('button[type="submit"]').click();
        await expect(page).not.toHaveURL(/\/login$/);
        await page.goto('/appointments');
        const tourClose = page.locator('.driver-popover-close-btn');
        if (await tourClose.isVisible()) await tourClose.click();
        const row = page.locator('.appt-row').filter({ has: page.locator('[data-open-reschedule="true"]') }).first();
        const details = page.locator('#view-appointment-modal');
        for (const width of [390, 768, 1366]) {
            await page.setViewportSize({ width, height: 900 });
            for (const theme of ['light', 'dark']) {
                await page.evaluate(mode => window.tnrSetTheme(mode), theme);
                await expect(page.locator('.appt-icon-btn.view')).toHaveCount(0);
                const client = await row.locator('.appt-client strong').innerText();
                await row.locator('[data-open-view]').click({ position: { x: 20, y: 20 } });
                await expect(details).toBeVisible();
                await expect(details.locator('#view-client')).toHaveValue(client);
                await page.locator('#view-close-btn').click();
                const trigger = row.locator('[data-open-view]');
                await trigger.focus();
                await page.keyboard.press('Enter');
                await expect(details).toBeVisible();
                await page.locator('#view-close-btn').click();
                await trigger.focus();
                await page.keyboard.press('Space');
                await expect(details).toBeVisible();
                await page.locator('#view-close-btn').click();
                await row.locator('[data-open-reschedule]').click();
                await expect(page.locator('#reschedule-appointment-modal')).toBeVisible();
                await expect(details).toBeHidden();
                await page.locator('#close-reschedule-modal').click();
                expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2)).toBeTruthy();
                await page.screenshot({ path: testInfo.outputPath(`appointments-${width}-${theme}.png`) });
            }
        }
    });
}
