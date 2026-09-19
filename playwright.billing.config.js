import { defineConfig } from '@playwright/test';

// UI contracts use built assets and intercepted HTTP responses; no database reset or server needed.
export default defineConfig({
    testDir: './tests/Browser', testMatch: ['shared-billing.spec.js', 'clinic-billing.spec.js', 'salon-billing.spec.js'], workers: 1,
    use: { baseURL: 'https://billing.test', trace: 'retain-on-failure' },
});
