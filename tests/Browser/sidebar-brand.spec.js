import { expect, test } from '@playwright/test';

for (const role of ['admin', 'receptionist']) {
    for (const setup of [
        { name: 'mobile dark', width: 390, height: 844, theme: 'dark' },
        { name: 'tablet light', width: 768, height: 1024, theme: 'light' },
        { name: 'desktop light', width: 1366, height: 768, theme: 'light' },
    ]) {
        test(`${role} brand returns to the correct dashboard on ${setup.name}`, async ({ page }) => {
            await page.setViewportSize(setup);
            await page.emulateMedia({ reducedMotion: 'reduce' });
            await page.addInitScript((theme) => {
                localStorage.setItem('tnr-theme-preference-version', 'light-default-v1');
                localStorage.setItem('tnr-theme', theme);
                document.addEventListener('DOMContentLoaded', () => {
                    const config = document.getElementById('tnr-customer-tour-config');
                    if (config) localStorage.setItem(`tnr-system-tour:${config.dataset.customerTourUser}:${config.dataset.customerTourRole}:${config.dataset.customerTourPage}:v3`, 'complete');
                });
            }, setup.theme);
            const dashboard = role === 'admin' ? '/dashboard' : '/receptionist-dashboard';
            await page.goto('/login');
            const form = page.locator('form[action$="/login"]');
            await form.locator('[name="login"]').fill(`${role}@browser.test`);
            await form.locator('[name="password"]').fill('AuditPassword9');
            await Promise.all([
                page.waitForURL(url => url.pathname === dashboard),
                form.locator('[type="submit"]').click(),
            ]);
            const brand = page.getByRole('link', { name: 'TOUCHnRELIEF dashboard', exact: true });
            await page.goto('/appointments');
            await expect(brand).toHaveAttribute('href', `http://127.0.0.1:8010${dashboard}`);
            await brand.locator('.brand-logo').click();
            await expect(page).toHaveURL(`http://127.0.0.1:8010${dashboard}`);
            await page.goto('/appointments');
            let existingDocumentWidth = setup.width;
            if (setup.width > 1024) {
                await page.locator('.sidebar').hover();
                await expect(page.locator('.sidebar')).toHaveCSS('width', '280px');
                existingDocumentWidth = await page.evaluate(() => {
                    // Compare against the original non-link wrapper without changing page content.
                    const link = document.querySelector('.sidebar a.brand');
                    const original = document.createElement('div');
                    original.className = link.className;
                    original.innerHTML = link.innerHTML;
                    link.replaceWith(original);
                    const width = document.documentElement.scrollWidth;
                    original.replaceWith(link);
                    return width;
                });
                if (existingDocumentWidth > setup.width + 2) {
                    console.log(`Existing expanded-sidebar width: ${existingDocumentWidth}px at ${setup.width}px viewport (${role}).`);
                }
            }
            await brand.focus();
            await expect(brand).toBeFocused();
            await expect(brand).toHaveCSS('outline-style', 'solid');
            if (setup.width > 1024) await expect(brand.locator('.brand-text')).toHaveCSS('opacity', '1');
            expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(existingDocumentWidth + 2);
            const brandBounds = await brand.boundingBox();
            expect(brandBounds.x).toBeGreaterThanOrEqual(0);
            expect(brandBounds.x + brandBounds.width).toBeLessThanOrEqual(setup.width);
            await page.keyboard.press('Enter');
            await expect(page).toHaveURL(`http://127.0.0.1:8010${dashboard}`);

            if (setup.width > 1024) {
                const paths = ['/appointments', '/ongoing-sessions', '/completed-sessions', '/therapist-tracking', '/client-records', '/services'];
                if (role === 'admin') paths.push('/reporting', '/activity-log', '/users', '/landing-settings', '/system-settings');
                for (const path of paths) {
                    const response = await page.goto(path);
                    expect(response.status()).toBe(200);
                    await expect(brand).toHaveAttribute('href', `http://127.0.0.1:8010${dashboard}`);
                    if (path === '/client-records') {
                        await page.locator('.cr-customer-row-link').first().click();
                        await expect(brand).toHaveAttribute('href', `http://127.0.0.1:8010${dashboard}`);
                    }
                }
            }
        });
    }
}
