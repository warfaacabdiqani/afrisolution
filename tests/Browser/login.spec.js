import { test, expect } from '@playwright/test';
import { spawnSync } from 'node:child_process';
import { env, php } from '../../playwright.config.js';

test.beforeAll(() => {
    const result = spawnSync(php, ['tests/Browser/login-fixtures.php'], { env, encoding: 'utf8' });
    if (result.status !== 0) throw new Error(result.stderr || result.stdout);
});

for (const [width, height] of [[1920, 900], [1536, 730], [1366, 768], [1280, 600], [1024, 768], [768, 1024], [390, 844], [320, 740]]) {
    test(`multi-business login layout at ${width}x${height}`, async ({ page }) => {
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        page.on('console', message => { if (message.type() === 'error' && !message.text().includes('401')) errors.push(message.text()); });
        await page.setViewportSize({ width, height });
        await page.goto('/app/login');
        await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();
        await expect(page.locator('.login-brand')).toContainText('AFRI SOLUTION');
        await expect(page.locator('.login-subtitle')).toHaveText('Sign in to your AFRI SOLUTION account.');
        await expect(page.locator('.login-page')).not.toContainText(/healthcare|patients|doctors|healthier|your clinic/i);
        await expect(page.getByLabel('Email address', { exact: true })).toHaveValue('');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        if (width >= 1024) expect(await page.evaluate(() => document.documentElement.scrollHeight - innerHeight)).toBeLessThanOrEqual(2);
        if (width >= 1200) {
            const visual = page.getByAltText('AFRISO dashboard in a demo workspace');
            await expect(visual).toBeVisible();
            expect(await visual.evaluate(img => img.complete && img.naturalWidth > 0)).toBe(true);
        }
        await page.screenshot({ path: test.info().outputPath(`login-${width}.png`), fullPage: true });
        await page.getByRole('button', { name: 'Forgot password?' }).click();
        await expect(page.locator('#recovery-help')).toContainText('administrator');
        await page.getByRole('link', { name: 'Create an account', exact: true }).click();
        await expect(page).toHaveURL(/\/app\/register$/);
        await page.goto('/app/login');
        await page.getByRole('link', { name: 'Back to home' }).click();
        await expect(page).toHaveURL(/\/$/);
        expect(errors).toEqual([]);
    });
}

test('password visibility, failed credentials, Enter submission and successful redirect remain working', async ({ page }) => {
    await page.goto('/app/login');
    const password = page.getByLabel('Password', { exact: true });
    await page.getByLabel('Email address', { exact: true }).fill('login-demo@example.test');
    await password.fill('IncorrectPassword123');
    await page.getByRole('button', { name: 'Show password', exact: true }).click();
    await expect(password).toHaveAttribute('type', 'text');
    await page.getByRole('button', { name: 'Hide password', exact: true }).click();
    await expect(password).toHaveAttribute('type', 'password');
    await password.press('Enter');
    await expect(page.getByRole('alert')).toBeVisible();
    await expect(password).toHaveValue('');
    await password.fill('BrowserTestPass123');
    await password.press('Enter');
    await expect(page).toHaveURL(/\/app\/dashboard$/);
    await expect(page.locator('[data-widget]')).toHaveCount(4);
});

test('unverified accounts still redirect to email verification', async ({ page }) => {
    await page.goto('/app/login');
    await page.getByLabel('Email address', { exact: true }).fill('login-unverified@example.test');
    await page.getByLabel('Password', { exact: true }).fill('BrowserTestPass123');
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL(/\/app\/verify-email$/);
    await page.goto('/app/dashboard');
    await expect(page).toHaveURL(/\/app\/verify-email$/);
});

test('capture actual dashboard for the login device asset', async ({ page }) => {
    test.skip(!process.env.CAPTURE_LOGIN_DASHBOARD, 'One-time capture of the real application using demo data.');
    await page.setViewportSize({ width: 1440, height: 1200 });
    await page.goto('/app/login');
    await page.getByLabel('Email address', { exact: true }).fill('login-demo@example.test');
    await page.getByLabel('Password', { exact: true }).fill('BrowserTestPass123');
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL(/\/app\/dashboard$/);
    await expect(page.locator('[data-widget]')).toHaveCount(4);
    await expect(page.locator('.clinic-skeleton-grid')).toHaveCount(0);
    await page.screenshot({ path: test.info().outputPath('afriso-dashboard-demo.png'), fullPage: true });
});
