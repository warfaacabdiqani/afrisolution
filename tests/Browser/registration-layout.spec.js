import { test, expect } from '@playwright/test';
import { readFile } from 'node:fs/promises';
import path from 'node:path';

async function fixture(page, failRegistration = false) {
    const manifest = JSON.parse(await readFile('public/build/manifest.json', 'utf8'));
    const entry = manifest['resources/js/app.js'];
    const stylesheet = manifest['resources/css/app.css'].file;
    let registered = false;
    await page.route('http://127.0.0.1:8011/**', async route => {
        const url = new URL(route.request().url());
        if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/images/')) {
            const file = path.resolve('public', '.' + url.pathname);
            if (!file.startsWith(path.resolve('public') + path.sep)) return route.abort();
            return route.fulfill({ body: await readFile(file), contentType: file.endsWith('.css') ? 'text/css' : file.endsWith('.png') ? 'image/png' : 'text/javascript' });
        }
        if (url.pathname === '/api/v1/public/settings') return route.fulfill({ json: { data: { 'branding.display_name': 'AFRI SOLUTION', 'branding.footer_text': 'Business Management' } } });
        if (url.pathname === '/api/v1/public/registration') return route.fulfill({ json: { data: {
            enabled: true, business_types: [{ id: 1, name: 'Healthcare / Clinic' }, { id: 2, name: 'Dental Clinic' }, { id: 3, name: 'Beauty Salon' }, { id: 4, name: 'Stadium / Sports Facility' }],
            plans: [{ id: 1, name: 'Starter', currency: 'USD', price: '10.00', billing_period: 'monthly', trial_days: 14 }, { id: 2, name: 'Pro', currency: 'USD', price: '25.00', billing_period: 'monthly', trial_days: 14 }],
        } } });
        if (url.pathname === '/api/v1/session') return route.fulfill({ status: registered ? 200 : 401, json: registered ? { data: { id: 1, email: 'owner@example.test', email_verified: false, active_tenant_id: 1 } } : {} });
        if (url.pathname === '/sanctum/csrf-cookie') return route.fulfill({ status: 204 });
        if (url.pathname === '/register') {
            if (failRegistration) return route.fulfill({ status: 422, json: { errors: { name: ['Please enter a different business name.'] } } });
            registered = true;
            return route.fulfill({ status: 201, json: { data: { business_name: 'Afriso', business_type: 'Beauty Salon', plan: 'Pro', trial_ends_at: '2026-10-04T00:00:00Z', tenant_id: 1, email: 'owner@example.test', verification_email_sent: true } } });
        }
        if (url.pathname.startsWith('/app/')) return route.fulfill({ contentType: 'text/html; charset=utf-8', body:
            `<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="/build/${stylesheet}">${(entry.css || []).map(css => `<link rel="stylesheet" href="/build/${css}">`).join('')}</head><body><div id="app"></div><script type="module" src="/build/${entry.file}"></script></body></html>` });
        return route.fulfill({ status: 404, body: 'Unexpected request' });
    });
}

test('registration keeps validation errors on the details step and sign-in remains available', async ({ page }) => {
    await fixture(page, true);
    await page.goto('/app/register');
    await page.getByPlaceholder('Enter your full name').fill('New Owner');
    await page.getByPlaceholder('you@yourcompany.com').fill('owner@example.test');
    await page.getByPlaceholder('Create a strong password').fill('SecurePass12345');
    await page.getByPlaceholder('Confirm your password').fill('SecurePass12345');
    await page.getByRole('button', { name: 'Continue' }).click();
    await page.getByText('Beauty Salon').click();
    await page.getByRole('button', { name: 'Continue' }).click();
    await page.getByText('Pro', { exact: true }).click();
    await page.getByRole('button', { name: 'Continue' }).click();
    await page.getByPlaceholder('Enter your business name').fill('Afriso');
    await page.getByRole('button', { name: 'Start Free Trial' }).click();
    await expect(page.getByRole('alert')).toContainText('Please enter a different business name.');
    await expect(page.getByRole('heading', { name: 'Business Information' })).toBeVisible();
    await page.locator('.registration-top a').click();
    await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();
});

for (const [width, height] of [[1920, 900], [1600, 900], [1536, 730], [1366, 768]]) {
    test(`login fits the desktop viewport at ${width}×${height}`, async ({ page }) => {
        await page.setViewportSize({ width, height });
        await fixture(page);
        await page.goto('/app/login');
        await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();
        const layout = await page.evaluate(() => ({ overflow: document.documentElement.scrollHeight - innerHeight,
            page: document.querySelector('.login-page').getBoundingClientRect().height,
            story: document.querySelector('.login-story').getBoundingClientRect().height,
            form: document.querySelector('.login-form-side').getBoundingClientRect().height }));
        expect(layout.overflow, JSON.stringify(layout)).toBeLessThanOrEqual(2);
    });
}

for (const [width, height] of [[1920, 1080], [1920, 900], [1920, 820], [1920, 720], [1600, 900], [1600, 800], [1536, 730], [1440, 700], [1366, 768], [1366, 650], [1280, 720], [1280, 640], [1280, 600], [1024, 768], [1024, 650], [820, 900], [390, 844]]) {
    test(`registration steps remain usable at ${width}×${height}`, async ({ page }) => {
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        page.on('console', message => { if (message.type() === 'error' && !message.text().includes('401')) errors.push(message.text()); });
        const capture = async name => {
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
            if ([1920, 1366, 390].includes(width)) await page.screenshot({ path: test.info().outputPath(name + '.png'), fullPage: true });
        };
        await page.setViewportSize({ width, height });
        await fixture(page);
        await page.goto('/app/register');
        await expect(page.getByRole('heading', { name: 'Create your account' })).toBeVisible();
        await capture('account');
        await expect(page.locator('.registration-story .login-brand')).toContainText('AFRI SOLUTION');
        await expect(page.locator('.registration-photo')).toHaveCount(0);
        await expect(page.locator('.registration-steps [aria-current="step"]')).toContainText('Account');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        await expect(page.locator('.registration-top a')).toHaveAttribute('href', '/app/login');
        await page.getByPlaceholder('Enter your full name').fill('New Owner');
        await page.getByPlaceholder('you@yourcompany.com').fill('owner@example.test');
        await page.getByPlaceholder('Create a strong password').fill('SecurePass12345');
        await page.getByPlaceholder('Confirm your password').fill('SecurePass12345');
        const assertDesktopFits = async () => {
            if (width >= 1024) {
                const layout = await page.evaluate(() => ({ overflow: document.documentElement.scrollHeight - innerHeight,
                    story: document.querySelector('.registration-story').getBoundingClientRect().height,
                    main: document.querySelector('.registration-main').getBoundingClientRect().height,
                    card: document.querySelector('.registration-card').getBoundingClientRect().height,
                    top: document.querySelector('.registration-top').getBoundingClientRect().height,
                    message: document.querySelector('.registration-message').getBoundingClientRect().height,
                    benefits: document.querySelector('.registration-benefits').getBoundingClientRect().height }));
                expect(layout.overflow, JSON.stringify(layout)).toBeLessThanOrEqual(2);
            }
        };
        await assertDesktopFits();
        await page.getByRole('button', { name: 'Continue' }).click();
        await expect(page.getByRole('heading', { name: 'Select Business Type' })).toBeVisible();
        await capture('business-type');
        await assertDesktopFits();
        await page.getByText('Beauty Salon').click();
        await page.getByRole('button', { name: 'Continue' }).click();
        await expect(page.getByRole('heading', { name: 'Select Subscription Plan' })).toBeVisible();
        await capture('plan');
        await assertDesktopFits();
        await page.getByText('Pro', { exact: true }).click();
        await page.getByRole('button', { name: 'Continue' }).click();
        await expect(page.getByRole('heading', { name: 'Business Information' })).toBeVisible();
        await capture('details');
        await assertDesktopFits();
        await page.getByRole('button', { name: 'Back' }).click();
        await expect(page.getByRole('heading', { name: 'Select Subscription Plan' })).toBeVisible();
        await page.getByRole('button', { name: 'Continue' }).click();
        await page.getByPlaceholder('Enter your business name').fill('Afriso');
        await page.getByRole('button', { name: 'Start Free Trial' }).click();
        await expect(page.getByRole('heading', { name: 'Your free trial has started' })).toBeVisible();
        await capture('success');
        await expect(page.locator('.registration-success')).toContainText('Beauty Salon');
        await assertDesktopFits();
        await expect(page.getByText('Email verification required')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Verify Email' })).toBeVisible();
        await page.getByRole('button', { name: 'Verify Email' }).click();
        await expect(page).toHaveURL(/\/app\/verify-email$/);
        await expect(page.getByRole('heading', { name: 'Verify your email' })).toBeVisible();
        expect(errors).toEqual([]);
    });
}
