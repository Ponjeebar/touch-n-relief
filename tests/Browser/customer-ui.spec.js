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

test('appointments keep search, history, and payment details usable across screen sizes', async ({ page }, testInfo) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await login(page);
    for (const width of [320, 390, 768, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        await page.evaluate(theme => localStorage.setItem('tnr-theme', theme), width <= 390 ? 'dark' : 'light');
        await page.goto('/');
        await dismissTour(page);
        await page.locator(width <= 1100 ? '.customer-mobile-shortcuts [data-tnr-open-transactions]' : '.nav-appointments-direct').click();
        const modal = page.locator('#tnr-transactions-modal');
        await expect(modal.getByRole('heading', { name: 'My Appointments', exact: true })).toBeInViewport();
        await modal.locator('[data-txn-group="upcoming"]').click();
        const visibleCards = modal.locator('.txn-card:visible');
        await expect(visibleCards.first()).toBeVisible();
        const firstCard = visibleCards.first();
        const service = await firstCard.getAttribute('data-service');
        await modal.locator('#txn-search').fill(service);
        await expect(visibleCards.first()).toHaveAttribute('data-service', service);
        await modal.locator('#txn-search').fill('no-matching-appointment-xyz');
        await expect(modal.locator('#txn-filter-empty')).toBeVisible();
        await expect(visibleCards).toHaveCount(0);
        await modal.locator('#txn-search').clear();
        const date = await visibleCards.first().getAttribute('data-sort-date');
        await modal.locator('#txn-date-filter').fill(date);
        await expect(visibleCards.first()).toHaveAttribute('data-sort-date', date);
        await modal.locator('#txn-date-filter').clear();
        await modal.locator('#txn-filter-select').selectOption('with-amount');
        await expect(visibleCards.first()).toHaveAttribute('data-has-amount', '1');
        await modal.locator('#txn-filter-select').selectOption('all');
        await modal.locator('#txn-sort-select').selectOption('amount-desc');
        const amounts = await visibleCards.evaluateAll(cards => cards.map(card => Number(card.dataset.amountRaw)));
        expect(amounts).toEqual([...amounts].sort((a, b) => b - a));
        const paymentCard = visibleCards.filter({ has: page.locator('[data-txn-payment-toggle]') }).first();
        const toggle = paymentCard.locator('[data-txn-payment-toggle]');
        await toggle.click();
        await expect(toggle).toHaveAttribute('aria-expanded', 'true');
        await expect(paymentCard.locator('[data-txn-payment-details]')).toBeVisible();
        await toggle.click();
        await expect(paymentCard.locator('[data-txn-payment-details]')).toBeHidden();
        await page.screenshot({ path: testInfo.outputPath(`appointments-upcoming-${width}.png`), animations: 'disabled' });
        await modal.locator('[data-txn-group="history"]').click();
        await expect(visibleCards.first()).toHaveAttribute('data-appointment-group', 'history');
        await page.screenshot({ path: testInfo.outputPath(`appointments-history-${width}.png`), animations: 'disabled' });
        expect(await modal.locator('.txn-modal-body').evaluate(body => body.scrollWidth <= body.clientWidth + 1)).toBe(true);
        await modal.locator('.txn-modal-header .txn-modal-close').click();
        await expect(modal).toBeHidden();
    }
});

test('appointment badges count upcoming records and decrease after cancellation', async ({ page }, testInfo) => {
    await login(page);
    let upcoming = 2;
    let expiringHold = false;
    await page.route(/\/(?:booking|profile)?$/, async route => {
        const response = await route.fetch();
        let body = await response.text();
        const cards = Array.from({ length: upcoming }, (_, index) => `<li class="txn-card" data-appointment-group="upcoming" data-service="Aromatherapy" ${expiringHold ? 'data-payment-hold-remaining="60"' : ''}>
            <span class="txn-badge">Confirmed</span><span>Aromatherapy</span>
            <div class="txn-card-actions"><button data-tnr-cancel-booking data-cancel-url="/bookings/${900000 + index}/cancel" data-booking-label="Aromatherapy">Cancel fixture appointment</button></div>
        </li>`).join('') + '<li class="txn-card" data-appointment-group="history">Completed appointment</li>';
        body = body.replace(/<ul class="txn-cards" id="txn-cards-list"[\s\S]*?<\/ul>/, '');
        body = body.replace('<div class="txn-modal-body">', `<div class="txn-modal-body"><ul class="txn-cards" id="txn-cards-list">${cards}</ul>`);
        await route.fulfill({ response, body });
    });
    await page.route('**/bookings/90000*/cancel', route => route.fulfill({ json: { message: 'Booking cancelled.', cancellation_reason: 'Schedule changed' } }));
    for (const width of [320, 390, 768, 1024, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        await page.evaluate(theme => localStorage.setItem('tnr-theme', theme), width <= 390 ? 'dark' : 'light');
        for (const path of ['/', '/booking', '/profile']) {
            await page.goto(path);
            await dismissTour(page);
            if (path === '/profile' && width > 1100) await page.goto('/profile#profile-appointments');
            const trigger = page.locator(width <= 1100
                ? '.customer-mobile-shortcuts [data-tnr-open-transactions]'
                : path === '/' ? '.nav-appointments-direct' : '.profile-txn-open');
            if (width > 1100 && path === '/booking') continue;
            await expect(trigger.locator('[data-appointment-count]')).toHaveText('2');
            await expect(trigger.locator('[data-appointment-count]')).toBeVisible();
            await expect(trigger).toHaveAttribute('aria-description', '2 upcoming appointments');
            await trigger.click();
            await expect(page.locator('#tnr-transactions-modal')).toBeVisible();
            await page.locator('#tnr-transactions-modal [data-tnr-txn-close]').last().click();
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
            if (path === '/') await page.screenshot({ path: testInfo.outputPath(`appointment-badge-${width}.png`), animations: 'disabled' });
        }
    }
    await page.goto('/');
    await dismissTour(page);
    const trigger = page.locator('.nav-appointments-direct');
    await trigger.click();
    const upcomingTab = page.locator('[data-txn-group="upcoming"]');
    if (await upcomingTab.count()) await upcomingTab.click();
    for (const remaining of [1, 0]) {
        await page.locator('#txn-cards-list [data-tnr-cancel-booking]').first().click();
        await page.locator('#tnr-cancel-booking-form [value="schedule_conflict"]').check();
        await page.getByRole('button', { name: 'Confirm cancellation', exact: true }).click();
        await expect(trigger).toHaveAttribute('aria-description', `${remaining} upcoming appointment${remaining === 1 ? '' : 's'}`);
    }
    await expect(trigger.locator('[data-appointment-count]')).toBeHidden();
    upcoming = 105;
    await page.goto('/');
    await expect(trigger.locator('[data-appointment-count]')).toHaveText('99+');
    await expect(trigger).toHaveAttribute('aria-description', '105 upcoming appointments');
    upcoming = 0;
    await page.goto('/');
    await expect(trigger.locator('[data-appointment-count]')).toBeHidden();
    await expect(trigger).toHaveAttribute('aria-description', '0 upcoming appointments');
    upcoming = 1;
    expiringHold = true;
    await page.clock.install();
    await page.goto('/');
    await expect(trigger.locator('[data-appointment-count]')).toHaveText('1');
    await page.clock.fastForward(61_000);
    await expect(trigger.locator('[data-appointment-count]')).toBeHidden();
    await expect(trigger).toHaveAttribute('aria-description', '0 upcoming appointments');
});

test('Aromatherapy retains an available therapist without a matching specialty after date changes', async ({ page }) => {
    await login(page);
    await page.route('**/booking', async route => {
        const response = await route.fetch();
        const body = (await response.text()).replace(/data-therapist-specialties='[^']*'/g, "data-therapist-specialties='[]'");
        await route.fulfill({ response, body });
    });
    await page.goto('/booking');
    await dismissTour(page);
    await page.locator('.service-item').filter({ has: page.locator('input[value="Aromatherapy"]') }).click();
    const therapist = page.locator('.therapist-item:not(.therapist-item-any)').first();
    await therapist.click();
    const name = await therapist.locator('input').inputValue();
    const date = page.locator('#booking_date');
    const monday = await date.evaluate(input => {
        const next = new Date(input.min + 'T12:00:00');
        next.setDate(next.getDate() + ((8 - next.getDay()) % 7 || 7));
        return next.toISOString().slice(0, 10);
    });
    for (const selectedDate of [monday, new Date(new Date(monday + 'T12:00:00').getTime() + 86400000).toISOString().slice(0, 10)]) {
        const scheduleResponse = page.waitForResponse(response => response.url().includes('/booking/therapist-availability?') && response.url().includes(selectedDate));
        await date.fill(selectedDate);
        const schedule = await (await scheduleResponse).json();
        expect(schedule.therapists[name].bookable).toBe(true);
        await expect(page.locator('#time-slots .time-slot:not([disabled])').first()).toBeVisible();
        await expect(therapist.locator('input')).toBeChecked();
        await expect(page.locator('#booking-selected-therapist')).toHaveText(name);
    }
});

test('landing header changes from light at the top to dark after scrolling', async ({ page }, testInfo) => {
    await login(page);
    for (const width of [390, 1366]) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/');
        await dismissTour(page);
        const landingHeader = page.locator('.landing-header');
        const shell = landingHeader.locator('.nav-shell');
        await expect(landingHeader).not.toHaveClass(/scrolled/);
        await expect.poll(() => shell.evaluate(node => getComputedStyle(node).backgroundColor)).toBe('rgb(247, 245, 241)');
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

test('mobile account menu stays readable before and after scrolling in both themes', async ({ page }, testInfo) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await login(page);
    const contrast = async (text, surface) => page.evaluate(({ text, surface }) => {
        const luminance = color => {
            const rgb = color.match(/[\d.]+/g).slice(0, 3).map(Number).map(value => {
                value /= 255;
                return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
            });
            return rgb[0] * 0.2126 + rgb[1] * 0.7152 + rgb[2] * 0.0722;
        };
        const foreground = luminance(getComputedStyle(document.querySelector(text)).color);
        const background = luminance(getComputedStyle(document.querySelector(surface)).backgroundColor);
        return (Math.max(foreground, background) + 0.05) / (Math.min(foreground, background) + 0.05);
    }, { text, surface });
    for (const theme of ['light', 'dark']) {
        for (const width of [320, 390, 768, 1100]) {
            await page.setViewportSize({ width, height: 900 });
            await page.evaluate(mode => {
                localStorage.setItem('tnr-theme', mode);
                localStorage.setItem('tnr-theme-preference-version', 'light-default-v1');
            }, theme);
            await page.goto('/');
            await dismissTour(page);
            const header = page.locator('.landing-header');
            const account = header.locator('.nav-user-wrap--mobile');
            for (const scroll of [0, 220]) {
                await page.evaluate(y => window.scrollTo(0, y), scroll);
                if (scroll) await expect(header).toHaveClass(/scrolled/);
                else await expect(header).not.toHaveClass(/scrolled/);
                if (!(await header.evaluate(node => node.classList.contains('is-mobile-nav-open')))) {
                    await header.locator('.mobile-nav-toggle').click();
                }
                if ((await account.locator('.nav-user').getAttribute('aria-expanded')) !== 'true') {
                    await account.locator('.nav-user').click();
                }
                await expect(account.locator('.nav-user-menu')).toBeVisible();
                await expect.poll(() => contrast('.nav-user-wrap--mobile .nav-username', '.nav-user-wrap--mobile .nav-user')).toBeGreaterThanOrEqual(4.5);
                await expect.poll(() => contrast('.nav-user-wrap--mobile .nav-avatar', '.nav-user-wrap--mobile .nav-avatar')).toBeGreaterThanOrEqual(4.5);
                await expect.poll(() => contrast('.nav-user-wrap--mobile [role="menuitem"]', '.nav-user-wrap--mobile .nav-user-menu')).toBeGreaterThanOrEqual(4.5);
                const profile = account.getByRole('menuitem', { name: 'Edit Profile', exact: true });
                await expect(profile).toBeInViewport();
                await profile.focus();
                await expect(profile).toBeFocused();
                expect(await header.locator('.nav-links').evaluate(menu => menu.scrollWidth <= menu.clientWidth + 1)).toBe(true);
                if (width === 390) await page.screenshot({ path: testInfo.outputPath(`account-menu-${theme}-${scroll}.png`), animations: 'disabled' });
            }
            await account.getByRole('menuitem', { name: 'Edit Profile', exact: true }).click();
            await expect(page).toHaveURL(/\/profile$/);
            await expect(page.locator('#personal-information')).toBeVisible();
        }
    }
});

test('checkout stays gray until the no-show policy is accepted', async ({ page }, testInfo) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await login(page);
    // Only simulate configured checkout in the browser. Do not submit a payment.
    let configured = true;
    await page.route('**/booking', async route => {
        const response = await route.fetch();
        const body = (await response.text()).replace(/var paymongoEnabled = (true|false);/, `var paymongoEnabled = ${configured};`);
        await route.fulfill({ response, body });
    });
    for (const scenario of [{ width: 390, enabled: true }, { width: 768, enabled: true }, { width: 1366, enabled: true }, { width: 1366, enabled: false }]) {
        const width = scenario.width;
        configured = scenario.enabled;
        await page.setViewportSize({ width, height: 900 });
        await page.evaluate(theme => localStorage.setItem('tnr-theme', theme), width === 390 ? 'dark' : 'light');
        await page.goto('/booking');
        await dismissTour(page);
        await page.locator('.service-item').first().click();
        await page.locator('.therapist-item-any').click();
        const date = page.locator('#booking_date');
        const monday = await date.evaluate(input => {
            const next = new Date(input.min + 'T12:00:00');
            next.setDate(next.getDate() + ((8 - next.getDay()) % 7 || 7));
            return next.toISOString().slice(0, 10);
        });
        await date.fill(monday);
        await page.locator('#time-slots .time-slot:not([disabled])').first().click();
        await page.locator('.booking-submit').click();
        const modal = page.locator('#bkConfirmModal');
        const submit = modal.locator('#bkConfirmSubmit');
        const checkbox = modal.locator('#bkNoShowPolicyAccepted');
        const wrapper = modal.locator('#bkCheckoutControl');
        await expect(modal).toBeVisible();
        await expect(modal.locator('[data-payment-type="downpayment"]')).toHaveAttribute('aria-pressed', 'true');
        await expect(submit).toBeDisabled();
        await expect(submit).toHaveCSS('background-color', width === 390 ? 'rgb(60, 71, 65)' : 'rgb(220, 226, 229)');
        await expect(wrapper).toHaveAttribute('title', /Check/);
        await wrapper.focus();
        await expect(wrapper).toBeFocused();
        await expect(modal.locator('#bkCheckoutHint')).toContainText('Check “I understand');
        await page.screenshot({ path: testInfo.outputPath(`payment-unchecked-${width}.png`), animations: 'disabled' });
        await checkbox.check();
        await expect(submit).toBeEnabled({ enabled: configured });
        await expect(modal.locator('#bkCheckoutHint')).toContainText(configured ? 'You will continue to PayMongo' : 'Online payment is unavailable');
        await checkbox.uncheck();
        await expect(submit).toBeDisabled();
        await checkbox.check();
        const amount = async id => Number((await modal.locator(id).textContent()).replace(/[^\d.]/g, ''));
        expect(await amount('#bkSumAmountDue') + await amount('#bkSumBalance')).toBeCloseTo(await amount('#bkSumServicePrice'), 2);
        await modal.locator('[data-payment-type="full"]').click();
        await expect(modal.locator('[data-payment-type="full"]')).toHaveAttribute('aria-pressed', 'true');
        await expect(modal.locator('[data-payment-type="downpayment"]')).toHaveAttribute('aria-pressed', 'false');
        expect(await amount('#bkSumBalance')).toBe(0);
        await expect(submit).toBeEnabled({ enabled: configured });
        await page.screenshot({ path: testInfo.outputPath(`payment-accepted-${width}-${configured}.png`), animations: 'disabled' });
        expect(await modal.locator('.bk-payment-body').evaluate(body => body.scrollWidth <= body.clientWidth + 1)).toBe(true);
        await modal.getByRole('button', { name: 'Close', exact: true }).click();
    }
});

test('booking selections and payment amounts remain clear when choices change', async ({ page }, testInfo) => {
    await login(page);
    for (const width of [320, 390, 768, 1366]) {
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
        await expect(page.getByRole('heading', { name: 'Your selections', exact: true })).toBeVisible();
        await page.locator('#booking-selection-summary').scrollIntoViewIfNeeded();
        expect(await page.locator('#booking-selection-summary').evaluate(summary => summary.scrollWidth <= summary.clientWidth + 1)).toBe(true);
        await page.screenshot({ path: testInfo.outputPath(`booking-selections-${width}.png`), animations: 'disabled' });
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
                await page.goto('/profile#profile-appointments');
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
