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
            await page.getByLabel('Digit 1 of 6', { exact: true }).pressSequentially(width === 390 ? '000000' : '00000');
            await expect(page.getByRole('button', { name: 'Send a new code' })).toBeVisible();
            if (width === 390) {
                await expect(page.locator('#verification-code-error')).toContainText('Incorrect code.');
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
                await expect(page.locator('#verification-code')).toHaveValue('1234');
                await digits.first().evaluate(input => {
                    input.value = '654321';
                    input.dispatchEvent(new InputEvent('input', { bubbles: true, inputType: 'insertReplacementText', data: '654321' }));
                });
                await expect(page.locator('#verification-code')).toHaveValue('654321');
                await digits.last().fill('');
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

test('password recovery automatically checks the code and shows success only after server verification', async ({ page }, testInfo) => {
    const recoveryId = randomUUID();
    fixture(`
        $user = App\\Models\\User::where('email', 'customer@browser.test')->firstOrFail();
        $verification = new App\\Models\\AuthVerificationCode;
        $verification->id = '${recoveryId}';
        $verification->fill(['purpose' => 'password_reset', 'email' => $user->email,
            'code_hash' => Illuminate\\Support\\Facades\\Hash::make('123456'), 'payload' => ['user_id' => $user->id],
            'failed_attempts' => 0, 'expires_at' => now()->addHour(), 'last_sent_at' => now()]);
        $verification->save();
    `);
    try {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto(`/verify-code/${recoveryId}?purpose=password_reset`);
        const digits = page.locator('[data-code-digits] input');
        await expect(page.getByRole('button', { name: 'Verify code', exact: true })).toBeHidden();
        await digits.first().pressSequentially('000000');
        await expect(page.locator('#verification-code-error')).toContainText('Incorrect code.');
        await expect(page.locator('.auth-recovery-verified')).toHaveCount(0);
        let requests = 0;
        await page.route(`**/verify-code/${recoveryId}`, async route => {
            if (route.request().method() !== 'POST') return route.continue();
            requests++;
            await route.continue();
        });
        // Hold the first native navigation so the checking UI can be inspected.
        await page.evaluate(() => document.querySelector('[data-recovery-code]').addEventListener('submit', event => event.preventDefault(), { once: true }));
        await digits.first().evaluate(input => {
            const clipboard = new DataTransfer();
            clipboard.setData('text', '12 34-56');
            input.dispatchEvent(new ClipboardEvent('paste', { clipboardData: clipboard, bubbles: true, cancelable: true }));
        });
        await expect(page.locator('[data-code-status]')).toHaveText('Checking code…');
        await expect(digits.first()).toHaveAttribute('readonly', '');
        await expect(page.locator('.auth-recovery-verified')).toHaveCount(0);
        await page.screenshot({ path: testInfo.outputPath('recovery-checking-mobile.png') });
        await digits.last().press('Enter');
        expect(requests).toBe(0);
        await page.evaluate(() => document.querySelector('[data-recovery-code]').submit());
        await expect(page).toHaveURL(/\/reset-password\//);
        await expect(page.locator('.auth-recovery-verified')).toContainText('Code verified');
        await expect(page.getByRole('heading', { name: 'Choose a new password' })).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath('recovery-verified-mobile.png') });
        expect(requests).toBe(1);
        for (const theme of ['light', 'dark']) {
            await page.evaluate(theme => { document.documentElement.dataset.theme = theme; }, theme);
            for (const [width, height] of [[1920, 920], [1366, 768], [1280, 600], [800, 450], [390, 844], [320, 667]]) {
                await page.setViewportSize({ width, height });
                await page.evaluate(() => window.scrollTo(0, 0));
                for (const element of [
                    page.locator('.auth-recovery-verified'),
                    page.getByRole('heading', { name: 'Choose a new password' }),
                    page.getByLabel('Email address', { exact: true }),
                    page.getByLabel('New password', { exact: true }),
                    page.getByLabel('Confirm new password', { exact: true }),
                    page.getByRole('button', { name: 'Save new password', exact: true }),
                    page.getByRole('link', { name: 'Back to Sign In', exact: true }),
                ]) {
                    const card = await page.locator('#container').boundingBox();
                    const bounds = await element.boundingBox();
                    expect(bounds.y, `clipped top at ${width}/${theme}`).toBeGreaterThanOrEqual(card.y);
                    expect(bounds.y + bounds.height, `clipped bottom at ${width}/${theme}`).toBeLessThanOrEqual(card.y + card.height);
                    await element.scrollIntoViewIfNeeded();
                    await expect(element).toBeInViewport();
                }
                await page.evaluate(() => window.scrollTo(0, 0));
                await page.screenshot({ path: testInfo.outputPath(`verified-reset-${width}-${theme}.png`), fullPage: true });
                expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
            }
        }
    } finally {
        fixture(`App\\Models\\AuthVerificationCode::whereKey('${recoveryId}')->delete();`);
    }
});
