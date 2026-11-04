import { expect, test } from '@playwright/test';

test.use({ actionTimeout: 30_000, navigationTimeout: 60_000 });
test.setTimeout(120_000);
test.beforeEach(async ({ page }) => {
    await page.route(/https:\/\/(fonts\.googleapis\.com|fonts\.gstatic\.com)\//, route => route.abort());
});

test('chat remains readable and reachable across mobile, tablet, desktop and themes', async ({ page }, testInfo) => {
    for (const width of [320, 390, 768, 1024, 1440]) {
        for (const theme of ['light', 'dark']) {
            await page.setViewportSize({ width, height: 800 });
            await page.goto('/');
            await page.evaluate(theme => {
                document.documentElement.dataset.theme = theme;
            }, theme);
            await page.locator('.tnr-chat-launcher').click();
            const chat = page.locator('.tnr-chat-panel');
            await expect(chat).toBeVisible();
            await expect(chat.locator('.tnr-chat-header')).toContainText('Automated spa assistant');
            for (const control of [chat.locator('input'), chat.getByRole('button', { name: 'Send', exact: true }), chat.locator('.tnr-chat-close')]) await expect(control).toBeInViewport();
            const rect = await chat.boundingBox();
            expect(rect.x).toBeGreaterThanOrEqual(0);
            expect(rect.x + rect.width).toBeLessThanOrEqual(width);
            expect(rect.y).toBeGreaterThanOrEqual(0);
            if ([320, 768, 1440].includes(width)) await page.screenshot({ path: testInfo.outputPath(`chat-${width}-${theme}.png`) });
            await page.keyboard.press('Escape');
            await expect(chat).toBeHidden();
            await expect(page.locator('.tnr-chat-launcher')).toBeFocused();
        }
    }
});

test('chat retries failures without duplicating the question and prevents duplicate sends', async ({ page }) => {
    await page.goto('/');
    await page.locator('.tnr-chat-launcher').click();
    const chat = page.locator('.tnr-chat-panel');
    let calls = 0;
    let finishReply;
    await page.route('**/chatbot/message', async route => {
        calls++;
        if (calls === 1) return route.fulfill({ status: 503, json: { message: 'Unavailable' } });
        await new Promise(resolve => { finishReply = resolve; });
        await route.fulfill({ json: { reply: 'Recovered reply', actions: [], suggestions: ['Business hours'] } });
    });
    await chat.locator('input').fill('Where are you located?');
    await chat.getByRole('button', { name: 'Send', exact: true }).click();
    const retry = chat.getByRole('button', { name: 'Retry question', exact: true });
    await expect(retry).toBeVisible();
    await retry.click();
    await expect(chat.locator('input')).toBeDisabled();
    await expect(chat.getByRole('button', { name: 'Send', exact: true })).toBeDisabled();
    for (const prompt of await chat.locator('.tnr-chat-prompts button').all()) await expect(prompt).toBeDisabled();
    await expect.poll(() => Boolean(finishReply)).toBe(true);
    finishReply();
    await expect(chat.locator('.tnr-chat-bot').last()).toHaveText('Recovered reply');
    await expect(chat.locator('.tnr-chat-user')).toHaveCount(1);
    await expect(retry).toHaveCount(0);
    await expect(chat.locator('input')).toBeEnabled();
    expect(calls).toBe(2);
    await page.unroute('**/chatbot/message');
    await page.route('**/chatbot/message', route => route.fulfill({ status: 429, json: {} }));
    await chat.locator('input').fill('Business hours');
    await chat.getByRole('button', { name: 'Send', exact: true }).click();
    await expect(chat.locator('.tnr-chat-bot').last()).toContainText('Wait a minute');
});
