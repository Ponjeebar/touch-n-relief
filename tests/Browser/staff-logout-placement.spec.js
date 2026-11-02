import { expect, test } from '@playwright/test';

for (const role of ['admin', 'receptionist']) {
    test(`${role} logout follows desktop and mobile navigation`, async ({ page }, testInfo) => {
        test.setTimeout(180_000);
        page.setDefaultNavigationTimeout(60_000);
        await page.goto('/login');
        await page.locator('.auth-login-panel [name="login"]').fill(`${role}@browser.test`);
        await page.locator('.auth-login-panel [name="password"]').fill('AuditPassword9');
        await page.locator('.auth-login-panel button[type="submit"]').click({ timeout: 60_000 });
        await expect(page).toHaveURL(new RegExp(role === 'admin' ? '/dashboard$' : '/receptionist-dashboard$'), { timeout: 60_000 });
        const sidebarLogout = page.locator('.sidebar .logout');
        const profileLogout = page.locator('.staff-profile-logout button');
        const confirmation = page.locator('#tnr-logout-confirm-modal');
        for (const height of [650, 900]) {
            await page.setViewportSize({ width: 1366, height });
            await page.locator('.sidebar').hover();
            await page.locator('.sidebar .nav-group-details').evaluateAll(groups => groups.forEach(group => { group.open = true; }));
            await expect(sidebarLogout).toBeInViewport();
            await page.locator('.sidebar .nav-list .nav-link').last().focus();
            await expect(page.locator('.sidebar .nav-list .nav-link').last()).toBeInViewport();
            await expect(sidebarLogout).toBeInViewport();
            await page.screenshot({ path: testInfo.outputPath(`expanded-sidebar-${height}.png`), animations: 'disabled' });
        }
        for (const width of [1366, 768, 390]) {
            await page.setViewportSize({ width, height: 768 });
            if (width > 1024) {
                await expect(sidebarLogout).toBeVisible();
                await expect(sidebarLogout).toBeInViewport();
                await page.locator('#tnr-profile-menu-trigger').click();
                await expect(profileLogout).toBeHidden();
                await page.locator('#tnr-profile-menu-trigger').click();
                await sidebarLogout.click();
            } else {
                await expect(sidebarLogout).toBeHidden();
                await page.locator('#tnr-profile-menu-trigger').click();
                await expect(profileLogout).toBeVisible();
                await profileLogout.click();
            }
            await expect(confirmation).toBeVisible();
            for (const theme of ['light', 'dark']) {
                await page.evaluate(theme => window.tnrSetTheme(theme), theme);
                await confirmation.locator('.lo-dialog').screenshot({ path: testInfo.outputPath(`logout-dialog-${width}-${theme}.png`) });
            }
            await page.evaluate(() => window.tnrSetTheme('light'));
            await confirmation.getByRole('button', { name: 'Stay signed in' }).click();
            await expect(confirmation).toBeHidden();
            // Close the account menu after cancelling a mobile sign-out.
            if (await page.locator('#tnr-profile-menu-trigger').getAttribute('aria-expanded') === 'true') {
                await page.locator('#tnr-profile-menu-trigger').click();
            }
            await page.screenshot({ path: testInfo.outputPath(`logout-${width}.png`), animations: 'disabled' });
        }
        await page.locator('#tnr-profile-menu-trigger').click();
        await profileLogout.click();
        await confirmation.getByRole('button', { name: 'Yes, log out' }).click({ timeout: 60_000 });
        await expect(page).toHaveURL(/\/$/, { timeout: 60_000 });
        await page.goto(role === 'admin' ? '/dashboard' : '/receptionist-dashboard');
        await expect(page).toHaveURL(/\/login/);
    });
}
