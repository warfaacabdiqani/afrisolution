import { test, expect } from '@playwright/test';
import { spawnSync } from 'node:child_process';
import { env, php } from '../../playwright.config.js';

test.beforeAll(() => {
    const result = spawnSync(php, ['tests/Browser/business-fixtures.php'], { env, encoding: 'utf8' });
    if (result.status !== 0) throw new Error(result.stderr || result.stdout);
});

test('business navigation switches without logout and route denials explain the right layer', async ({ page }) => {
    test.setTimeout(120000);
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    page.on('console', message => { if (message.type() === 'warning' && message.text().includes('[Vue warn]')) errors.push(message.text()); });
    await page.goto('/app/login');
    await page.getByLabel('Email', { exact: true }).fill('phase-clinic@example.test');
    await page.getByLabel('Password', { exact: true }).fill('BrowserTestPass123');
    await page.getByRole('button', { name: 'Sign in' }).click();
    const nav = page.getByRole('navigation', { name: 'Business navigation' });
    for (const [slug, settings, subtitle, healthcare] of [
        ['clinic', 'Clinic Settings', 'Healthcare', true],
        ['beauty-salon', 'Salon Settings', 'Beauty & Wellness', false],
        ['stadium', 'Stadium Settings', 'Sports & Facilities', false],
        ['clinic', 'Clinic Settings', 'Healthcare', true],
        ['dental', 'Clinic Settings', 'Dental Healthcare', true],
    ]) {
        await page.goto('/app/clinics?switch=1');
        await page.getByRole('button').filter({ hasText: 'Phase ' + slug }).click();
        await expect(page).toHaveURL(/app\/dashboard$/);
        await expect(nav.getByRole('link', { name: settings, exact: true })).toBeVisible();
        await expect(page.locator('.clinic-brand small')).toHaveText(subtitle);
        for (const name of ['Patients', 'Doctors / Clinicians', 'Consultations', 'Prescriptions']) {
            await expect(nav.getByRole('link', { name, exact: true })).toHaveCount(healthcare ? 1 : 0);
        }
        await expect(nav.getByRole('link', { name: 'Pharmacy', exact: true })).toHaveCount(slug === 'clinic' ? 1 : 0);
        await nav.getByRole('link', { name: settings, exact: true }).click();
        await expect(page.getByRole('heading', { name: settings, exact: true })).toBeVisible();
        if (!healthcare) {
            await page.goto('/app/prescriptions');
            await expect(page.getByRole('heading', { name: 'Module Not Available' })).toBeVisible();
            await expect(page.getByText(/Prescriptions are not available for/)).toBeVisible();
            const response = await page.request.get('/api/v1/clinic/prescriptions');
            expect(response.status()).toBe(403);
            expect((await response.json()).code).toBe('BUSINESS_MODULE_UNAVAILABLE');
            await page.goto('/app/reports');
            await expect(page.getByRole('heading', { name: 'Module coming soon' })).toBeVisible();
        }
    }
    expect(errors).toEqual([]);
});

test('platform business profile details are read-only and visible', async ({ page }) => {
    await page.goto('/app/login');
    await page.getByLabel('Email', { exact: true }).fill('browser-admin@example.test');
    await page.getByLabel('Password', { exact: true }).fill('BrowserTestPass123');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await page.goto('/app/admin/business-types');
    await page.getByRole('link', { name: 'Beauty Salon', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Terminology' })).toBeVisible();
    await expect(page.getByText('Stylist', { exact: true })).toBeVisible();
    await expect(page.getByText('Navigation Profile', { exact: true })).toBeVisible();
    const tenants = await (await page.request.get('/api/v1/platform/tenants?search=Phase')).json();
    const tenant = tenants.data.find(t => t.name === 'Phase beauty-salon');
    await page.goto('/app/admin/clinics/' + tenant.id);
    await expect(page.getByRole('heading', { name: 'Enabled Business Modules' })).toBeVisible();
    await expect(page.getByText('Beauty Salon', { exact: true })).toBeVisible();
});
