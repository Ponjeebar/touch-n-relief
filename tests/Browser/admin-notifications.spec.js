import { expect, test } from '@playwright/test';

for (const role of ['admin', 'receptionist']) {
    for (const theme of ['light', 'dark']) {
        test(`${role} notifications open and close across screen sizes in ${theme}`, async ({ page }) => {
            await page.setViewportSize({ width: 390, height: 844 });
            await page.addInitScript((mode) => {
                localStorage.setItem('tnr-theme-preference-version', 'light-default-v1');
                localStorage.setItem('tnr-theme', mode);
            }, theme);
            await page.goto('/login');
            const form = page.locator('form[action$="/login"]');
            await form.locator('[name="login"]').fill(`${role}@browser.test`);
            await form.locator('[name="password"]').fill('AuditPassword9');
            await form.locator('button[type="submit"]').click();
            await expect(page).toHaveURL(role === 'admin' ? /\/dashboard$/ : /\/receptionist-dashboard$/);
            const tourClose = page.locator('.driver-popover-close-btn');
            if (await tourClose.isVisible()) await tourClose.click();
            for (const viewport of [{ width: 390, height: 844 }, { width: 768, height: 1024 }, { width: 1366, height: 768 }]) {
                await page.setViewportSize(viewport);
                const bell = page.getByRole('button', { name: 'Notifications', exact: true });
                const panel = page.getByRole('complementary', { name: 'Notifications panel' });
                await bell.click();
                await expect(panel).toBeVisible();
                await expect(bell).toHaveAttribute('aria-expanded', 'true');
                const bounds = await panel.boundingBox();
                expect(bounds.x).toBeGreaterThanOrEqual(0);
                expect(bounds.x + bounds.width).toBeLessThanOrEqual(viewport.width);
                await panel.getByRole('heading', { name: 'Notifications', exact: true }).click();
                await expect(panel).toBeVisible();
                await page.keyboard.press('Escape');
                await expect(panel).toBeHidden();
                await expect(bell).toHaveAttribute('aria-expanded', 'false');
                await bell.click();
                await page.locator('h1').click();
                await expect(panel).toBeHidden();
                await bell.click();
                await bell.click();
                await expect(panel).toBeHidden();
            }
        });
    }
}
