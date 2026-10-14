import { expect, test } from '@playwright/test';

for (const role of ['admin', 'receptionist']) {
    for (const setup of [
        { name: 'mobile light', width: 390, height: 844, theme: 'light' },
        { name: 'tablet dark', width: 768, height: 1024, theme: 'dark' },
        { name: 'desktop light', width: 1366, height: 768, theme: 'light' },
    ]) {
        test(`${role} appointment tutorial is temporary and safe on ${setup.name}`, async ({ page }) => {
            await page.setViewportSize(setup);
            await page.emulateMedia({ reducedMotion: 'reduce' });
            await page.addInitScript(({ theme, role }) => {
                localStorage.setItem('tnr-theme-preference-version', 'light-default-v1');
                localStorage.setItem('tnr-theme', theme);
                // This test starts the guide explicitly through the staff account menu.
                document.addEventListener('DOMContentLoaded', () => {
                    const config = document.getElementById('tnr-customer-tour-config');
                    if (config) localStorage.setItem(`tnr-system-tour:${config.dataset.customerTourUser}:${role}:${config.dataset.customerTourPage}:v3`, 'complete');
                });
            }, { theme: setup.theme, role });
            await page.goto('/login');
            const login = page.locator('form[action$="/login"]');
            await login.locator('[name="login"]').fill(`${role}@browser.test`);
            await login.locator('[name="password"]').fill('AuditPassword9');
            await Promise.all([
                page.waitForURL(role === 'admin' ? '**/dashboard' : '**/receptionist-dashboard'),
                login.locator('button[type="submit"]').click(),
            ]);
            await page.goto('/appointments?search=tutorial-no-matching-booking');
            await expect(page.locator('#appointments-list-container .empty-users')).toBeVisible();
            const statsBefore = await page.locator('[data-appointments-meta]').getAttribute('data-total');
            const mutations = [];
            page.on('request', (request) => {
                // Receptionist navigation is already audited by the staff click logger.
                if (!['GET', 'HEAD'].includes(request.method()) && new URL(request.url()).pathname !== '/activity-log/click') {
                    mutations.push(request.url());
                }
            });

            async function startGuide() {
                await page.locator('#tnr-profile-menu-trigger').click();
                await page.getByRole('menuitem', { name: 'Take a tour', exact: true }).click();
                await expect(page.locator('.driver-popover')).toBeVisible();
            }

            async function finishGuide() {
                const titles = [];
                for (let step = 0; step < 25; step += 1) {
                    if (!await page.locator('.driver-popover').isVisible()) break;
                    const title = await page.locator('.driver-popover-title').innerText();
                    titles.push(title);
                    const bounds = await page.locator('.driver-popover').boundingBox();
                    expect(bounds.x).toBeGreaterThanOrEqual(0);
                    expect(bounds.x + bounds.width).toBeLessThanOrEqual(setup.width + 2);
                    if (title === 'Appointment list') {
                        await page.screenshot({ path: `test-results/appointment-tutorial-${role}-${setup.width}.png` });
                    }
                    await page.locator('.driver-popover-next-btn').click();
                }
                await expect(page.locator('.driver-popover')).toHaveCount(0);
                return titles;
            }

            await startGuide();
            const sample = page.locator('[data-tour-demo="appointment"]');
            await expect(sample).toHaveCount(1);
            await expect(sample).toContainText('Tutorial example — not saved');
            await expect(sample).toContainText('PHP 72.50 balance');
            await expect(sample).toContainText('Balance Due');
            await expect(sample.locator('[data-booking-id], [data-start-url], [data-open-balance], form')).toHaveCount(0);
            for (const button of await sample.locator('button').all()) await expect(button).toBeDisabled();
            const titles = await finishGuide();
            expect(titles).toEqual(expect.arrayContaining([
                'Review full details', 'Collect a remaining balance', 'Start an eligible session',
                'Reschedule carefully', 'Cancel with a reason',
            ]));
            await expect(sample).toHaveCount(0);
            await expect(page.locator('#appointments-list-container .empty-users')).toBeVisible();
            await expect(page.locator('[data-appointments-meta]')).toHaveAttribute('data-total', statsBefore);
            expect(mutations).toEqual([]);

            await startGuide();
            await expect(sample).toHaveCount(1);
            await finishGuide();
            await page.reload();
            await expect(sample).toHaveCount(0);
            await expect(page.locator('#appointments-list-container .empty-users')).toBeVisible();
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 2)).toBe(true);
        });
    }
}
