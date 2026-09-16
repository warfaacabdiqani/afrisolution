import { test, expect } from '@playwright/test';
import { readFile } from 'node:fs/promises';
import path from 'node:path';

for (const [business, customerLabel] of [['clinic', 'Patient'], ['beauty-salon', 'Client']]) {
    test(`${business} enters the shared ledger, opens details and records a payment`, async ({ page }) => {
        const errors = []; page.on('pageerror', error => errors.push(error.message));
        const manifest = JSON.parse(await readFile('public/build/manifest.json', 'utf8'));
        const entry = manifest['resources/js/app.js'];
        const context = {
            clinic: { id: 1, name: 'Test Business', timezone: 'UTC' }, branch: { id: 1, name: 'Main' }, branches: [{ id: 1, name: 'Main' }],
            business_type: { slug: business, name: business }, labels: { customer: customerLabel },
            business_modules: { billing: true, clinical: business === 'clinic' }, permissions: ['billing.view', 'billing.payments'], features: { billing: true },
            modules: [{ key: 'billing', label: 'Billing', icon: 'revenue', allowed: true, business_allowed: true, business_modules: ['billing'], feature: 'billing', permission: 'billing.view' }],
            operational: true, subscription: { status: 'active' }, role: 'cashier', idle_timeout_minutes: 120,
        };
        const invoice = { id: 1, number: 'INV-000001', tenant_id: 1, branch: { id: 1, name: 'Main' },
            customer: { type: business === 'clinic' ? 'patient' : 'salon_client', id: 1, name: 'Snapshot Customer' },
            currency: 'USD', status: 'unpaid', subtotal: '45.00', discount: '0.00', tax: '0.00', total: '45.00', paid: '0.00', balance: '45.00', snapshot_version: 2,
            items: [{ id: 1, description: 'Saved charge', quantity: 1, unit_price: '45.00', amount: '45.00', line_total: '45.00', tax_amount: '0.00', discount_amount: '0.00' }], payments: [] };
        let payment;
        await page.route('https://billing.test/**', async route => {
            const url = new URL(route.request().url());
            const json = data => route.fulfill({ json: data });
            if (url.pathname.startsWith('/build/')) {
                const file = path.resolve('public', '.' + url.pathname);
                if (!file.startsWith(path.resolve('public/build') + path.sep)) return route.abort();
                return route.fulfill({ body: await readFile(file), contentType: file.endsWith('.css') ? 'text/css' : 'text/javascript' });
            }
            if (url.pathname === '/api/v1/session') return json({ data: { id: 1, name: 'Cashier', active_tenant_id: 1 } });
            if (url.pathname === '/api/v1/clinic/context') return json({ data: context });
            if (url.pathname === '/api/v1/public/settings') return json({ data: {} });
            if (url.pathname === '/api/v1/billing/invoices/1/payments') {
                payment = route.request().postDataJSON();
                expect(route.request().headers()['x-clinic-context']).toBe('1');
                invoice.paid = '45.00'; invoice.balance = '0.00'; invoice.status = 'paid';
                invoice.payments = [{ id: 1, amount: '45.00', method: 'cash', reference: 'Received', paid_at: '2026-09-16T10:00:00Z' }];
                return json({ data: invoice });
            }
            if (url.pathname === '/api/v1/billing/invoices/1') return json({ data: invoice, methods: ['cash'] });
            if (url.pathname === '/api/v1/billing/invoices') return json({ data: { data: [invoice], current_page: 1, last_page: 1 }, methods: ['cash'] });
            if (url.pathname.startsWith('/app/')) return route.fulfill({ contentType: 'text/html; charset=utf-8', body: `<!doctype html><html><head><meta charset="utf-8">${(entry.css || []).map(css => `<link rel="stylesheet" href="/build/${css}">`).join('')}</head><body><div id="app"></div><script type="module" src="/build/${entry.file}"></script></body></html>` });
            return route.fulfill({ status: 404, body: 'Unexpected request' });
        });
        await page.goto('/app/billing');
        await expect(page.getByRole('columnheader', { name: customerLabel, exact: true })).toBeVisible();
        await expect(page.getByRole('columnheader', { name: 'Balance', exact: true })).toBeVisible();
        await page.getByRole('link', { name: 'INV-000001', exact: true }).click();
        await expect(page.getByRole('region', { name: 'Invoice details' })).toContainText('Snapshot Customer');
        await page.getByLabel('Reference', { exact: true }).fill('Received');
        await page.getByRole('button', { name: 'Record Payment', exact: true }).click();
        await expect(page.getByText('Paid: 45.00 · Balance: 0.00', { exact: true })).toBeVisible();
        expect(payment.idempotency_key).toBeTruthy();
        expect(payment.amount).toBe('45.00');
        expect(errors).toEqual([]);
    });
}
