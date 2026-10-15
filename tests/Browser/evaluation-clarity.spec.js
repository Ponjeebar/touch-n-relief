import { expect, test } from '@playwright/test';
import { mkdirSync } from 'node:fs';

for (const role of ['admin', 'receptionist']) {
    for (const setup of [
        { width: 390, height: 844, theme: 'light' },
        { width: 768, height: 1024, theme: 'dark' },
        { width: 1366, height: 768, theme: 'light' },
    ]) {
        test(`${role} can understand service hours and client statuses at ${setup.width}px ${setup.theme}`, async ({ page }) => {
            await page.setViewportSize(setup);
            await page.addInitScript((theme) => {
                localStorage.setItem('tnr-theme-preference-version', 'light-default-v1');
                localStorage.setItem('tnr-theme', theme);
                document.addEventListener('DOMContentLoaded', () => {
                    const config = document.getElementById('tnr-customer-tour-config');
                    if (config) localStorage.setItem(`tnr-system-tour:${config.dataset.customerTourUser}:${config.dataset.customerTourRole}:${config.dataset.customerTourPage}:v3`, 'complete');
                });
            }, setup.theme);
            await page.goto('/login');
            const login = page.locator('form[action$="/login"]');
            await login.locator('[name="login"]').fill(`${role}@browser.test`);
            await login.locator('[name="password"]').fill('AuditPassword9');
            await Promise.all([
                page.waitForURL(role === 'admin' ? '**/dashboard' : '**/receptionist-dashboard'),
                login.locator('[type="submit"]').click(),
            ]);

            for (const section of [
                { path: '/therapist-tracking', selector: '.tt-hours-explanation', text: 'Target progress =' },
                { path: '/client-records', selector: '.cr-status-explanation', text: 'Statuses and filters are recalculated when this directory loads.' },
            ]) {
                const response = await page.goto(section.path);
                expect(response.status()).toBe(200);
                const details = page.locator(section.selector);
                await expect(details).not.toHaveAttribute('open');
                await details.locator('summary').focus();
                await page.keyboard.press('Enter');
                await expect(details).toHaveAttribute('open');
                await expect(details).toContainText(section.text);
                expect(await page.evaluate(() => Math.max(document.body.scrollWidth, document.documentElement.scrollWidth) <= window.innerWidth + 2)).toBe(true);
                if (section.path === '/client-records') {
                    expect(await page.locator('.cr-customer-meta .pill-active, .cr-customer-meta .pill-inactive').count()).toBeGreaterThan(0);
                }
                mkdirSync('storage/app/browser-audit/evaluation-clarity', { recursive: true });
                await page.screenshot({ path: `storage/app/browser-audit/evaluation-clarity/${role}-${setup.width}-${section.path.slice(1)}.png`, fullPage: true });
            }

            await page.getByRole('link', { name: 'Active', exact: true }).click();
            await expect(page).toHaveURL(/sort=active/);
            await expect(page.locator('.cr-customer-meta .pill-inactive')).toHaveCount(0);
            await page.getByRole('link', { name: 'Inactive', exact: true }).click();
            await expect(page).toHaveURL(/sort=inactive/);
            await expect(page.locator('.cr-customer-meta .pill-active')).toHaveCount(0);
        });
    }
}
