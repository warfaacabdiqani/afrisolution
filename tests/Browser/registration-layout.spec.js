import { test, expect } from '@playwright/test';
import { readFile } from 'node:fs/promises';
import path from 'node:path';

async function fixture(page, failRegistration = false) {
    const manifest = JSON.parse(await readFile('public/build/manifest.json', 'utf8'));
    const entry = manifest['resources/js/app.js'];
    const stylesheet = manifest['resources/css/app.css'].file;
    let registered = false;
    await page.route('https://registration.test/**', async route => {
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
        if (url.pathname === '/api/v1/session') return route.fulfill({ status: registered ? 200 : 401, json: registered ? { data: { id: 1, active_tenant_id: 1 } } : {} });
        if (url.pathname === '/sanctum/csrf-cookie') return route.fulfill({ status: 204 });
        if (url.pathname === '/register') {
            if (failRegistration) return route.fulfill({ status: 422, json: { errors: { name: ['Please enter a different business name.'] } } });
            registered = true;
            return route.fulfill({ status: 201, json: { data: { business_name: 'Afriso', plan: 'Pro', trial_ends_at: '2026-10-04T00:00:00Z', tenant_id: 1 } } });
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

for (const [width, height] of [[1920, 1080], [1600, 900], [1366, 768], [820, 900], [390, 844]]) {
    test(`registration steps remain usable at ${width}×${height}`, async ({ page }) => {
        await page.setViewportSize({ width, height });
        await fixture(page);
        await page.goto('/app/register');
        await expect(page.getByRole('heading', { name: 'Create your account' })).toBeVisible();
        await expect(page.locator('.registration-top a')).toHaveAttribute('href', '/app/login');
        await page.getByPlaceholder('Enter your full name').fill('New Owner');
        await page.getByPlaceholder('you@yourcompany.com').fill('owner@example.test');
        await page.getByPlaceholder('Create a strong password').fill('SecurePass12345');
        await page.getByPlaceholder('Confirm your password').fill('SecurePass12345');
        const assertDesktopFits = async () => {
            if (width >= 1366) expect(await page.evaluate(() => document.documentElement.scrollHeight - innerHeight)).toBeLessThanOrEqual(2);
        };
        await assertDesktopFits();
        await page.getByRole('button', { name: 'Continue' }).click();
        await expect(page.getByRole('heading', { name: 'Select Business Type' })).toBeVisible();
        await assertDesktopFits();
        await page.getByText('Beauty Salon').click();
        await page.getByRole('button', { name: 'Continue' }).click();
        await expect(page.getByRole('heading', { name: 'Select Subscription Plan' })).toBeVisible();
        await assertDesktopFits();
        await page.getByText('Pro', { exact: true }).click();
        await page.getByRole('button', { name: 'Continue' }).click();
        await expect(page.getByRole('heading', { name: 'Business Information' })).toBeVisible();
        await assertDesktopFits();
        await page.getByRole('button', { name: 'Back' }).click();
        await expect(page.getByRole('heading', { name: 'Select Subscription Plan' })).toBeVisible();
        await page.getByRole('button', { name: 'Continue' }).click();
        await page.getByPlaceholder('Enter your business name').fill('Afriso');
        await page.getByRole('button', { name: 'Start Free Trial' }).click();
        await expect(page.getByRole('heading', { name: 'Your free trial has started' })).toBeVisible();
        await assertDesktopFits();
        await expect(page.getByRole('button', { name: 'Go to Dashboard' })).toBeVisible();
    });
}
