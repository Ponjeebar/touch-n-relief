import { expect, test } from '@playwright/test';

test('reporting keeps normal monitoring compact and reveals issues', async ({ page }, testInfo) => {
    test.setTimeout(180_000);
    page.setDefaultNavigationTimeout(60_000);
    await page.goto('/login');
    await page.locator('[name="login"]').fill('admin@browser.test');
    await page.locator('.auth-login-panel [name="password"]').fill('AuditPassword9');
    await page.locator('.auth-login-panel button[type="submit"]').click({ timeout: 60_000 });
    await expect(page).toHaveURL(/\/dashboard$/, { timeout: 60_000 });
    await page.goto('/reporting');
    const original = await page.request.get('/reporting/data').then(response => response.json());
    const healthy = { checkedAt: new Date().toISOString(), status: 'healthy', processors: [], issues: [] };
    const apply = (monitoring, reviews = []) => page.evaluate(({ payload, monitoring, reviews }) => {
        applyReportingPayload({ ...payload, operationalMonitoring: monitoring, gatewayReconciliations: reviews });
    }, { payload: original, monitoring, reviews });

    await page.locator('#repSystemHealth').evaluate(details => { details.open = false; });
    await apply(healthy);
    await expect(page.locator('#repOperationalStatus')).toHaveText('No issues detected.');
    await expect(page.locator('#repSystemHealth')).not.toHaveAttribute('open', '');
    await expect(page.locator('#repOperationalSummary')).toBeHidden();
    await expect(page.locator('#gateway-reconciliation')).toBeHidden();
    await page.getByText('System Health', { exact: true }).click();
    await expect(page.locator('#repOperationalSummary')).toBeVisible();
    await page.getByText('System Health', { exact: true }).click();

    await apply({ ...healthy, status: 'unverified' });
    await expect(page.locator('#repOperationalStatus')).toHaveText('Processor history is not yet available.');
    await expect(page.locator('#repSystemHealth')).not.toHaveAttribute('open', '');
    await apply({ ...healthy, status: 'attention', issues: [{ message: 'Automatic processing needs review.', url: '/appointments' }] }, [
        { receivedAt: '2026-11-02', subject: 'RCP-00123', client: 'Test client', reference: 'pay_review', amount: 100, currency: 'PHP', reason: 'distinct_paid_reference' },
    ]);
    await expect(page.locator('#repSystemHealth')).toHaveAttribute('open', '');
    await expect(page.locator('#repOperationalIssues')).toContainText('Automatic processing needs review.');
    await expect(page.locator('#gateway-reconciliation')).toBeVisible();
    await expect(page.locator('#repGatewayReviewRows')).toContainText('pay_review');
    await apply(healthy);
    await expect(page.locator('#gateway-reconciliation')).toBeHidden();
    // Preserve the administrator's open disclosure while report filters refresh.
    await expect(page.locator('#repSystemHealth')).toHaveAttribute('open', '');
    await page.getByText('System Health', { exact: true }).click();
    await page.evaluate(() => { window.location.hash = 'operational-checks'; });
    await expect(page.locator('#repSystemHealth')).toHaveAttribute('open', '');
    await page.getByText('System Health', { exact: true }).click();
    for (const width of [390, 768, 1366]) {
        await page.setViewportSize({ width, height: 900 });
        await page.locator('#operational-checks').scrollIntoViewIfNeeded();
        await expect(page.locator('#repOperationalStatus')).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath(`monitoring-${width}.png`), fullPage: true });
    }
});
