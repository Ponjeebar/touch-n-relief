import { expect, test } from '@playwright/test';

test('guests can change theme from the header', async ({ page }) => {
    await page.goto('/');
    const toggle = page.locator('#tnr-landing-theme-toggle');
    await expect(toggle).toBeVisible();
    await toggle.click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    await expect(toggle).toHaveAttribute('aria-label', 'Switch to light mode');
    await page.reload();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
});

for (const width of [390, 768, 1366]) {
    test(`customer changes theme through account dropdown at ${width}px`, async ({ page }, testInfo) => {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/login');
        const form = page.locator('form[action$="/login"]');
        await form.locator('[name="login"]').fill('customer@browser.test');
        await form.locator('[name="password"]').fill('AuditPassword9');
        await Promise.all([
            page.waitForURL('http://127.0.0.1:8010/'),
            form.locator('[type="submit"]').click(),
        ]);
        await expect(page.locator('#tnr-landing-theme-toggle')).toHaveCount(0);
        const wrapper = page.locator(width <= 900 ? '.nav-user-wrap--mobile' : '.nav-user-wrap--desktop');
        async function openAccount() {
            if (width <= 900) await page.getByRole('button', { name: 'Open navigation menu', exact: true }).click();
            await wrapper.locator('.nav-user').click();
        }
        await openAccount();
        const toggle = wrapper.getByRole('menuitem', { name: 'Switch to dark mode', exact: true });
        await expect(toggle).toBeVisible();
        await toggle.focus();
        await page.keyboard.press('Enter');
        await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
        await expect(page.locator('[data-landing-theme-label]')).toHaveText(['Switch to light mode', 'Switch to light mode']);
        await page.reload();
        await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
        await openAccount();
        await page.screenshot({ path: testInfo.outputPath('theme-dropdown-dark.png') });
        await wrapper.getByRole('menuitem', { name: 'Switch to light mode', exact: true }).click();
        await expect(page.locator('html')).not.toHaveAttribute('data-theme', 'dark');
        await expect(page.locator('[data-landing-theme-label]')).toHaveText(['Switch to dark mode', 'Switch to dark mode']);
        await page.reload();
        await expect(page.locator('html')).not.toHaveAttribute('data-theme', 'dark');
    });
}
