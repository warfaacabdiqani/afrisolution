import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/Browser', testMatch: ['reports-layout.spec.js'], workers: 1,
    use: { baseURL: 'https://billing.test', trace: 'retain-on-failure' },
});
