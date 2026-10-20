import { expect, test } from '@playwright/test';

test.beforeAll(async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    await page.goto('/login');
    const login = page.locator('form[action$="/login"]');
    await login.locator('[name="login"]').fill('admin@browser.test');
    await login.locator('[name="password"]').fill('AuditPassword9');
    await login.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.goto('/landing-settings');
    await expect(page.locator('#owner_name')).toHaveValue('Ronald Bueno');
    await page.locator('#facebook_url').fill('https://www.facebook.com/browser-spa');
    await page.locator('#instagram_url').fill('https://www.instagram.com/browser-spa');
    await page.locator('#landingSettingsForm button[type="submit"]').click();
    await expect(page.locator('#facebook_url')).toHaveValue('https://www.facebook.com/browser-spa');
    await context.close();
});

for (const setup of [
    { role: 'guest', width: 390, theme: 'light' },
    { role: 'customer', width: 768, theme: 'dark' },
    { role: 'admin', width: 1366, theme: 'light' },
    { role: 'receptionist', width: 390, theme: 'dark' },
]) {
    test(`${setup.role} can ask business questions at ${setup.width}px`, async ({ page }, testInfo) => {
        await page.setViewportSize({ width: setup.width, height: 900 });
        await page.addInitScript(theme => {
            localStorage.setItem('tnr-theme-preference-version', 'light-default-v1');
            localStorage.setItem('tnr-theme', theme);
        }, setup.theme);
        if (setup.role !== 'guest') {
            await page.goto('/login');
            const login = page.locator('form[action$="/login"]');
            await login.locator('[name="login"]').fill(`${setup.role}@browser.test`);
            await login.locator('[name="password"]').fill('AuditPassword9');
            await login.locator('button[type="submit"]').click();
            await expect(page).not.toHaveURL(/\/login$/);
        }
        await page.goto('/');
        if (setup.role === 'receptionist' && setup.width < 768) {
            await page.locator('[data-staff-mobile-menu]').click();
            await page.locator('[data-staff-chat-open]').click();
        } else {
            await page.locator('.tnr-chat-launcher').click();
        }
        const chat = page.locator('.tnr-chat-panel');
        async function ask(question) {
            await chat.locator('input').fill(question);
            await chat.getByRole('button', { name: 'Send', exact: true }).click();
            await expect(chat.locator('.is-pending')).toHaveCount(0);
            return chat.locator('.tnr-chat-bot').last();
        }
        await expect(await ask('who are you')).toContainText('TouchNRelief virtual assistant');
        await expect(await ask('Who owns the spa?')).toContainText('Ronald Bueno');
        await expect(await ask('Sino ang may-ari?')).toContainText('Ronald Bueno');
        const social = await ask('social media accounts');
        const facebook = social.getByRole('link', { name: 'Open Facebook' });
        await expect(facebook).toHaveAttribute('href', 'https://www.facebook.com/browser-spa');
        await expect(facebook).toHaveAttribute('target', '_blank');
        await expect(facebook).toHaveAttribute('rel', 'noopener noreferrer');
        await expect(social.getByRole('link', { name: 'Open Instagram' })).toHaveAttribute('href', 'https://www.instagram.com/browser-spa');
        await page.screenshot({ path: testInfo.outputPath(`business-chat-${setup.role}.png`) });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2)).toBeTruthy();

        await page.route('**/chatbot/message', route => route.fulfill({ json: {
            reply: 'Link filtering check', suggestions: [], actions: [
                { label: 'Unsafe scheme', url: 'javascript:alert(1)', external: true },
                { label: 'Lookalike domain', url: 'https://facebook.com.evil.test', external: true },
                { label: 'Unapproved external site', url: 'https://example.test', external: true },
            ],
        } }));
        const filtered = await ask('Link filtering check');
        await expect(filtered.getByRole('link')).toHaveCount(0);
    });
}
