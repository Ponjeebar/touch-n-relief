import { expect, test } from '@playwright/test';
import { mkdirSync } from 'node:fs';

for (const role of ['admin', 'receptionist']) {
    for (const theme of ['light', 'dark']) {
        test(`${role} notifications open and close across screen sizes in ${theme}`, async ({ page }) => {
            await page.clock.install();
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
                for (const path of [role === 'admin' ? '/dashboard' : '/receptionist-dashboard', '/ongoing-sessions', '/appointments']) {
                    await page.goto(path);
                    const bell = page.getByRole('button', { name: 'Notifications', exact: true });
                    const panel = page.getByRole('complementary', { name: 'Notifications panel' });
                    await bell.click();
                    await expect(panel).toBeVisible();
                    await expect(bell).toHaveAttribute('aria-expanded', 'true');
                    const bounds = await panel.boundingBox();
                    expect(bounds.x).toBeGreaterThanOrEqual(0);
                    expect(bounds.x + bounds.width).toBeLessThanOrEqual(viewport.width);
                    const readAll = panel.locator('.notification-mark-all');
                    await expect(readAll).toBeVisible();
                    await expect(readAll).toHaveText('Read all');
                    const controlBounds = await readAll.boundingBox();
                    expect(controlBounds.x).toBeGreaterThanOrEqual(bounds.x);
                    expect(controlBounds.x + controlBounds.width).toBeLessThanOrEqual(bounds.x + bounds.width);
                    const colors = await readAll.evaluate((button) => {
                        const style = getComputedStyle(button);
                        return { foreground: style.color, background: style.backgroundColor };
                    });
                    expect(colors.foreground).not.toBe(colors.background);
                    const list = panel.getByRole('region', { name: 'Recent notifications' });
                    await expect(list.locator('.dashboard-note')).toHaveCount(20);
                    expect(await list.evaluate((element) => element.scrollHeight > element.clientHeight)).toBe(true);
                    const headerTop = controlBounds.y;
                    await list.focus();
                    await page.keyboard.press('End');
                    await expect.poll(() => list.evaluate((element) => element.scrollTop)).toBeGreaterThan(0);
                    expect((await readAll.boundingBox()).y).toBe(headerTop);
                    await expect(list.locator('.dashboard-note').last()).toBeInViewport();
                    if (viewport.width === 390 && path === '/ongoing-sessions') {
                        const feedResponse = page.waitForResponse((response) => response.url().includes('/staff-feed/poll'));
                        await page.clock.fastForward(30000);
                        await feedResponse;
                        await expect.poll(() => list.evaluate((element) => element.scrollTop)).toBeGreaterThan(0);
                    }
                    if (viewport.width === 390 && path === '/ongoing-sessions') {
                        mkdirSync('storage/app/browser-audit', { recursive: true });
                        await page.screenshot({ path: `storage/app/browser-audit/notifications-${role}-${theme}.png` });
                    }
                    await panel.getByRole('heading', { name: 'Notifications', exact: true }).click();
                    await expect(panel).toBeVisible();
                    await page.keyboard.press('Escape');
                    await expect(panel).toBeHidden();
                    await expect(bell).toHaveAttribute('aria-expanded', 'false');
                    await bell.click();
                    await page.locator('.sidebar .brand-logo').click();
                    await expect(panel).toBeHidden();
                    await bell.click();
                    await bell.click();
                    await expect(panel).toBeHidden();
                }
            }
            await page.getByRole('button', { name: 'Notifications', exact: true }).click();
            const readAll = page.locator('.notification-mark-all');
            if (theme === 'light') {
                await expect(readAll).toBeEnabled();
                const response = page.waitForResponse((result) => result.url().includes('/staff-notifications/mark-all-read') && result.request().method() === 'POST');
                await readAll.click();
                expect((await response).status()).toBe(200);
                await expect(page.locator('.dashboard-note.note-unread')).toHaveCount(0);
            }
            await page.reload();
            await page.getByRole('button', { name: 'Notifications', exact: true }).click();
            await expect(readAll).toBeDisabled();
            await expect(readAll).toBeVisible();
        });
    }
}
