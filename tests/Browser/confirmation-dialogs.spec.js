import { expect, test } from '@playwright/test';
import { execFileSync } from 'node:child_process';

test.setTimeout(120_000);
async function loginAdmin(page) {
    await page.goto('/login');
    await page.locator('.auth-login-panel [name="login"]').fill('admin@browser.test');
    await page.locator('.auth-login-panel [name="password"]').fill('AuditPassword9');
    await page.locator('.auth-login-panel [type="submit"]').click();
    await expect(page).toHaveURL(/\/dashboard$/);
}
async function dismissTour(page) {
    const close = page.locator('.driver-popover-close-btn');
    if (await close.isVisible()) await close.click();
}

test('service availability confirmation preserves the editor and submits only after approval', async ({ page }, testInfo) => {
    await loginAdmin(page);
    await page.goto('/services');
    await dismissTour(page);
    let submitted = 0;
    await page.route('**/services/*/toggle-availability', async route => {
        submitted++;
        expect(route.request().method()).toBe('POST');
        expect(route.request().postData()).toContain('_token=');
        await route.fulfill({ contentType: 'text/html', body: '<p>Availability request received.</p>' });
    });
    await page.locator('[data-open-service-edit]').first().click();
    const parent = page.locator('#service-modal');
    const confirmation = page.locator('#tnr-logout-confirm-modal');
    for (const width of [320, 390, 768, 1366]) {
        await page.setViewportSize({ width, height: 844 });
        for (const theme of ['light', 'dark']) {
            await page.evaluate(mode => window.tnrSetTheme(mode), theme);
            await page.locator('#service-toggle-btn').click();
            await expect(confirmation).toBeVisible();
            await expect(confirmation.locator('.lo-title')).toContainText('Make service');
            await expect(confirmation.getByRole('button', { name: 'Keep current availability' })).toBeFocused();
            for (let i = 0; i < 4; i++) {
                await page.keyboard.press('Tab');
                expect(await confirmation.evaluate(dialog => dialog.contains(document.activeElement))).toBe(true);
            }
            const box = await confirmation.locator('.lo-dialog').boundingBox();
            expect(box.x).toBeGreaterThanOrEqual(0);
            expect(box.x + box.width).toBeLessThanOrEqual(width);
            await confirmation.locator('.lo-dialog').screenshot({ path: testInfo.outputPath(`availability-${width}-${theme}.png`) });
            await page.keyboard.press('Escape');
            await expect(confirmation).toBeHidden();
            await expect(parent).toBeVisible();
            await expect(page.locator('#service-toggle-btn')).toBeFocused();
        }
    }
    expect(submitted).toBe(0);
    await page.locator('#service-toggle-btn').click();
    await confirmation.locator('[data-lo-confirm]').click();
    await expect(page.getByText('Availability request received.')).toBeVisible();
    expect(submitted).toBe(1);
});

test('archive and delete confirmations keep record context and cancel safely', async ({ page }, testInfo) => {
    await loginAdmin(page);
    for (const width of [390, 1366]) {
        await page.setViewportSize({ width, height: 844 });
        for (const theme of ['light', 'dark']) {
            await page.evaluate(mode => window.tnrSetTheme(mode), theme);
            await page.goto('/users');
            await dismissTour(page);
            await page.locator('.archive-receptionist-btn').first().click();
            const archive = page.locator('#archive-receptionist-modal');
            await expect(archive).toBeVisible();
            await expect(archive.locator('#archive-receptionist-name')).not.toHaveText('');
            await archive.locator('.profile-modal-content').screenshot({ path: testInfo.outputPath(`archive-${width}-${theme}.png`) });
            await archive.getByRole('button', { name: 'Cancel', exact: true }).click();
            await expect(archive).toBeHidden();
            await page.goto('/therapist-tracking?manage=1');
            await dismissTour(page);
            await page.locator('[data-tt-delete]:visible').first().click();
            const deletion = page.locator('#ttDeleteModal');
            await expect(deletion).toBeVisible();
            await expect(deletion.locator('#ttDeleteName')).not.toHaveText('');
            await deletion.locator('.tt-modal-content').screenshot({ path: testInfo.outputPath(`delete-${width}-${theme}.png`) });
            await deletion.getByRole('button', { name: 'Cancel', exact: true }).click();
            await expect(deletion).toBeHidden();
        }
    }
});

test('correct no-show confirmation keeps the original form action', async ({ page }) => {
    const fixtureDate = execFileSync('php', ['artisan', 'tinker', '--env=e2e', '--execute', `
        if (!app()->environment('e2e') || config('database.default') !== 'sqlite'
            || !str_ends_with(config('database.connections.sqlite.database'), 'browser-tests.sqlite')) {
            throw new RuntimeException('Confirmation check requires the isolated browser database.');
        }
        echo 'CONFIRM_DATE='.App\\Models\\SpaBooking::where('session_status', 'no_show')->firstOrFail()->booking_date->format('Y-m-d');
    `], { encoding: 'utf8' }).match(/CONFIRM_DATE=(\d{4}-\d{2}-\d{2})/)[1];
    await loginAdmin(page);
    await page.goto(`/appointments?status_filter=no-show&date=${fixtureDate}`);
    await dismissTour(page);
    const form = page.locator('form[data-action-confirm-title]').first();
    await expect(form).toBeVisible();
    const action = await form.getAttribute('action');
    let submitted = 0;
    await page.route(action, async route => {
        submitted++;
        expect(route.request().postData()).toContain('_method=PATCH');
        await route.fulfill({ contentType: 'text/html', body: '<p>Correction request received.</p>' });
    });
    await form.locator('button').click();
    const dialog = page.locator('#tnr-logout-confirm-modal');
    await expect(dialog.getByRole('heading', { name: 'Correct no-show record?' })).toBeVisible();
    await dialog.getByRole('button', { name: 'Go back', exact: true }).click();
    expect(submitted).toBe(0);
    await form.locator('button').click();
    await dialog.getByRole('button', { name: 'Correct record', exact: true }).click();
    await expect(page.getByText('Correction request received.')).toBeVisible();
    expect(submitted).toBe(1);
});
