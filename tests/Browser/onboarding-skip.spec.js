import { expect, test } from '@playwright/test';

async function login(page, scenario) {
    await page.goto('/login');
    const form = page.locator('form[action$="/login"]');
    await form.locator('[name="login"]').fill(`onboarding-${scenario}@browser.test`);
    await form.locator('[name="password"]').fill('AuditPassword9');
    await form.locator('button[type="submit"]').click();
    await expect(page).toHaveURL('http://127.0.0.1:8010/');
    await expect(page.locator('#tnr-registration-onboarding-modal')).toBeVisible();
}

test('customer can skip unanswered wellness questions and provide preferences later', async ({ page }, testInfo) => {
    await login(page, 'skip');
    const skip = page.getByRole('button', { name: 'Skip for now', exact: true });
    for (const width of [360, 768, 1366]) {
        await page.setViewportSize({ width, height: 900 });
        for (const theme of ['light', 'dark']) {
            await page.evaluate(mode => {
                document.documentElement.setAttribute('data-theme', mode);
                localStorage.setItem('tnr-theme', mode);
                localStorage.setItem('tnr-theme-preference-version', 'light-default-v1');
            }, theme);
            await expect(skip).toBeInViewport();
            const bounds = await skip.boundingBox();
            expect(bounds.x).toBeGreaterThanOrEqual(0);
            expect(bounds.x + bounds.width).toBeLessThanOrEqual(width);
            await page.screenshot({ path: testInfo.outputPath(`onboarding-${width}-${theme}.png`) });
        }
    }
    await page.getByRole('button', { name: 'Male therapist', exact: true }).click();
    await page.getByRole('button', { name: 'Next', exact: true }).click();
    await expect(page.locator('[data-ob-step-pregnancy]')).toBeVisible();
    await skip.focus();
    await page.keyboard.press('Enter');
    await expect(page.locator('#tnr-registration-onboarding-modal')).toHaveCount(0);
    await page.reload();
    await expect(page.locator('#tnr-registration-onboarding-modal')).toHaveCount(0);
    await page.goto('/profile');
    await expect(page.locator('.profile-wellness-badge')).toHaveCount(0);
    for (const name of ['therapist_gender_preference', 'pressure_preference', 'is_pregnant']) {
        await expect(page.locator(`#wellness-section input[name="${name}"]:checked`)).toHaveCount(0);
    }
    await page.locator('#wellness-edit').click();
    await page.locator('[data-wellness-chip="therapist_gender_preference"][data-value="no_preference"]').click();
    await page.locator('[data-wellness-chip="pressure_preference"][data-value="medium"]').click();
    await page.locator('[data-wellness-chip="is_pregnant"][data-value="0"]').click();
    const saved = page.waitForResponse(response => response.url().endsWith('/profile') && response.request().method() === 'POST');
    await page.locator('#wellness-save').click();
    expect((await saved).status()).toBe(302);
    await page.reload();
    await expect(page.locator('.profile-wellness-badge')).toContainText('Preferences provided');
    await expect(page.locator('#wp-pregnant-no')).toBeChecked();
});

test('customer can still complete the wellness questionnaire', async ({ page }) => {
    await login(page, 'complete');
    await page.getByRole('button', { name: 'No preference', exact: true }).click();
    await page.getByRole('button', { name: 'Next', exact: true }).click();
    await page.getByRole('button', { name: 'Medium pressure', exact: true }).click();
    await page.getByRole('button', { name: 'Complete', exact: true }).click();
    await expect(page.locator('#tnr-registration-onboarding-modal')).toHaveCount(0);
    await page.goto('/profile');
    await expect(page.locator('#wp-pressure-medium')).toBeChecked();
    await expect(page.locator('.profile-wellness-badge')).toContainText('Preferences provided');
});
