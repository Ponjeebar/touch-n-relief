import { expect, test } from '@playwright/test';

const scenarios = [
    { width: 390, height: 844, theme: 'dark', account: 'receptionist', method: 'cash' },
    { width: 768, height: 1024, theme: 'dark', account: 'admin', method: 'gcash' },
    { width: 1366, height: 768, theme: 'light', account: 'receptionist', method: 'bank_transfer' },
];

for (const [index, scenario] of scenarios.entries()) {
    test(`manual refund evidence ${scenario.width} ${scenario.theme}`, async ({ page }) => {
        await page.setViewportSize({ width: scenario.width, height: scenario.height });
        await page.addInitScript(theme => {
            localStorage.setItem('tnr-theme-preference-version', 'light-default-v1');
            localStorage.setItem('tnr-theme', theme);
        }, scenario.theme);
        await page.goto('/login');
        await page.locator('input[name="login"]').fill(`${scenario.account}@browser.test`);
        await page.locator('form[action$="/login"] input[name="password"]').fill('AuditPassword9');
        await Promise.all([page.waitForURL(url => !url.pathname.endsWith('/login')), page.locator('form[action$="/login"] button[type="submit"]').click()]);
        await page.goto('/appointments');
        const tourClose = page.locator('.driver-popover-close-btn');
        if (await tourClose.isVisible()) await tourClose.click();
        const view = page.locator(`[data-open-view][data-payment-transaction="REFUND-E2E-${index}"]`);
        await view.click();
        await page.locator('#view-complete-refund-btn').click();
        const form = page.locator('#manual-refund-form');
        await expect(page.locator('#refund-confirm-amount')).toHaveValue('₱100.00');
        await expect(page.locator('#refund-method')).toBeFocused();
        await page.locator('#refund-method').selectOption(scenario.method);
        if (scenario.method !== 'cash') await page.locator('#refund-transfer-reference').fill(`E2E-TRANSFER-${index}`);
        await page.locator('#refund-recipient').fill('Browser Customer account');
        await page.locator('#refund-evidence').setInputFiles({ name: 'acknowledgment.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF') });
        await form.locator('[name="confirmed"]').check();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 2)).toBeTruthy();
        await page.screenshot({ path: `test-results/refund-${scenario.width}-${scenario.theme}.png`, fullPage: true });
        const responsePromise = page.waitForResponse(response => response.url().includes('/refund') && response.request().method() === 'POST');
        await form.locator('[type="submit"]').click();
        expect((await responsePromise).status()).toBe(200);
        await expect(page.locator('#view-appointment-modal')).toBeHidden();
        await view.click();
        const history = page.locator('#refund-confirmation-history');
        await expect(history).toContainText('Browser Customer account');
        const downloadPromise = page.waitForEvent('download');
        await history.getByText('Download supporting evidence').click();
        expect((await downloadPromise).suggestedFilename()).toMatch(/refund-\d+\.pdf/);
        await history.locator('textarea').fill('Customer disputed receipt; checking records.');
        const reportedHistory = page.waitForResponse(response => response.url().includes('/refund-confirmations') && response.request().method() === 'GET');
        await history.getByRole('button', { name: 'Report refund dispute' }).click();
        await expect(history).toContainText('Dispute awaiting reconciliation');
        const [reported] = await (await reportedHistory).json();
        if (index === 0) {
            const headers = { 'X-CSRF-TOKEN': await page.locator('meta[name="csrf-token"]').getAttribute('content'), Accept: 'application/json' };
            expect((await page.request.patch(reported.dispute_url, { headers, data: { action: 'resolve', note: 'Another staff member checked records.', dispute_version: reported.dispute_version } })).status()).toBe(200);
            const [resolved] = await (await page.request.get(`/appointments/${await view.getAttribute('data-booking-id')}/refund-confirmations`, { headers })).json();
            expect((await page.request.patch(reported.dispute_url, { headers, data: { action: 'report', note: 'New complaint requires review.', dispute_version: resolved.dispute_version } })).status()).toBe(200);
            await history.locator('textarea').fill('Old reconciliation form.');
            await history.getByRole('button', { name: 'Record reconciliation' }).click();
            await expect(history).toContainText('The dispute state changed. Reopen the appointment.');
            await page.locator('#view-close-btn').click();
            await view.click();
            await expect(history).toContainText('New complaint requires review.');
        }
        await history.locator('textarea').fill('Transfer or signed cash acknowledgment reconciled with register.');
        await history.getByRole('button', { name: 'Record reconciliation' }).click();
        await expect(history).toContainText('Reconciled:');
    });
}
