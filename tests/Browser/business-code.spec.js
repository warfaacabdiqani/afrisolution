import { test, expect } from '@playwright/test';
import { spawnSync } from 'node:child_process';
import { env, php } from '../../playwright.config.js';

test.beforeAll(() => {
    const result = spawnSync(php, ['tests/Browser/business-code-fixtures.php'], { env, encoding: 'utf8' });
    if (result.status !== 0) throw new Error(result.stderr || result.stdout);
});

test('settings and readonly business codes use server previews and discard stale responses', async ({ page }) => {
    test.setTimeout(120000);
    page.setDefaultTimeout(15000);
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    page.on('console', m => { if (m.type() === 'warning' && m.text().includes('[Vue warn]')) errors.push(m.text()); });
    await page.goto('/app/login');
    await page.getByLabel('Email address', { exact: true }).fill('browser-admin@example.test');
    await page.getByLabel('Password', { exact: true }).fill('BrowserTestPass123');
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).not.toHaveURL(/app\/login$/);
    await page.goto('/app/admin/settings');
    await page.getByLabel('Business Code Prefix').fill('Afri9');
    await page.getByLabel('Starting Sequence').fill('400001');
    await expect(page.getByText('AFRI9-SAL-400001', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Save Changes', exact: true }).click();
    await expect(page.getByLabel('Business Code Prefix')).toHaveValue('AFRI9');
    await page.reload();
    await expect(page.getByLabel('Starting Sequence')).toHaveValue('400001');
    await page.goto('/app/admin/businesses/create');
    const headers = { Origin: 'http://127.0.0.1:8011', Accept: 'application/json' };
    const types = (await (await page.request.get('/api/v1/platform/business-types', { headers })).json()).data;
    const id = slug => String(types.find(t => t.slug === slug).id);
    for (const [slug, label, abbreviation] of [
        ['clinic', 'Clinic Code', 'CLN'], ['dental', 'Dental Clinic Code', 'DEN'],
        ['beauty-salon', 'Salon Code', 'SAL'], ['stadium', 'Stadium Code', 'STD'],
    ]) {
        await page.getByRole('combobox', { name: 'Business Type', exact: true }).selectOption(id(slug));
        const field = page.getByLabel(label, { exact: false });
        await expect(field).toHaveValue(`AFRI9-${abbreviation}-400001`);
        await expect(field).toHaveAttribute('readonly', '');
    }
    // Deliberately deliver a salon response after the newer stadium response.
    let release;
    const gate = new Promise(resolve => { release = resolve; });
    await page.route('**/platform/businesses/next-code?*', async route => {
        if (new URL(route.request().url()).searchParams.get('business_type_id') === id('beauty-salon')) {
            const response = await route.fetch();
            await gate;
            await route.fulfill({ response });
        } else await route.continue();
    });
    const salonRequest = page.waitForRequest(r => r.url().includes('businesses/next-code?business_type_id=' + id('beauty-salon')));
    await page.getByRole('combobox', { name: 'Business Type', exact: true }).selectOption(id('beauty-salon'));
    await salonRequest;
    await page.getByRole('combobox', { name: 'Business Type', exact: true }).selectOption(id('stadium'));
    await expect(page.getByLabel('Stadium Code')).toHaveValue('AFRI9-STD-400001');
    const delayedResponse = page.waitForResponse(r => r.url().includes('businesses/next-code?business_type_id=' + id('beauty-salon')));
    release();
    await delayedResponse;
    await expect(page.getByLabel('Stadium Code')).toHaveValue('AFRI9-STD-400001');
    await page.unroute('**/platform/businesses/next-code?*');
    await page.getByRole('combobox', { name: 'Business Type', exact: true }).selectOption(id('beauty-salon'));
    await expect(page.getByLabel('Salon Code')).toHaveValue('AFRI9-SAL-400001');
    await page.getByLabel('Salon Name', { exact: true }).fill('Auto Code Salon');
    await page.getByLabel('Owner name', { exact: true }).fill('Salon Owner');
    await page.getByLabel('Owner email', { exact: true }).fill('auto-code-owner@example.test');
    await page.getByLabel('Password', { exact: false }).first().fill('OwnerTestPass123');
    await page.getByLabel('Confirm password', { exact: true }).fill('OwnerTestPass123');
    const plans = (await (await page.request.get('/api/v1/platform/plans', { headers })).json()).data;
    await page.getByRole('combobox', { name: 'Plan', exact: true }).selectOption(String(plans.find(p => p.name === 'Business code plan').id));
    const creation = page.waitForResponse(r => r.url().endsWith('/platform/tenants') && r.request().method() === 'POST');
    await page.getByRole('button', { name: 'Create Salon', exact: true }).click();
    const response = await creation;
    expect(response.status()).toBe(201);
    expect((await response.json()).data.slug).toBe('AFRI9-SAL-400001');
    expect(response.request().postDataJSON()).not.toHaveProperty('slug');
    await expect(page).toHaveURL(/admin\/clinics\/\d+\?created=1$/);
    await page.goto('/app/admin/clinics/create');
    await expect(page.getByLabel('Clinic Code')).toHaveValue('AFRI9-CLN-400002');
    expect(errors).toEqual([]);
});
