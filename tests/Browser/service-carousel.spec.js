import { expect, test } from '@playwright/test';

test.use({ hasTouch: true });

for (const setup of [
    { name: 'guest mobile light', width: 360, height: 800, theme: 'light', customer: false },
    { name: 'customer mobile dark', width: 390, height: 844, theme: 'dark', customer: true },
    { name: 'guest tablet dark', width: 768, height: 1024, theme: 'dark', customer: false },
    { name: 'guest desktop light', width: 1366, height: 768, theme: 'light', customer: false },
    { name: 'customer desktop dark', width: 1366, height: 768, theme: 'dark', customer: true },
]) {
    test(`service browsing is manual and usable for ${setup.name}`, async ({ page, context }, testInfo) => {
        await page.setViewportSize(setup);
        await page.addInitScript(theme => {
            localStorage.setItem('tnr-theme-preference-version', 'light-default-v1');
            localStorage.setItem('tnr-theme', theme);
        }, setup.theme);
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        if (setup.customer) {
            await page.goto('/login');
            const form = page.locator('form[action$="/login"]');
            await form.locator('[name="login"]').fill('customer@browser.test');
            await form.locator('[name="password"]').fill('AuditPassword9');
            await Promise.all([page.waitForURL('http://127.0.0.1:8010/'), form.locator('[type="submit"]').click()]);
        } else {
            await page.goto('/');
        }
        await expect(page.locator('.hero + #services')).toBeVisible();
        expect(await page.locator('#about').evaluate(el =>
            Boolean(document.querySelector('#therapists').compareDocumentPosition(el) & Node.DOCUMENT_POSITION_FOLLOWING)
        )).toBe(true);
        const viewport = page.locator('.carousel-track-wrap');
        const cards = viewport.locator('.carousel-slide');
        expect(await cards.count()).toBeGreaterThan(3);
        await viewport.scrollIntoViewIfNeeded();
        await expect(viewport).toBeVisible();
        await page.locator('#services').screenshot({ path: testInfo.outputPath('service-carousel.png'), animations: 'disabled' });

        if (setup.width <= 900) {
            await expect(page.getByText('Swipe left or right to see more services.')).toBeVisible();
            await expect(page.getByRole('button', { name: 'Next services', exact: true })).toBeHidden();
            const bounds = await viewport.boundingBox();
            const nextCard = await cards.nth(1).boundingBox();
            expect(nextCard.x).toBeLessThan(bounds.x + bounds.width);
            expect(nextCard.x).toBeGreaterThan(bounds.x);
            const client = await context.newCDPSession(page);
            async function swipe(dx, dy) {
                const area = await viewport.boundingBox();
                const x = area.x + area.width * (dx > 0 ? 0.2 : 0.8);
                const y = Math.min(area.y + 120, setup.height - 100);
                await client.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x, y }] });
                for (let step = 1; step <= 10; step += 1) {
                    await client.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: x + dx * step / 10, y: y + dy * step / 10 }] });
                    await page.waitForTimeout(25);
                }
                await client.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
            }
            await swipe(-bounds.width * 0.65, 0);
            await expect.poll(() => viewport.evaluate(el => el.scrollLeft)).toBeGreaterThan(100);
            const activeDot = page.locator('[data-dots] [aria-current="true"]');
            await expect(activeDot).not.toHaveAttribute('aria-label', 'Go to service page 1');
            await expect(page.locator('#serviceModal')).not.toHaveClass(/open/);

            const forwardPosition = await viewport.evaluate(el => el.scrollLeft);
            await swipe(bounds.width * 0.65, 0);
            await expect.poll(() => viewport.evaluate(el => el.scrollLeft)).toBeLessThan(forwardPosition - 50);
            await swipe(-bounds.width * 0.65, 0);
            await expect.poll(() => viewport.evaluate(el => el.scrollLeft)).toBeGreaterThan(100);

            const label = await activeDot.getAttribute('aria-label');
            const selected = cards.nth(Number(label.match(/\d+/)[0]) - 1);
            const name = await selected.getAttribute('data-service-name');
            await selected.tap();
            await expect(page.locator('#serviceModal')).toHaveClass(/open/);
            await expect(page.locator('#serviceModalTitle')).toHaveText(name);
            await page.locator('#serviceModal button[data-close-service-modal]').click();
            await viewport.scrollIntoViewIfNeeded();
            const scrollY = await page.evaluate(() => window.scrollY);
            await swipe(0, -150);
            await expect.poll(() => page.evaluate(() => window.scrollY)).toBeGreaterThan(scrollY + 40);
            await expect(page.locator('#serviceModal')).not.toHaveClass(/open/);
        } else {
            await expect(page.locator('.services-swipe-hint')).toBeHidden();
            const originalX = (await cards.first().boundingBox()).x;
            await page.waitForTimeout(3600);
            expect((await cards.first().boundingBox()).x).toBeCloseTo(originalX, 0);
            await page.getByRole('button', { name: 'Next services', exact: true }).click();
            await expect.poll(() => viewport.evaluate(el => el.scrollLeft)).toBeGreaterThan(100);
            await expect(page.getByRole('button', { name: 'Go to service page 2', exact: true })).toHaveAttribute('aria-current', 'true');
            await page.getByRole('button', { name: 'Go to service page 1', exact: true }).focus();
            await page.keyboard.press('Enter');
            await expect.poll(() => viewport.evaluate(el => el.scrollLeft)).toBeLessThan(1);
            await page.waitForTimeout(3600);
            expect((await cards.first().boundingBox()).x).toBeCloseTo(originalX, 0);
            await page.setViewportSize({ width: 390, height: 844 });
            await expect(page.getByText('Swipe left or right to see more services.')).toBeVisible();
            await expect(page.getByRole('button', { name: 'Next services', exact: true })).toBeHidden();
        }
        await page.locator('.footer-links a[href="#about"]').click();
        await expect(page).toHaveURL(/#about$/);
        await expect(page.getByRole('heading', { name: 'About Buenos Touche and TouchNRelief', exact: true })).toBeInViewport();
        await expect(page.getByRole('heading', { name: 'The TouchNRelief System', exact: true })).toBeVisible();
        await page.locator('#about').screenshot({ path: testInfo.outputPath('about.png'), animations: 'disabled' });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 2)).toBe(true);
        expect(errors).toEqual([]);
    });
}
