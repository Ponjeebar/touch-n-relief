import { expect, test } from '@playwright/test';

for (const width of [390, 768, 1366]) {
    test(`signup navigates and validates each step at ${width}px`, async ({ page }, testInfo) => {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/login?register=1');
        const form = page.locator('#register-form');
        const name = form.locator('[name="name"]');
        await expect(name).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath('personal-step.png'), fullPage: true, animations: 'disabled' });
        for (const value of ['Maria123', '123', 'Maria@Clara', '---']) {
            await name.fill(value);
            expect(await name.evaluate(el => el.validity.patternMismatch), value).toBe(true);
        }
        for (const value of ['María-José O’Neill Jr.', 'Jose\u0301 Dela Cruz', '李明', 'Cher']) {
            await name.fill(value);
            expect(await name.evaluate(el => el.validity.valid), value).toBe(true);
        }
        const next = form.getByRole('button', { name: 'Next', exact: false });
        await next.click();
        await expect(form.locator('[name="email"]')).toBeHidden();
        await form.locator('[name="birthday"]').fill('2000-01-01');
        await form.getByText('Female', { exact: true }).click();
        await name.press('Enter');
        await expect(form.locator('[name="email"]')).toBeVisible();
        await expect(name).toBeHidden();
        await form.locator('[name="email"]').fill('maria@example.com');
        const phone = form.locator('[name="contact_number"]');
        await phone.fill('0912345678x');
        expect(await phone.evaluate(el => el.validity.patternMismatch)).toBe(true);
        await next.click();
        await expect(form.locator('[name="username"]')).toBeHidden();
        await phone.fill('09123456789');
        await form.getByRole('button', { name: 'Back', exact: true }).click();
        await expect(name).toHaveValue('Cher');
        await next.click();
        await expect(phone).toHaveValue('09123456789');
        await next.click();
        const username = form.locator('[name="username"]');
        expect(await page.locator('#container').evaluate(el => el.scrollLeft)).toBe(0);
        await page.screenshot({ path: testInfo.outputPath('account-step.png'), fullPage: true, animations: 'disabled' });
        await username.fill('bad username');
        expect(await username.evaluate(el => el.validity.patternMismatch)).toBe(true);
        await username.fill('maria_clara-2');
        expect(await username.evaluate(el => el.validity.valid)).toBe(true);
        await form.locator('[name="password"]').fill('SecurePassword9');
        await form.locator('[name="password_confirmation"]').fill('DifferentPassword9');
        await form.locator('[name="terms_accepted"]').check();
        await form.getByRole('button', { name: 'Sign Up', exact: true }).click();
        expect(await form.locator('[name="password_confirmation"]').evaluate(el => el.validationMessage)).toBe('Passwords do not match.');
        await expect(page).toHaveURL(/register=1/);
        await form.getByRole('button', { name: 'Sign Up', exact: true }).scrollIntoViewIfNeeded();
        await expect(form.getByRole('button', { name: 'Sign Up', exact: true })).toBeInViewport();
    });
}

test('signup opens the step with server errors and completes email verification handoff', async ({ page }) => {
    test.setTimeout(90_000);
    await page.goto('/login?register=1');
    const form = page.locator('#register-form');
    await form.getByLabel('Full name', { exact: true }).fill('Maria Clara');
    await form.getByLabel('Birthday', { exact: true }).fill('2000-01-01');
    await form.getByText('Female', { exact: true }).click();
    await form.getByRole('button', { name: 'Next' }).click();
    await form.getByLabel('Email address', { exact: true }).fill('customer@browser.test');
    await form.getByLabel('Contact number', { exact: true }).fill('09999998765');
    await form.getByRole('button', { name: 'Next' }).click();
    await form.getByLabel('Username', { exact: true }).fill('wizard_customer');
    await form.getByLabel('Password', { exact: true }).fill('SecurePassword9');
    await form.getByLabel('Confirm password', { exact: true }).fill('SecurePassword9');
    await form.locator('[name="terms_accepted"]').check();
    await form.getByRole('button', { name: 'Sign Up', exact: true }).click({ timeout: 30_000 });
    await expect(form.locator('.field-msg')).toContainText('already been taken', { timeout: 30_000 });
    await expect(form.getByLabel('Email address', { exact: true })).toBeVisible();
    await expect(form.getByLabel('Full name', { exact: true })).toHaveValue('Maria Clara');
    await form.getByLabel('Email address', { exact: true }).fill('wizard-customer@browser.test');
    await form.getByRole('button', { name: 'Next' }).click();
    await expect(form.getByLabel('Username', { exact: true })).toHaveValue('wizard_customer');
    await form.getByLabel('Password', { exact: true }).fill('SecurePassword9');
    await form.getByLabel('Confirm password', { exact: true }).fill('SecurePassword9');
    await form.getByRole('button', { name: 'Sign Up', exact: true }).click({ timeout: 30_000 });
    await expect(page).toHaveURL(/verify-code\/.*purpose=registration/, { timeout: 30_000 });
});
