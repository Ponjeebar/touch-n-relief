import { expect, test } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';

test.setTimeout(120000);
let authenticationCookies;

test.beforeAll(async ({ browser }, testInfo) => {
    execFileSync('php', ['tests/Support/prepare-browser-audit-follow-up.php'], { stdio: 'pipe' });
    const context = await browser.newContext({ baseURL: testInfo.project.use.baseURL });
    const page = await context.newPage();
    await page.goto('/login');
    const login = page.locator('form[action$="/login"]');
    await login.locator('[name="login"]').fill('admin@browser.test');
    await login.locator('[name="password"]').fill('AuditPassword9');
    await Promise.all([page.waitForURL('**/dashboard'), login.locator('[type="submit"]').click()]);
    authenticationCookies = await context.cookies();
    await context.close();
});

for (const setup of [
    { width: 360, height: 800, theme: 'light' },
    { width: 768, height: 1024, theme: 'dark' },
    { width: 1366, height: 768, theme: 'light' },
    { width: 390, height: 844, theme: 'dark', blockCharts: true },
]) {
    test(`report preserves refund values and payment review at ${setup.width}px ${setup.theme}`, async ({ page }) => {
        await page.setViewportSize(setup);
        if (setup.blockCharts) await page.route('**/*chart*js*', (route) => route.abort());
        await page.addInitScript((theme) => {
            localStorage.setItem('tnr-theme-preference-version', 'light-default-v1');
            localStorage.setItem('tnr-theme', theme);
        }, setup.theme);
        await page.context().addCookies(authenticationCookies);
        await page.goto('/reporting?period=custom&date_from=2024-10-01&date_to=2024-10-31');
        await expect(page.locator('#repGatewayReviewRows')).toContainText('pay_audit_browser_extra');
        await expect(page.locator('#repUnallocatedGatewayAmount')).toContainText('100.00');
        await expect(page.locator('#gateway-reconciliation')).toContainText('excluded');
        await expect(page.locator('#repOperationalSummary')).toContainText('Administrator review is required');
        await expect(page.locator('#repOperationalIssues')).toContainText('gateway payment case(s)');
        await expect(page.locator('#repOperationalIssues a').filter({ hasText: 'gateway payment case(s)' })).toHaveAttribute('href', /date_from=2024-10-19/);
        const monitoringBounds = await page.locator('#operational-checks').boundingBox();
        expect(monitoringBounds.x + monitoringBounds.width).toBeLessThanOrEqual(setup.width + 16);
        await expect(page.locator('#bestDay')).toHaveText('No positive sales');
        await expect(page.locator('#bestService')).toHaveText('No positive sales');
        const reviewBounds = await page.locator('#gateway-reconciliation').boundingBox();
        expect(reviewBounds.x + reviewBounds.width).toBeLessThanOrEqual(setup.width + 16);
        if (setup.width <= 768) {
            expect(await page.locator('#gateway-reconciliation .rep-table-wrap').evaluate((element) => element.scrollWidth > element.clientWidth)).toBe(true);
        }
        if (setup.blockCharts) {
            await expect(page.locator('#serviceLegend')).toContainText(/-\D*100\.00/);
            await expect(page.locator('#trendFallbackBars')).toContainText('-');
            await expect(page.locator('#pieEmptyState')).toBeHidden();
        } else {
            await expect.poll(() => page.evaluate(() => {
                const chart = window.Chart?.getChart('servicePieChart');
                return chart ? { type: chart.config.type, negative: chart.data.datasets[0].data.some((value) => value < 0) } : null;
            })).toEqual({ type: 'bar', negative: true });
        }
        expect(await page.evaluate(() => Math.max(document.body.scrollWidth, document.documentElement.scrollWidth) <= window.innerWidth + 16)).toBe(true);
        mkdirSync('storage/app/browser-audit/follow-up', { recursive: true });
        await page.screenshot({ path: `storage/app/browser-audit/follow-up/report-${setup.width}-${setup.theme}.png`, fullPage: true });
        await page.locator('#gateway-reconciliation').screenshot({ path: `storage/app/browser-audit/follow-up/review-${setup.width}-${setup.theme}.png` });
        if (setup.width === 1366) {
            await page.goto('/therapist-tracking?manage=1');
            const tourClose = page.locator('.driver-popover-close-btn');
            if (await tourClose.isVisible()) await tourClose.click();
            const profile = page.locator('.tt-manage-card[data-tt-id="AUDIT-LINKED"]');
            await profile.locator('[data-open-tt-edit]').click();
            await page.locator('#ttName').fill('Changed Audit Therapist');
            const rejectedEdit = page.waitForResponse((response) => response.url().includes('/therapists/AUDIT-LINKED') && response.status() === 422);
            page.once('dialog', (dialog) => dialog.accept());
            await page.locator('#ttAddSubmit').click();
            await rejectedEdit;
            await expect(profile).toContainText('Audit Linked Therapist');
            await page.keyboard.press('Escape');
            await profile.locator('[data-tt-delete]').click();
            const rejectedDelete = page.waitForResponse((response) => response.url().includes('/therapists/AUDIT-LINKED') && response.status() === 422);
            page.once('dialog', (dialog) => dialog.accept());
            await page.locator('#ttDeleteConfirmBtn').click();
            await rejectedDelete;
            await expect(profile).toBeVisible();
        }
    });
}
