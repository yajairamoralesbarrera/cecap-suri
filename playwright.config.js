import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './tests/e2e',
    timeout: 45_000,
    retries: 0,
    reporter: 'list',
    use: {
        baseURL: process.env.E2E_BASE_URL || 'http://127.0.0.1:8000',
        trace: 'off',
    },
    projects: [
        {
            name: 'desktop',
            use: {
                ...devices['Desktop Chrome'],
                viewport: { width: 1440, height: 900 },
            },
        },
        {
            name: 'tablet',
            use: {
                ...devices['Desktop Chrome'],
                viewport: { width: 820, height: 1180 },
            },
        },
        {
            name: 'mobile',
            use: {
                ...devices['Desktop Chrome'],
                viewport: { width: 390, height: 844 },
                isMobile: true,
                hasTouch: true,
            },
        },
    ],
});
