import { expect, test } from '@playwright/test';

async function login(page, role) {
    await page.goto('/login');
    await page.locator('.auth-login-panel [name="login"]').fill(`${role}@browser.test`);
    await page.locator('.auth-login-panel [name="password"]').fill('AuditPassword9');
    await page.locator('.auth-login-panel button[type="submit"]').click({ timeout: 60_000 });
    await expect(page).toHaveURL(new RegExp(role === 'admin' ? '/dashboard$' : '/receptionist-dashboard$'), { timeout: 60_000 });
}

test('sidebar preference, staff Help, and modal focus remain usable', async ({ page }, testInfo) => {
    test.setTimeout(180_000);
    page.setDefaultNavigationTimeout(60_000);
    await page.setViewportSize({ width: 1366, height: 768 });
    await login(page, 'admin');
    const sidebar = page.locator('.sidebar');
    const toggle = page.locator('[data-staff-sidebar-toggle]');
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await page.mouse.move(1200, 500);
    await expect(sidebar.locator('.nav-text').filter({ hasText: 'Dashboard' }).first()).toBeVisible();
    await toggle.click();
    await page.reload();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await sidebar.hover();
    await expect(sidebar).toHaveCSS('width', '90px');
    await toggle.click();
    await page.reload();
    await page.mouse.move(1200, 500);
    await expect(sidebar).toHaveCSS('width', '280px');
    await expect(sidebar.locator('.nav-text').filter({ hasText: 'Dashboard' }).first()).toBeVisible();
    await page.goto('/services');
    await expect(page.getByRole('heading', { name: 'Services & Membership', exact: true })).toBeVisible();
    await expect(page.locator('.tnr-chat-launcher')).toBeHidden();
    await page.getByRole('button', { name: 'Staff help', exact: true }).click();
    await expect(page.locator('.tnr-chat-panel')).toBeVisible();
    await page.locator('[data-open-membership-modal]').first().click();
    await expect(page.locator('.membership-modal').first()).toBeVisible();
    await expect(page.locator('.tnr-chat')).toBeHidden();
    const modal = page.locator('.membership-modal').first();
    await modal.getByRole('button', { name: 'Save membership' }).focus();
    await page.keyboard.press('Tab');
    await expect(modal.getByRole('button', { name: 'Close membership editor' })).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(modal).toBeHidden();
    await expect(page.locator('.tnr-chat-panel')).toBeVisible();
    await page.getByRole('button', { name: 'Close help chat' }).click();
    await expect(page.getByRole('button', { name: 'Staff help', exact: true })).toBeFocused();
    await page.screenshot({ path: testInfo.outputPath('services-desktop.png'), animations: 'disabled' });
});

test('mobile reports keep totals visible and supporting details expandable', async ({ page }, testInfo) => {
    test.setTimeout(180_000);
    page.setDefaultNavigationTimeout(60_000);
    await page.setViewportSize({ width: 390, height: 844 });
    await login(page, 'admin');
    await page.goto('/reporting');
    await expect(page.locator('#repPrimaryValue')).toBeVisible();
    await expect(page.locator('#repMobilePeriod')).toBeVisible();
    for (const details of await page.locator('[data-mobile-report-details]').all()) {
        await expect(details).not.toHaveAttribute('open', '');
        await details.locator('summary').click();
        await expect(details).toHaveAttribute('open', '');
        await details.locator('summary').click();
    }
    await page.getByText('Service breakdown', { exact: true }).click();
    await expect(page.locator('#serviceLegend')).toBeVisible();
    await page.getByText('Service breakdown', { exact: true }).click();
    for (const theme of ['light', 'dark']) {
        await page.evaluate(theme => window.tnrSetTheme(theme), theme);
        await page.screenshot({ path: testInfo.outputPath(`reporting-mobile-${theme}.png`), fullPage: true });
    }
    await page.setViewportSize({ width: 1366, height: 900 });
    for (const details of await page.locator('[data-mobile-report-details]').all()) await expect(details).toHaveAttribute('open', '');
    await expect(page.locator('#repMobilePeriod')).toBeHidden();
});
