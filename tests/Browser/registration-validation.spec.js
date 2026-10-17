import { expect, test } from '@playwright/test';

for (const width of [390, 768, 1366]) {
    test(`signup validates personal fields at ${width}px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/login?register=1');
        const form = page.locator('#register-form');
        const name = form.locator('[name="name"]');
        await expect(name).toBeVisible();
        for (const value of ['Maria123', '123', 'Maria@Clara', '---']) {
            await name.fill(value);
            expect(await name.evaluate(el => el.validity.patternMismatch), value).toBe(true);
        }
        for (const value of ['María-José O’Neill Jr.', 'Jose\u0301 Dela Cruz', '李明', 'Cher']) {
            await name.fill(value);
            expect(await name.evaluate(el => el.validity.valid), value).toBe(true);
        }
        const username = form.locator('[name="username"]');
        await username.fill('bad username');
        expect(await username.evaluate(el => el.validity.patternMismatch)).toBe(true);
        await username.fill('maria_clara-2');
        expect(await username.evaluate(el => el.validity.valid)).toBe(true);
        const phone = form.locator('[name="contact_number"]');
        await phone.fill('0912345678x');
        expect(await phone.evaluate(el => el.validity.patternMismatch)).toBe(true);
        await phone.fill('09123456789');
        expect(await phone.evaluate(el => el.validity.valid)).toBe(true);
        await form.getByRole('button', { name: 'Sign Up', exact: true }).scrollIntoViewIfNeeded();
        await expect(form.getByRole('button', { name: 'Sign Up', exact: true })).toBeInViewport();
    });
}
