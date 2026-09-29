import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/Browser', testMatch: 'dental.spec.js', workers: 1,
    use: { baseURL: 'https://dental.test', trace: 'retain-on-failure' },
});
