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

test('landing header changes from light at the top to dark after scrolling', async ({ page }, testInfo) => {
    await login(page);
    for (const width of [390, 1366]) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/');
        await dismissTour(page);
        const landingHeader = page.locator('.landing-header');
        const shell = landingHeader.locator('.nav-shell');
        await expect(landingHeader).not.toHaveClass(/scrolled/);
        await expect.poll(() => shell.evaluate(node => getComputedStyle(node).backgroundColor)).toBe('rgba(255, 255, 255, 0.97)');
        await expect(landingHeader.locator('.brand')).toHaveCSS('color', 'rgb(23, 19, 17)');
        await page.screenshot({ path: testInfo.outputPath(`header-top-${width}.png`), animations: 'disabled' });
        await page.evaluate(() => window.scrollTo(0, 180));
        await expect(landingHeader).toHaveClass(/scrolled/);
        await expect.poll(() => shell.evaluate(node => getComputedStyle(node).backgroundColor)).toBe('rgba(17, 14, 13, 0.97)');
        await expect(landingHeader.locator('.brand')).toHaveCSS('color', 'rgb(248, 240, 233)');
        await page.screenshot({ path: testInfo.outputPath(`header-scrolled-${width}.png`), animations: 'disabled' });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2)).toBe(true);
    }
});

test('booking selections and payment amounts remain clear when choices change', async ({ page }, testInfo) => {
    await login(page);
    for (const width of [390, 768, 1366]) {
        await page.setViewportSize({ width, height: 900 });
        await page.evaluate(theme => localStorage.setItem('tnr-theme', theme), width === 390 ? 'dark' : 'light');
        await page.goto('/booking');
        await dismissTour(page);
        await page.evaluate(() => window.scrollTo(0, 0));
        await expect(page.locator('.landing-header')).toHaveCount(0);
        await expect(page.getByRole('link', { name: 'Back to home', exact: true })).toBeInViewport();
        await page.evaluate(() => document.fonts.ready);
        const back = await page.locator('.booking-return-link').first().boundingBox();
        const guide = await page.getByRole('button', { name: 'Booking guide', exact: true }).boundingBox();
        const heading = await page.getByRole('heading', { name: 'Book an appointment', exact: true }).boundingBox();
        expect(Math.abs(back.y - guide.y)).toBeLessThanOrEqual(2);
        expect(heading.y).toBeGreaterThan(back.y + back.height);
        expect(Math.abs(heading.x - back.x)).toBeLessThanOrEqual(2);
        await page.screenshot({ path: testInfo.outputPath(`booking-introduction-${width}.png`), animations: 'disabled' });
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

test('customer navigation, back links, and appointments work across customer pages', async ({ page }, testInfo) => {
    await login(page);
    for (const width of [390, 1024, 1366]) {
        await page.setViewportSize({ width, height: 900 });
        for (const path of ['/', '/booking', '/profile']) {
            await page.goto(path);
            await dismissTour(page);
            if (path === '/') {
                const header = await page.locator('.landing-header .nav-shell').boundingBox();
                expect(header.x).toBeLessThanOrEqual(2);
                expect(Math.abs(header.width - width)).toBeLessThanOrEqual(2);
                if (width > 1100) {
                    const brand = await page.locator('.landing-header .brand-wrap').boundingBox();
                    const links = await page.locator('.landing-header .nav-links').boundingBox();
                    const actions = await page.locator('.landing-header .nav-header-actions').boundingBox();
                    expect(brand.x).toBeLessThan(links.x);
                    expect(Math.abs(links.x + links.width / 2 - width / 2)).toBeLessThanOrEqual(2);
                    expect(actions.x).toBeGreaterThan(links.x + links.width);
                }
                if (width <= 1100) await page.locator('.mobile-nav-toggle').click();
                const menu = page.locator('[data-user-menu]:visible');
                await menu.locator('.nav-user').click();
                await expect(menu.locator('.nav-user-menu')).toBeVisible();
                await expect(menu.getByRole('menuitem', { name: 'Logout', exact: true })).toBeVisible();
                await expect(menu.getByRole('menuitem', { name: 'My Appointments', exact: true })).toHaveCount(0);
                await menu.locator('.nav-user').click();
                if (width <= 1100) await page.locator('.mobile-nav-toggle').click();
            } else {
                await expect(page.locator('.landing-header')).toHaveCount(0);
                await page.evaluate(() => window.scrollTo(0, 0));
                await expect(page.getByRole('link', { name: 'Back to home', exact: true })).toBeInViewport();
            }
            let appointmentsOpened = false;
            if (width <= 1100) {
                await page.locator('.customer-mobile-shortcuts [data-tnr-open-transactions]').click();
                appointmentsOpened = true;
            } else if (path === '/') {
                await page.locator('.nav-appointments-direct').click();
                appointmentsOpened = true;
            } else if (path === '/profile') {
                await page.locator('.profile-txn-open').click();
                appointmentsOpened = true;
            }
            const appointments = page.locator('#tnr-transactions-modal');
            if (appointmentsOpened) {
                await expect(appointments).toBeVisible();
                await expect(appointments.locator('.txn-modal-client-meta')).toHaveCount(0);
                await expect(appointments.locator('.txn-arrival-policy')).not.toHaveAttribute('open', '');
                await appointments.locator('.txn-arrival-policy summary').click();
                await expect(appointments.locator('.txn-modal-note')).toContainText('grace period');
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
    await page.goto('/');
    const menu = page.locator('[data-user-menu]:visible');
    await menu.locator('.nav-user').click();
    await menu.getByRole('menuitem', { name: 'Logout', exact: true }).click();
    await page.getByRole('button', { name: 'Stay signed in', exact: true }).click();
    await expect(page).toHaveURL(/\/$/);
    await menu.locator('.nav-user').click();
    await menu.getByRole('menuitem', { name: 'Logout', exact: true }).click();
    await page.getByRole('button', { name: 'Yes, log out', exact: true }).click();
    await page.goto('/profile');
    await expect(page).toHaveURL(/\/login$/);
});
