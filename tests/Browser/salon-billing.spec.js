import { test, expect } from '@playwright/test';
import { readFile } from 'node:fs/promises';
import path from 'node:path';

async function fixture(page, { cashier = false, status = 'completed' } = {}) {
    const errors = [], healthcare = [], filters = [];
    page.on('pageerror', error => errors.push(error.message));
    const manifest = JSON.parse(await readFile('public/build/manifest.json', 'utf8'));
    const entry = manifest['resources/js/app.js'];
    const modules = [['billing', 'billing'], ['appointments', 'appointments'], ['clients', 'clients']].map(([key, feature]) => ({
        key, label: key, icon: 'calendar', allowed: !cashier || key === 'billing', business_allowed: true,
        business_modules: [key], feature, permission: key + '.view',
    }));
    const context = { clinic: { id: 1, name: 'Salon A', timezone: 'UTC' }, branch: { id: 1, name: 'Main' }, branches: [{ id: 1, name: 'Main' }],
        business_type: { slug: 'beauty-salon', name: 'Beauty Salon' }, business_modules: { clinical: false, billing: true, appointments: true, clients: true },
        labels: { customer: 'Client', bookings: 'Appointments' }, features: { billing: true, appointments: true, clients: true },
        permissions: cashier ? ['billing.view', 'billing.create', 'billing.payments'] : ['*'], modules, operational: true,
        subscription: { status: 'active' }, role: cashier ? 'cashier' : 'owner', today: '2026-09-16', idle_timeout_minutes: 120 };
    const client = { id: 17, first_name: 'Amina', last_name: 'Ali', full_name: 'Amina Ali', client_number: 'CLI-17', status: 'active', branch_id: 1 };
    const appointment = { id: 7, appointment_number: 'APT-7', client, stylist: { id: 3, name: 'Asha Stylist' },
        branch_id: 1, location: 'Main', starts_at: '2026-09-16 09:00:00', ends_at: '2026-09-16 10:30:00',
        status, currency: 'USD', total: '45.00', notes: '', deposit_required: '0.00', invoice_id: null,
        items: [{ id: 1, service_id: 11, name: 'Haircut', duration_minutes: 30, unit_price: '15.00' },
            { id: 2, service_id: 12, name: 'Color', duration_minutes: 60, unit_price: '30.00' }] };
    const invoice = { id: 99, number: 'INV-000099', tenant_id: 1, branch: { id: 1, name: 'Main' },
        customer: { type: 'salon_client', id: 17, name: 'Amina Ali' }, source: { type: 'salon_appointment', id: 7 },
        currency: 'USD', status: 'unpaid', subtotal: '45.00', discount: '0.00', tax: '0.00', total: '45.00', paid: '0.00', balance: '45.00',
        issued_at: '2026-09-16T10:00:00Z', snapshot_version: 2,
        items: [{ id: 1, description: 'Haircut', quantity: 1, unit_price: '15.00', amount: '15.00', discount_amount: '0.00', tax_amount: '0.00', line_total: '15.00' },
            { id: 2, description: 'Color', quantity: 1, unit_price: '30.00', amount: '30.00', discount_amount: '0.00', tax_amount: '0.00', line_total: '30.00' }], payments: [] };
    const state = { creations: 0, payments: 0, filters, errors, healthcare };
    await page.route('https://billing.test/**', async route => {
        const url = new URL(route.request().url()); const json = data => route.fulfill({ json: data });
        if (url.pathname.startsWith('/api/v1/clinic/appointments')) healthcare.push(url.pathname);
        if (url.pathname.startsWith('/build/')) {
            const file = path.resolve('public', '.' + url.pathname);
            if (!file.startsWith(path.resolve('public/build') + path.sep)) return route.abort();
            return route.fulfill({ body: await readFile(file), contentType: file.endsWith('.css') ? 'text/css' : 'text/javascript' });
        }
        if (url.pathname === '/api/v1/session') return json({ data: { id: 1, name: 'Operator', active_tenant_id: 1 } });
        if (url.pathname === '/api/v1/clinic/context') return json({ data: context });
        if (url.pathname === '/api/v1/public/settings') return json({ data: {} });
        if (url.pathname === '/api/v1/salon/billing/sources') return json({ data: appointment.invoice_id ? [] : [{
            id: 7, appointment_number: 'APT-7', customer: { type: 'salon_client', id: 17, name: 'Amina Ali' },
            branch: { id: 1, name: 'Main' }, currency: 'USD', total: '45.00' }], meta: { current_page: 1, last_page: 1 } });
        if (url.pathname === '/api/v1/salon/appointments/7/invoice') {
            state.creations++; appointment.invoice_id = 99;
            expect(route.request().postDataJSON()).toEqual({});
            return route.fulfill({ status: 201, json: { data: invoice } });
        }
        if (url.pathname === '/api/v1/salon/appointments/7') return json({ data: appointment });
        if (url.pathname === '/api/v1/salon/appointments/7/activity') return json({ data: [] });
        if (url.pathname === '/api/v1/salon/appointments/options') return json({ data: { statuses: {
            completed: ['Completed', 'green'], scheduled: ['Scheduled', 'blue'] }, actions: {},
            stylists: [{ id: 3, name: 'Asha Stylist' }], services: [], settings: { allow_walk_in: false } } });
        if (url.pathname === '/api/v1/salon/appointments') return json({ data: [appointment], meta: { last_page: 1 },
            today: [appointment], counts: [], summary: { completed: appointment.status === 'completed' ? 1 : 0 } });
        if (url.pathname === '/api/v1/salon/clients/17') return json({ data: client, activity: [] });
        if (url.pathname === '/api/v1/salon/booking-history/clients/17') return json({ data: { data: [], last_page: 1 } });
        if (url.pathname === '/api/v1/billing/invoices/99/payments') {
            state.payments++; const payment = route.request().postDataJSON();
            expect(Number(payment.amount)).toBe(20);
            invoice.paid = '20.00'; invoice.balance = '25.00'; invoice.status = 'partial';
            invoice.payments = [{ id: 1, amount: '20.00', method: 'cash', reference: 'Salon cash', paid_at: '2026-09-16T10:10:00Z' }];
            return json({ data: invoice });
        }
        if (url.pathname === '/api/v1/billing/invoices/99') return json({ data: invoice, methods: ['cash'] });
        if (url.pathname === '/api/v1/billing/invoices') {
            filters.push(Object.fromEntries(url.searchParams));
            return json({ data: { data: appointment.invoice_id ? [invoice] : [], current_page: 1, last_page: 1 }, methods: ['cash'] });
        }
        if (url.pathname.startsWith('/app/')) return route.fulfill({ contentType: 'text/html; charset=utf-8', body:
            `<!doctype html><html><head><meta charset="utf-8">${(entry.css || []).map(css => `<link rel="stylesheet" href="/build/${css}">`).join('')}</head><body><div id="app"></div><script type="module" src="/build/${entry.file}"></script></body></html>` });
        return route.fulfill({ status: 404, body: 'Unexpected request: ' + url.pathname });
    });
    return state;
}

test('completed Salon booking creates shared invoice, records payment, and appears in Client Billing', async ({ page }) => {
    const state = await fixture(page);
    await page.goto('/app/appointments/7?view=schedule');
    const dialog = page.getByRole('dialog');
    await expect(dialog.getByRole('button', { name: 'Create Invoice' })).toBeVisible();
    await dialog.getByRole('button', { name: 'Create Invoice' }).click();
    await expect(page).toHaveURL(/billing\/invoices\/99$/);
    await expect(page.getByRole('region', { name: 'Invoice details' })).toContainText('Haircut');
    await page.getByLabel('Amount', { exact: true }).fill('20');
    await page.getByLabel('Reference', { exact: true }).fill('Salon cash');
    await page.getByRole('button', { name: 'Record Payment' }).click();
    await expect(page.getByText('Paid: 20.00 · Balance: 25.00', { exact: true })).toBeVisible();
    await page.goto('/app/appointments/7?view=schedule');
    await expect(dialog.getByRole('link', { name: 'View Invoice' })).toBeVisible();
    await expect(dialog.getByRole('button', { name: 'Create Invoice' })).toHaveCount(0);
    await page.goto('/app/clients/17');
    await page.getByRole('button', { name: 'Billing', exact: true }).click();
    await expect(page.getByRole('columnheader', { name: 'Client', exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'INV-000099', exact: true })).toBeVisible();
    expect(state.filters.at(-1)).toMatchObject({ customer_type: 'salon_client', customer_id: '17' });
    expect(state.creations).toBe(1); expect(state.payments).toBe(1);
    expect(state.healthcare).toEqual([]); expect(state.errors).toEqual([]);
});

test('billing-only cashier can create from completed charges without opening appointments', async ({ page }) => {
    const state = await fixture(page, { cashier: true });
    await page.goto('/app/billing/invoices');
    const sources = page.getByRole('region', { name: 'Completed appointments awaiting invoices' });
    await expect(sources.getByText('APT-7')).toBeVisible();
    await sources.getByRole('button', { name: 'Create Invoice' }).click();
    await expect(page).toHaveURL(/billing\/invoices\/99$/);
    expect(state.creations).toBe(1); expect(state.healthcare).toEqual([]); expect(state.errors).toEqual([]);
});

test('scheduled Salon booking does not offer invoice creation', async ({ page }) => {
    const state = await fixture(page, { status: 'scheduled' });
    await page.goto('/app/appointments/7?view=schedule');
    await expect(page.getByRole('dialog')).toContainText('APT-7');
    await expect(page.getByRole('button', { name: 'Create Invoice' })).toHaveCount(0);
    expect(state.creations).toBe(0); expect(state.healthcare).toEqual([]); expect(state.errors).toEqual([]);
});
