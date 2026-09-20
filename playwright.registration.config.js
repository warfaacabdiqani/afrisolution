import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/Browser', testMatch: ['registration-layout.spec.js'], workers: 1,
    use: { baseURL: 'https://registration.test' },
});
