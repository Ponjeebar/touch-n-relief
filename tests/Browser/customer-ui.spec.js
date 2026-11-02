import { expect, test } from '@playwright/test';

test.setTimeout(180_000);
test.use({ actionTimeout: 60_000, navigationTimeout: 60_000 });

async function login(page) {
    await page.goto('/login');
    await page.locator('.auth-login-panel [name="login"]').fill('customer@browser.test');
    await page.locator('.auth-login-panel [name="password"]').fill('AuditPassword9');
    await page.locator('.auth-login-panel button[type="submit"]').click();
    await expect(page).not.toHaveURL(/\/login$/);
}

async function dismissTour(page) {
    const skip = page.getByRole('button', { name: 'Skip tour', exact: true });
    if (await skip.isVisible()) await skip.click();
}

test('booking selections and payment amounts remain clear when choices change', async ({ page }, testInfo) => {
    await login(page);
    for (const width of [390, 768, 1366]) {
        await page.setViewportSize({ width, height: 900 });
        await page.evaluate(theme => localStorage.setItem('tnr-theme', theme), width === 390 ? 'dark' : 'light');
        await page.goto('/booking');
        await dismissTour(page);
        await expect(page.locator('#tnr-transactions-modal')).toHaveCount(1);
        await expect(page.locator('#booking-selection-summary')).toBeHidden();
        await page.locator('.service-item').first().click();
        const firstService = await page.locator('input[name="service"]:checked').inputValue();
        await expect(page.locator('#booking-selected-service')).toHaveText(firstService);
        await expect(page.locator('#booking-selected-price')).not.toBeEmpty();
        await page.locator('.therapist-item-any').click();
        await expect(page.locator('#booking-selected-therapist')).toHaveText('Any available therapist');
        await page.getByRole('button', { name: 'Edit service', exact: true }).click();
        await expect(page.locator('#booking-grid')).toHaveAttribute('data-mobile-step', 'service');
        await page.locator('.service-item').nth(1).click();
        const nextService = await page.locator('input[name="service"]:checked').inputValue();
        await expect(page.locator('#booking-selected-service')).toHaveText(nextService);
        await expect(page.locator('#booking-selected-therapist-row')).toBeHidden();
        await page.locator('.therapist-item-any').click();
        await page.getByRole('button', { name: 'Edit therapist', exact: true }).click();
        await expect(page.locator('#booking-grid')).toHaveAttribute('data-mobile-step', 'therapist');
        await page.locator('.therapist-item:not(.is-unavailable):not(.therapist-item-any)').first().click();
        const chosen = await page.locator('input[name="therapist"]:checked').inputValue();
        await expect(page.locator('#booking-selected-therapist')).toHaveText(chosen);
        const date = page.locator('#booking_date');
        const monday = await date.evaluate(input => {
            const next = new Date(input.min + 'T12:00:00');
            next.setDate(next.getDate() + ((8 - next.getDay()) % 7 || 7));
            return next.toISOString().slice(0, 10);
        });
        await date.fill(monday);
        await page.locator('#time-slots .time-slot:not([disabled])').first().click();
        await expect(page.locator('#time_slot')).not.toHaveValue('');
        await page.locator('.booking-submit').click();
        const payment = page.locator('#bkConfirmModal');
        await expect(payment).toBeVisible({ timeout: 60_000 });
        await expect(page.locator('#bkSumService')).toBeVisible();
        await expect(page.locator('#bkSumService')).toHaveText(nextService);
        await expect(page.locator('#bkSumTherapist')).toHaveText(chosen);
        await expect(page.locator('#bkSumDate')).toBeVisible();
        await expect(page.locator('#bkSumTime')).toBeVisible();
        const amount = async id => Number((await page.locator(id).textContent()).replace(/[^\d.]/g, ''));
        const price = await amount('#bkSumServicePrice');
        expect(price).toBeGreaterThan(0);
        expect(await amount('#bkSumAmountDue') + await amount('#bkSumBalance')).toBeCloseTo(price, 2);
        await page.locator('[data-payment-type="full"]').click();
        expect(await amount('#bkSumAmountDue')).toBe(price);
        expect(await amount('#bkSumBalance')).toBe(0);
        await expect(page.locator('#bkConfirmSubmit')).toBeDisabled();
        await page.locator('#bkNoShowPolicyAccepted').check();
        if (process.env.PAYMONGO_SECRET_KEY) {
            await expect(page.locator('#bkConfirmSubmit')).toBeEnabled();
            await payment.getByText('Payment methods and refund information', { exact: true }).click();
            await expect(payment.locator('.bk-payment-paymongo-channels')).toBeVisible();
            await payment.getByText('Payment methods and refund information', { exact: true }).click();
        } else {
            await expect(page.locator('#bkConfirmSubmit')).toBeDisabled();
        }
        await payment.locator('.bk-payment-body').evaluate(el => { el.scrollTop = 0; });
        await page.screenshot({ path: testInfo.outputPath(`payment-review-${width}.png`) });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2)).toBe(true);
        await payment.getByRole('button', { name: 'Close', exact: true }).click();
    }
});

test('customer account navigation and appointments work across customer pages', async ({ page }, testInfo) => {
    await login(page);
    for (const width of [390, 1024, 1366]) {
        await page.setViewportSize({ width, height: 900 });
        for (const path of ['/', '/booking', '/profile']) {
            await page.goto(path);
            await dismissTour(page);
            if (width <= 900) await page.locator('.mobile-nav-toggle').click();
            const menu = page.locator('[data-user-menu]:visible');
            await menu.locator('.nav-user').click();
            await expect(menu.locator('.nav-user-menu')).toBeVisible();
            await expect(menu.getByRole('menuitem', { name: 'Logout', exact: true })).toBeVisible();
            await expect(menu.getByRole('menuitem', { name: 'My Appointments', exact: true })).toHaveCount(0);
            await menu.locator('.nav-user').click();
            if (width <= 900) {
                await page.locator('.mobile-nav-toggle').click();
                await page.locator('.customer-mobile-shortcuts [data-tnr-open-transactions]').click();
            } else {
                await page.locator('.nav-appointments-direct').click();
            }
            const appointments = page.locator('#tnr-transactions-modal');
            await expect(appointments).toBeVisible();
            await expect(appointments.locator('.txn-modal-client-meta')).toHaveCount(0);
            await expect(appointments.locator('.txn-arrival-policy')).not.toHaveAttribute('open', '');
            await appointments.locator('.txn-arrival-policy summary').click();
            await expect(appointments.locator('.txn-modal-note')).toContainText('grace period');
            await appointments.getByRole('button', { name: 'Close', exact: true }).last().click();
            if (width > 900) {
                await page.locator('.nav-appointments-direct').click();
                await expect(appointments).toBeVisible();
                await appointments.getByRole('button', { name: 'Close', exact: true }).last().click();
            }
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2)).toBe(true);
            if (path === '/' && width === 1366) {
                await page.locator('.service-details-action').first().scrollIntoViewIfNeeded();
                await expect(page.locator('.service-details-action').first()).toBeVisible();
                await page.locator('.service-trigger').first().press('Enter');
                await expect(page.locator('#serviceModal')).toBeVisible();
            }
            await page.screenshot({ path: testInfo.outputPath(`customer-${path.replace(/\W/g, '') || 'home'}-${width}.png`) });
        }
    }
    const menu = page.locator('[data-user-menu]:visible');
    await menu.locator('.nav-user').click();
    await menu.getByRole('menuitem', { name: 'Logout', exact: true }).click();
    await page.getByRole('button', { name: 'Stay signed in', exact: true }).click();
    await expect(page).toHaveURL(/\/profile$/);
    await menu.locator('.nav-user').click();
    await menu.getByRole('menuitem', { name: 'Logout', exact: true }).click();
    await page.getByRole('button', { name: 'Yes, log out', exact: true }).click();
    await page.goto('/profile');
    await expect(page).toHaveURL(/\/login$/);
});
