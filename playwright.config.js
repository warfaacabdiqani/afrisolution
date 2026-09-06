import { defineConfig } from '@playwright/test';
import path from 'node:path';
const php = process.env.PHP_EXECUTABLE || 'C:/php84/php.exe';
const env = {
    ...process.env, APP_ENV: 'local', APP_URL: 'http://127.0.0.1:8011',
    DB_CONNECTION: 'sqlite', DB_DATABASE: path.resolve('storage/framework/testing/saas-browser.sqlite'),
    SESSION_DRIVER: 'file', CACHE_STORE: 'array', MAIL_MAILER: 'array',
    SANCTUM_STATEFUL_DOMAINS: '127.0.0.1:8011', SESSION_SECURE_COOKIE: 'false',
    VITE_HOT_FILE: path.resolve('storage/framework/testing/browser-no-vite.hot'),
};
export { env, php };
export default defineConfig({
    testDir: './tests/Browser', workers: 1, timeout: 60000,
    globalSetup: './tests/Browser/setup.js',
    use: { baseURL: 'http://127.0.0.1:8011', trace: 'retain-on-failure' },
    webServer: {
        command: '"' + php + '" artisan serve --host=127.0.0.1 --port=8011',
        url: 'http://127.0.0.1:8011/up', env, reuseExistingServer: false,
    },
});
