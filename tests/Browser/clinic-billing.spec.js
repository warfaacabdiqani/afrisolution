import { test, expect } from '@playwright/test';
import { readFile } from 'node:fs/promises';
import path from 'node:path';

async function fixture(page, status = 'completed') {
    const errors = []; page.on('pageerror', error => errors.push(error.message));
    const manifest = JSON.parse(await readFile('public/build/manifest.json', 'utf8'));
    const entry = manifest['resources/js/app.js'];
    const stylesheet = manifest['resources/css/app.css'].file;
    const modules = [['dashboard', 'dashboard'], ['billing', 'billing'], ['appointments', 'appointments'], ['patients', 'patient_management']].map(([key, feature]) => ({
        key, label: key, icon: 'calendar', allowed: true, business_allowed: true, business_modules: [key], feature, permission: key + '.view',
    }));
    const context = { clinic: { id: 1, name: 'Clinic A', timezone: 'UTC' }, branch: { id: 1, name: 'Main' }, branches: [{ id: 1, name: 'Main' }],
        business_type: { slug: 'clinic', name: 'Clinic' }, business_modules: { clinical: true, dashboard: true, billing: true, appointments: true, patients: true },
        labels: { customer: 'Patient' }, features: { dashboard: true, billing: true, appointments: true, patient_management: true }, permissions: ['*'],
        modules, operational: true, subscription: { status: 'active' }, role: 'owner', today: '2026-09-16', idle_timeout_minutes: 120 };
    const patient = { id: 17, first_name: 'Amina', last_name: 'Yusuf', full_name: 'Amina Yusuf', patient_number: 'PAT-17', gender: 'female',
        age: 30, status: 'active', allergies: [], conditions: [] };
    const appointment = { id: 7, appointment_number: 'APT-7', patient, patient_id: 17, doctor_id: 3, branch_id: 1,
        doctor: { id: 3, full_name: 'Ahmed Hassan' }, branch: 'Main', status, source: 'clinic', starts_at: '2026-09-16 09:00:00',
        ends_at: '2026-09-16 09:30:00', created_at: '2026-09-16T08:00:00Z', created_by: 'Owner', invoice_id: null };
    const invoice = { id: 99, number: 'INV-000099', tenant_id: 1, branch: { id: 1, name: 'Main' }, customer: { type: 'patient', id: 17, name: 'Amina Yusuf' },
        currency: 'USD', status: 'unpaid', subtotal: '20.00', discount: '0.00', tax: '0.00', total: '20.00', paid: '0.00', balance: '20.00',
        issued_at: '2026-09-16T10:00:00Z', source: { type: 'clinic_appointment', id: 7 }, snapshot_version: 2,
        items: [{ id: 1, description: 'Consultation - Dr. Ahmed Hassan', quantity: 1, unit_price: '20.00', amount: '20.00', discount_amount: '0.00', tax_amount: '0.00', line_total: '20.00' }], payments: [] };
    const state = { creations: 0, filters: [], detailIds: [], errors };
    await page.route('https://billing.test/**', async route => {
        const url = new URL(route.request().url()); const json = data => route.fulfill({ json: data });
        if (url.pathname.startsWith('/build/')) {
            const file = path.resolve('public', '.' + url.pathname);
            if (!file.startsWith(path.resolve('public/build') + path.sep)) return route.abort();
            return route.fulfill({ body: await readFile(file), contentType: file.endsWith('.css') ? 'text/css' : 'text/javascript' });
        }
        if (url.pathname === '/api/v1/session') return json({ data: { id: 1, name: 'Owner', active_tenant_id: 1, email_verified: true, is_platform_admin: false } });
        if (url.pathname === '/api/v1/clinic/context') return json({ data: context });
        if (url.pathname === '/api/v1/clinic/dashboard') return json({ data: {
            business: { id: 1, name: 'Clinic A', workspace_label: 'clinic' }, today: '2026-09-16',
            widgets: [{ key: 'monthly_revenue', label: 'Monthly Revenue', icon: 'revenue', tone: 'mint',
                format: 'currency', currency: 'USD', available: true, value: 5,
                description: 'Payments received this month in this branch' }], sections: {}, quick_actions: [],
        } });
        if (url.pathname === '/api/v1/public/settings') return json({ data: {} });
        if (url.pathname === '/api/v1/clinic/patients/17') return json({ data: patient });
        if (url.pathname === '/api/v1/clinic/appointments/7') return json({ data: appointment });
        if (url.pathname === '/api/v1/clinic/appointments/7/activity') return json({ data: { data: [], last_page: 1 } });
        if (url.pathname === '/api/v1/clinic/appointments/7/complete') { appointment.status = 'completed'; return json({ data: appointment }); }
        if (url.pathname === '/api/v1/clinic/appointments/7/invoice') {
            state.creations++; appointment.invoice_id = invoice.id;
            expect(route.request().postDataJSON()).toEqual({});
            return route.fulfill({ status: 201, json: { data: invoice } });
        }
        if (url.pathname === '/api/v1/clinic/appointments/options') return json({ data: { doctors: [appointment.doctor], types: [], statuses: {},
            actions: { complete: { from: ['in_consultation'], permission: 'appointments.complete' } } } });
        if (url.pathname === '/api/v1/clinic/appointments/hours') return json({ data: { schedules: [], leaves: [] } });
        if (['/api/v1/clinic/appointments', '/api/v1/clinic/appointments/today', '/api/v1/clinic/appointments/calendar'].includes(url.pathname)) {
            return json({ data: [appointment], meta: { current_page: 1, last_page: 1, total: 1 }, summary: { completed: 1 }, counts: [] });
        }
        if (url.pathname === '/api/v1/billing/invoices') {
            state.filters.push(Object.fromEntries(url.searchParams));
            return json({ data: { data: appointment.invoice_id ? [invoice] : [], current_page: 1, last_page: 1 }, methods: ['cash'] });
        }
        if (url.pathname.startsWith('/api/v1/billing/invoices/')) {
            state.detailIds.push(url.pathname.split('/').at(-1));
            return json({ data: invoice, methods: ['cash'] });
        }
        if (url.pathname.startsWith('/app/')) return route.fulfill({ contentType: 'text/html; charset=utf-8', body: `<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="/build/${stylesheet}">${(entry.css || []).map(css => `<link rel="stylesheet" href="/build/${css}">`).join('')}</head><body><div id="app"></div><script type="module" src="/build/${entry.file}"></script></body></html>` });
        return route.fulfill({ status: 404, body: 'Unexpected request' });
    });
    return state;
}

test('completion exposes Create Invoice; issuance switches to View Invoice and patient history uses the shared API', async ({ page }) => {
    const state = await fixture(page, 'in_consultation');
    await page.goto('/app/appointments/7?view=schedule');
    const dialog = page.getByRole('dialog');
    await expect(dialog.getByRole('button', { name: 'Create Invoice', exact: true })).toHaveCount(0);
    await dialog.getByRole('button', { name: 'Complete Appointment', exact: true }).click();
    const confirmation = page.getByRole('dialog', { name: 'Complete Appointment?' });
    await expect(confirmation.getByRole('button', { name: 'Confirm Complete Appointment', exact: true })).toBeVisible();
    const bounds = await confirmation.boundingBox();
    const viewport = page.viewportSize();
    expect(bounds.y).toBeGreaterThanOrEqual(0);
    expect(bounds.y + bounds.height).toBeLessThanOrEqual(viewport.height);
    await confirmation.getByRole('button', { name: 'Keep Appointment' }).click();
    await expect(confirmation).toHaveCount(0);
    await dialog.getByRole('button', { name: 'Complete Appointment', exact: true }).click();
    await confirmation.getByRole('button', { name: 'Confirm Complete Appointment', exact: true }).click();
    await expect(dialog.getByRole('button', { name: 'Create Invoice', exact: true })).toBeVisible();
    expect(state.creations).toBe(0);
    await dialog.getByRole('button', { name: 'Create Invoice', exact: true }).click();
    await expect(page).toHaveURL(/billing\/invoices\/99$/);
    await expect(page.getByRole('region', { name: 'Invoice details' })).toContainText('Consultation - Dr. Ahmed Hassan');
    await page.goto('/app/appointments/7?view=schedule');
    await expect(dialog.getByRole('link', { name: 'View Invoice', exact: true })).toBeVisible();
    await expect(dialog.getByRole('button', { name: 'Create Invoice', exact: true })).toHaveCount(0);
    await page.goto('/app/patients/17/billing');
    await expect(page.getByRole('columnheader', { name: 'Patient', exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'INV-000099', exact: true })).toBeVisible();
    expect(state.filters.at(-1)).toMatchObject({ customer_type: 'patient', customer_id: '17' });
    expect(state.detailIds).not.toContain('17');
    expect(state.creations).toBe(1); expect(state.errors).toEqual([]);
});

test('dashboard revenue links to the shared Billing ledger', async ({ page }) => {
    const state = await fixture(page);
    await page.goto('/app/dashboard');
    const revenue = page.locator('[data-widget=monthly_revenue]');
    await expect(revenue).toContainText('Monthly Revenue');
    await expect(revenue).toContainText('5.00');
    await revenue.getByRole('link', { name: 'View Billing' }).click();
    await expect(page).toHaveURL(/\/app\/billing\/invoices$/);
    await expect(page.getByRole('columnheader', { name: 'Invoice', exact: true })).toBeVisible();
    expect(state.errors).toEqual([]);
});

test('sidebar keeps clinic identity fixed while navigation and business controls scroll together', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 500 });
    await fixture(page);
    await page.goto('https://billing.test/app/billing');
    const brand = page.locator('.clinic-brand'), navigation = page.getByRole('navigation', { name: 'Business navigation' });
    const scrollArea = page.locator('.clinic-sidebar-scroll');
    const bottom = page.locator('.clinic-sidebar-bottom');
    await expect(brand).toBeVisible();
    await page.getByRole('button', { name: 'Collapse sidebar' }).click();
    await expect(page.locator('.clinic-sidebar')).toHaveCSS('width', '80px');
    await expect(navigation.getByRole('link', { name: 'billing', exact: true })).toBeVisible();
    await navigation.getByRole('link', { name: 'billing', exact: true }).hover();
    await expect(page.getByRole('tooltip')).toHaveText('billing');
    expect(await scrollArea.evaluate(area => area.scrollWidth <= area.clientWidth)).toBeTruthy();
    await page.getByRole('button', { name: 'Expand sidebar' }).click();
    await expect(page.locator('.clinic-sidebar')).toHaveCSS('width', '248px');
    await navigation.evaluate(nav => {
        const link = nav.querySelector('a');
        for (let index = 0; index < 20; index++) nav.appendChild(link.cloneNode(true));
    });
    const before = await brand.boundingBox();
    const metrics = await scrollArea.evaluate(area => ({ height: area.clientHeight, scrollHeight: area.scrollHeight,
        overflow: getComputedStyle(area).overflowY }));
    expect(metrics.scrollHeight, JSON.stringify(metrics)).toBeGreaterThan(metrics.height);
    await scrollArea.evaluate(area => { area.scrollTop = area.scrollHeight; });
    await expect.poll(() => scrollArea.evaluate(area => area.scrollTop)).toBeGreaterThan(0);
    const after = await brand.boundingBox();
    expect(after.y).toBe(before.y);
    expect(await page.locator('.clinic-sidebar').evaluate(sidebar => sidebar.scrollTop)).toBe(0);
    const bottomBounds = await bottom.boundingBox();
    expect(bottomBounds.y).toBeGreaterThanOrEqual(before.y + before.height);
    expect(bottomBounds.y + bottomBounds.height).toBeLessThanOrEqual(500);
    await expect(bottom.getByRole('link', { name: /Switch Business/ })).toBeVisible();
});

for (const status of ['scheduled', 'cancelled', 'no_show']) {
    test(`${status} appointment does not offer invoice creation`, async ({ page }) => {
        const state = await fixture(page, status);
        await page.goto('/app/appointments/7?view=schedule');
        await expect(page.getByRole('dialog')).toContainText('APT-7');
        await expect(page.getByRole('button', { name: 'Create Invoice', exact: true })).toHaveCount(0);
        expect(state.creations).toBe(0); expect(state.errors).toEqual([]);
    });
}

test('status confirmation stays visible on a short mobile viewport and Escape returns focus', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 600 });
    const state = await fixture(page, 'in_consultation');
    await page.goto('/app/appointments/7?view=schedule');
    const action = page.getByRole('button', { name: 'Complete Appointment', exact: true });
    await action.click();
    const confirmation = page.getByRole('dialog', { name: 'Complete Appointment?' });
    await expect(confirmation).toBeVisible();
    const bounds = await confirmation.boundingBox();
    expect(bounds.y).toBeGreaterThanOrEqual(0);
    expect(bounds.y + bounds.height).toBeLessThanOrEqual(600);
    await page.keyboard.press('Escape');
    await expect(confirmation).toHaveCount(0);
    await expect(action).toBeFocused();
    expect(state.errors).toEqual([]);
});
