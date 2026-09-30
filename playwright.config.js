import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/Browser',
    testMatch: '**/*.spec.js',
    fullyParallel: false,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 1 : 0,
    workers: 1,
    timeout: 60_000,
    expect: { timeout: 10_000 },
    reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
    outputDir: 'test-results',
    use: {
        baseURL: 'http://127.0.0.1:8010',
        channel: 'chrome',
        actionTimeout: 10_000,
        navigationTimeout: 20_000,
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
        video: 'off',
    },
    webServer: {
        command: 'php artisan serve --env=e2e --host=127.0.0.1 --port=8010',
        url: 'http://127.0.0.1:8010/login',
        reuseExistingServer: false,
        timeout: 120_000,
    },
});
