import { expect, test } from '@playwright/test';

for (const role of ['admin', 'receptionist']) {
    test(`${role} changes theme through profile on mobile, tablet, and desktop`, async ({ page }, testInfo) => {
        await page.goto('/login');
        const form = page.locator('form[action$="/login"]');
        await form.locator('[name="login"]').fill(`${role}@browser.test`);
        await form.locator('[name="password"]').fill('AuditPassword9');
        await form.locator('button[type="submit"]').click();
        const dashboard = role === 'admin' ? '/dashboard' : '/receptionist-dashboard';
        await expect(page).toHaveURL(new RegExp(`${dashboard}$`));
        await page.evaluate(() => window.tnrSetTheme('light'));

        for (const width of [390, 768, 1366]) {
            await page.setViewportSize({ width, height: 900 });
            for (const path of [dashboard, '/appointments']) {
                await page.goto(path);
                const tourClose = page.locator('.driver-popover-close-btn');
                if (await tourClose.isVisible()) await tourClose.click();
                await expect(page.locator('#tnr-settings-trigger, [data-tnr-settings-wrap]')).toHaveCount(0);
                const trigger = page.locator('#tnr-profile-menu-trigger');
                await trigger.focus();
                await page.keyboard.press('Enter');
                const menu = page.locator('#tnr-profile-dropdown');
                const theme = menu.getByRole('menuitemcheckbox', { name: 'Dark mode', exact: true });
                await expect(theme).toBeVisible();
                await expect(theme).toHaveAttribute('aria-checked', 'false');
                await theme.focus();
                await page.keyboard.press('Enter');
                await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
                await expect(theme).toHaveAttribute('aria-checked', 'true');
                const bounds = await menu.boundingBox();
                expect(bounds.x).toBeGreaterThanOrEqual(0);
                expect(bounds.x + bounds.width).toBeLessThanOrEqual(width);
                if (path === dashboard) await page.screenshot({ path: testInfo.outputPath(`profile-theme-${width}-dark.png`) });
                await page.keyboard.press('Escape');
                await expect(menu).toBeHidden();
                await page.reload();
                await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
                await trigger.click();
                await theme.click();
                await expect(page.locator('html')).not.toHaveAttribute('data-theme', 'dark');
                await expect(theme).toHaveAttribute('aria-checked', 'false');
                await page.reload();
                await expect(page.locator('html')).not.toHaveAttribute('data-theme', 'dark');
            }
        }
    });
}
