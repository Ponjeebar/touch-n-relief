import { expect, test } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';

const verificationId = randomUUID();
function fixture(command) {
    execFileSync('php', ['artisan', 'tinker', '--env=e2e', '--execute', `
        if (!app()->environment('e2e') || config('database.default') !== 'sqlite'
            || !str_ends_with(config('database.connections.sqlite.database'), 'browser-tests.sqlite')) {
            throw new RuntimeException('Browser fixture requires the isolated test database.');
        }
        ${command}
    `], { stdio: 'pipe' });
}

test.beforeAll(() => fixture(`
    $verification = new App\\Models\\AuthVerificationCode;
    $verification->id = '${verificationId}';
    $verification->fill(['purpose' => 'password_reset', 'email' => 'account-preview@browser.test',
        'code_hash' => Illuminate\\Support\\Facades\\Hash::make('123456'), 'payload' => [],
        'failed_attempts' => 0, 'expires_at' => now()->addHour(), 'last_sent_at' => now()]);
    $verification->save();
`));
test.afterAll(() => fixture(`App\\Models\\AuthVerificationCode::whereKey('${verificationId}')->delete();`));

for (const theme of ['light', 'dark']) {
    test(`verification and password reset stay usable in ${theme} mode`, async ({ page }, testInfo) => {
        test.setTimeout(120_000);
        await page.addInitScript(mode => {
            localStorage.setItem('tnr-theme-preference-version', 'light-default-v1');
            localStorage.setItem('tnr-theme', mode);
        }, theme);
        for (const width of [320, 390, 768, 1440]) {
            await page.setViewportSize({ width, height: 768 });
            await page.goto(`/verify-code/${verificationId}?purpose=password_reset`);
            await expect(page.getByRole('heading', { name: 'Enter your code' })).toBeVisible();
            await expect(page.getByText('account-preview@browser.test', { exact: true })).toBeVisible();
            const digits = page.locator('[data-code-digits] input');
            await expect(digits).toHaveCount(6);
            await page.getByLabel('Digit 1 of 6', { exact: true }).pressSequentially('000000');
            await expect(page.locator('#verification-code')).toHaveValue('000000');
            await expect(page.getByRole('button', { name: 'Send a new code' })).toBeVisible();
            if (width === 390) {
                await page.getByRole('button', { name: 'Verify code', exact: true }).click();
                await expect(page.locator('.field-msg')).toContainText('Incorrect code.');
                for (const input of await digits.all()) await expect(input).toHaveAttribute('aria-invalid', 'true');
                await digits.nth(2).evaluate(input => {
                    const clipboard = new DataTransfer();
                    clipboard.setData('text', '12 34-56');
                    input.dispatchEvent(new ClipboardEvent('paste', { clipboardData: clipboard, bubbles: true, cancelable: true }));
                });
                await expect(page.locator('#verification-code')).toHaveValue('123456');
                await digits.nth(5).press('Backspace');
                await digits.nth(5).press('Backspace');
                await expect(digits.nth(4)).toBeFocused();
                await expect(digits.nth(4)).toHaveValue('');
                await digits.nth(4).press('ArrowLeft');
                await expect(digits.nth(3)).toBeFocused();
                await page.getByRole('button', { name: 'Verify code', exact: true }).click();
                await expect(digits.nth(4)).toBeFocused();
                await digits.first().evaluate(input => {
                    input.value = '654321';
                    input.dispatchEvent(new InputEvent('input', { bubbles: true, inputType: 'insertReplacementText', data: '654321' }));
                });
                await expect(page.locator('#verification-code')).toHaveValue('654321');
                if (theme === 'light') {
                    for (const input of await digits.all()) await input.fill('');
                    await page.getByRole('button', { name: 'Send a new code' }).click();
                    await expect(page.locator('#verification-code-error')).toContainText('Please wait');
                }
            }
            await page.screenshot({ path: testInfo.outputPath(`verification-${width}.png`), fullPage: true });
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
            await page.getByRole('link', { name: 'Back to Sign In', exact: true }).click();
            await expect(page.locator('.auth-login-panel')).toBeVisible();

            await page.goto('/reset-password/layout-preview?email=customer%40browser.test');
            await expect(page.getByLabel('Email address', { exact: true })).toHaveValue('customer@browser.test');
            await page.getByLabel('New password', { exact: true }).fill('PreviewPassword9!');
            await page.getByLabel('Confirm new password', { exact: true }).fill('PreviewPassword9!');
            const save = page.getByRole('button', { name: 'Save new password', exact: true });
            await save.scrollIntoViewIfNeeded();
            await expect(save).toBeInViewport();
            await expect(page.getByRole('link', { name: 'Back to home', exact: true })).toBeInViewport();
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
            await page.evaluate(() => window.scrollTo(0, 0));
            await page.screenshot({ path: testInfo.outputPath(`reset-${width}.png`), fullPage: true });
        }
    });
}

test.describe('verification without JavaScript', () => {
    test.use({ javaScriptEnabled: false });
    test('keeps the single code field usable as a fallback', async ({ page }) => {
        await page.goto(`/verify-code/${verificationId}?purpose=password_reset`);
        const code = page.getByLabel('Verification code', { exact: true });
        await expect(code).toBeVisible();
        await code.fill('123');
        expect(await code.evaluate(input => input.validity.patternMismatch)).toBe(true);
        await code.fill('123456');
        expect(await code.evaluate(input => input.validity.valid)).toBe(true);
        await expect(page.locator('[data-code-digits]')).toBeHidden();
    });
});
