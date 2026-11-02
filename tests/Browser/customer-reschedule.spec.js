import { expect, test } from '@playwright/test';

test.setTimeout(180_000);

test('customer can switch therapist without a new payment and review a later cancellation', async ({ page }, testInfo) => {
    await page.goto('/login');
    await page.locator('.auth-login-panel [name="login"]').fill('customer@browser.test');
    await page.locator('.auth-login-panel [name="password"]').fill('AuditPassword9');
    await page.locator('.auth-login-panel button[type="submit"]').click();
    await expect(page).not.toHaveURL(/\/login$/);
    const date = new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 10);
    let saved = false;
    let rejectSave = false;
    let rejectPreview = false;
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route(/\/$/, async route => {
        const response = await route.fetch();
        let body = (await response.text()).replace(/<ul class="txn-cards" id="txn-cards-list"[\s\S]*?<\/ul>/, '');
        const card = `<ul id="txn-cards-list" class="txn-cards"><li class="txn-card" data-appointment-group="upcoming">
            <span class="txn-ref">BKG-990001</span><span class="txn-amount-pill">₱80.00</span>
            <dl class="txn-meta"><div><dt>Therapist</dt><dd>Maria Santos</dd></div><div><dt>Date</dt><dd>${date}</dd></div><div><dt>Time</dt><dd>10:00 AM</dd></div></dl>
            <button data-tnr-reschedule-booking data-reschedule-url="/bookings/990001/reschedule" data-reschedule-availability-url="/bookings/990001/reschedule/availability" data-booking-id="990001" data-service="Aromatherapy" data-therapist="Maria Santos" data-booking-date="${date}" data-time-slot="10:00 AM" data-paid-amount="₱40.00" data-remaining-balance="₱40.00">Reschedule fixture appointment</button>
            <button data-tnr-cancel-booking data-cancel-url="/bookings/990001/cancel" data-cancellation-preview-url="/bookings/990001/cancellation-preview">Cancel fixture appointment</button>
        </li></ul>`;
        body = body.replace('<div class="txn-modal-body">', `<div class="txn-modal-body">${card}`);
        await route.fulfill({ response, body });
    });
    await page.route('**/bookings/990001/reschedule/availability?*', route => route.fulfill({ json: {
        offered_slots: ['10:00 AM', '11:00 AM'], booked_slots: [], past_slots: [], user_conflicts: {},
        therapists: { 'Maria Santos': { bookable: true }, 'Juan dela Cruz': { bookable: true } },
        therapist_schedule: { bookable: true },
    } }));
    await page.route('**/bookings/990001/reschedule', async route => {
        const choice = route.request().postDataJSON();
        expect(choice).toEqual({ therapist: 'Juan dela Cruz', booking_date: date, time_slot: '10:00 AM' });
        if (rejectSave) return route.fulfill({ status: 422, json: { errors: { time_slot: ['That time was just reserved. Choose another time.'] } } });
        saved = true;
        await route.fulfill({ json: { message: 'Appointment rescheduled.', booking: {
            therapist_name: choice.therapist, booking_date: date, time_slot: choice.time_slot,
            date_display: date, sort_ts: 2000000000, activity_ts: 2000000000, can_cancel: true, can_reschedule: true,
        } } });
    });
    await page.route('**/bookings/990001/cancellation-preview', route => rejectPreview
        ? route.fulfill({ status: 503, json: { message: 'Preview unavailable.' } })
        : route.fulfill({ json: {
        can_cancel: true, was_rescheduled: saved, current_appointment: `Aromatherapy · Juan dela Cruz · ${date} at 10:00 AM`,
        previous_appointment: `Maria Santos · ${date} at 10:00 AM`, paid_amount: '₱40.00', refund_amount: '₱40.00',
        message: 'Eligible refund: ₱40.00. Online payments follow the existing gateway refund process.',
    } }));
    for (const width of [390, 768, 1440]) {
        saved = false;
        await page.setViewportSize({ width, height: 900 });
        await page.evaluate(theme => localStorage.setItem('tnr-theme', theme), width === 390 ? 'dark' : 'light');
        await page.goto('/');
        const skip = page.getByRole('button', { name: 'Skip tour', exact: true });
        if (await skip.isVisible()) await skip.click();
        await page.locator(width <= 1100 ? '.customer-mobile-shortcuts [data-tnr-open-transactions]' : '.nav-appointments-direct').click();
        const upcoming = page.locator('[data-txn-group="upcoming"]');
        if (await upcoming.count()) await upcoming.click();
        await page.getByRole('button', { name: 'Reschedule fixture appointment' }).click();
        const modal = page.locator('#tnr-reschedule-booking-modal');
        await expect(modal.locator('.txn-reschedule-slot').first()).toBeDisabled();
        await modal.locator('#tnr-reschedule-therapist-choice').selectOption('Juan dela Cruz');
        await expect(modal.locator('.txn-reschedule-slot').first()).toBeEnabled();
        await modal.locator('.txn-reschedule-slot').first().click();
        await expect(modal.locator('#tnr-reschedule-paid')).toHaveText('₱40.00');
        await expect(modal.locator('#tnr-reschedule-balance')).toHaveText('₱40.00');
        await expect(modal.locator('#tnr-reschedule-proposed')).toContainText('Juan dela Cruz');
        await page.screenshot({ path: testInfo.outputPath(`reschedule-${width}.png`), animations: 'disabled' });
        rejectSave = true;
        await modal.getByRole('button', { name: 'Save new schedule', exact: true }).click();
        await expect(modal.locator('[role="alert"]')).toContainText('just reserved');
        await expect(page.locator('#txn-cards-list .txn-meta dd').first()).toHaveText('Maria Santos');
        rejectSave = false;
        await modal.getByRole('button', { name: 'Save new schedule', exact: true }).click();
        await expect(modal).toHaveAttribute('aria-hidden', 'true');
        await expect(page.locator('#txn-cards-list .txn-meta dd').first()).toHaveText('Juan dela Cruz');
        await expect(page.locator('#txn-cards-list [data-tnr-reschedule-booking]')).toHaveAttribute('data-booking-label', /Juan dela Cruz/);
        const cancellation = page.locator('#tnr-cancel-booking-modal');
        rejectPreview = true;
        await page.getByRole('button', { name: 'Cancel fixture appointment' }).click();
        await expect(cancellation.locator('#tnr-cancel-refund-notice')).toContainText('Could not check cancellation eligibility');
        await expect(cancellation.getByRole('button', { name: 'Confirm cancellation' })).toBeDisabled();
        await cancellation.getByRole('button', { name: 'Back to transaction', exact: true }).click();
        rejectPreview = false;
        await page.getByRole('button', { name: 'Cancel fixture appointment' }).click();
        await expect(cancellation.locator('#tnr-cancel-booking-ref')).toContainText('Juan dela Cruz');
        await expect(cancellation.locator('#tnr-cancel-rescheduled-note')).toContainText('Maria Santos');
        await expect(cancellation.locator('#tnr-cancel-refund-notice')).toContainText('Eligible refund: ₱40.00');
        await expect(cancellation.getByRole('button', { name: 'Confirm cancellation' })).toBeEnabled();
        await page.screenshot({ path: testInfo.outputPath(`cancellation-${width}.png`), animations: 'disabled' });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    }
    expect(errors).toEqual([]);
});
