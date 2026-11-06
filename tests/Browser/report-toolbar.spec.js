import { expect, test } from '@playwright/test';

test('report downloads stay grouped beside filters or wrap together on smaller screens', async ({ page }, testInfo) => {
    await page.goto('/login');
    const form = page.locator('form[action$="/login"]');
    await form.locator('[name="login"]').fill('admin@browser.test');
    await form.locator('[name="password"]').fill('AuditPassword9');
    await form.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.goto('/reporting', { waitUntil: 'networkidle' });
    await page.evaluate(() => document.fonts.ready);
    const group = page.getByRole('group', { name: 'Report downloads', exact: true });
    const excel = group.getByRole('link', { name: 'Export Excel', exact: true });
    const pdf = group.getByRole('link', { name: 'Download Reports (opens in a new tab)', exact: true });
    const custom = page.locator('#repPeriod [data-period="custom"]');
    const range = page.locator('#repCustomRange');
    for (const width of [360, 390, 768, 1024, 1366, 1440, 1920]) {
        await page.setViewportSize({ width, height: 900 });
        for (const theme of ['light', 'dark']) {
            await page.evaluate(mode => window.tnrSetTheme(mode), theme);
            await expect(excel).toBeVisible();
            await expect(pdf).toBeVisible();
            const [left, right] = await group.locator('a').evaluateAll(links => links.map(link => {
                const { x, y, width, height } = link.getBoundingClientRect();
                return { x, y, width, height };
            }));
            expect(Math.abs(left.y - right.y), `${width}px ${theme} alignment`).toBeLessThan(2);
            expect(right.x - left.x - left.width).toBeGreaterThanOrEqual(6);
            expect(right.x - left.x - left.width).toBeLessThanOrEqual(12);
            expect(left.x).toBeGreaterThanOrEqual(0);
            expect(right.x + right.width).toBeLessThanOrEqual(width);
            for (const button of [excel, pdf]) {
                expect(await button.evaluate(element => element.scrollWidth <= element.clientWidth + 1)).toBe(true);
            }
            const filters = await page.locator('#repPeriod').boundingBox();
            expect(left.y >= filters.y + filters.height - 1 || left.x >= filters.x + filters.width).toBe(true);
            await custom.click();
            await expect(range).toBeVisible();
            const dates = await range.boundingBox();
            const expandedButton = await excel.boundingBox();
            expect(dates.y).toBeGreaterThanOrEqual(expandedButton.y + expandedButton.height - 1);
            expect(dates.x + dates.width).toBeLessThanOrEqual(width);
            await page.screenshot({ path: testInfo.outputPath(`toolbar-${width}-${theme}.png`) });
            await custom.click();
            await expect(range).toBeHidden();
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2)).toBe(true);
        }
    }
    await page.goto('/reporting?period=custom&date_from=2026-09-01&date_to=2026-09-07', { waitUntil: 'networkidle' });
    const preview = page.locator('#repPdfLink');
    const href = new URL(await preview.getAttribute('href'));
    expect(href.searchParams.get('preview')).toBe('1');
    expect(href.searchParams.get('period')).toBe('custom');
    expect(href.searchParams.get('date_from')).toBe('2026-09-01');
    expect(href.searchParams.get('date_to')).toBe('2026-09-07');
    await expect(preview).toHaveAttribute('target', '_blank');
    const downloads = [];
    page.on('download', download => downloads.push(download));
    const responsePromise = page.context().waitForEvent('response', response => response.url().includes('/reporting/pdf?'));
    const popupPromise = page.waitForEvent('popup');
    await preview.click();
    const response = await responsePromise;
    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toContain('application/pdf');
    expect(response.headers()['content-disposition']).toMatch(/^inline;/);
    const popup = await popupPromise;
    await expect(popup).toHaveURL(/\/reporting\/pdf\?/);
    expect(downloads).toHaveLength(0);
    await popup.close();
    await expect(page).toHaveURL(/\/reporting\?period=custom/);
});
