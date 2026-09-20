import { test, expect } from '@playwright/test';
import { readFile } from 'node:fs/promises';
import path from 'node:path';

async function fixture(page) {
    const errors = []; page.on('pageerror', error => errors.push(error.message));
    const manifest = JSON.parse(await readFile('public/build/manifest.json', 'utf8'));
    const entry = manifest['resources/js/app.js'];
    const stylesheet = manifest['resources/css/app.css'].file;
    const modules = ['reports', 'billing'].map(key => ({ key, label: key, icon: 'activity', allowed: true,
        business_allowed: true, business_modules: [key], feature: key === 'reports' ? 'basic_reports' : 'billing', permission: key + '.view' }));
    const context = { clinic: { id: 1, name: 'Afriso', timezone: 'Africa/Nairobi' }, branch: { id: 1, name: 'Main Branch' },
        branches: [{ id: 1, name: 'Main Branch' }], business_type: { slug: 'clinic', name: 'Clinic' },
        business_modules: { clinical: true, reports: true, billing: true }, features: { basic_reports: true, billing: true },
        permissions: ['*'], modules, operational: true, subscription: { status: 'active' }, role: 'owner',
        today: '2026-09-19', idle_timeout_minutes: 120 };
    await page.route('https://billing.test/**', async route => {
        const url = new URL(route.request().url()); const json = data => route.fulfill({ json: data });
        if (url.pathname.startsWith('/build/')) {
            const file = path.resolve('public', '.' + url.pathname);
            if (!file.startsWith(path.resolve('public/build') + path.sep)) return route.abort();
            return route.fulfill({ body: await readFile(file), contentType: file.endsWith('.css') ? 'text/css' : 'text/javascript' });
        }
        if (url.pathname === '/api/v1/session') return json({ data: { id: 1, name: 'Owner', active_tenant_id: 1 } });
        if (url.pathname === '/api/v1/clinic/context') return json({ data: context });
        if (url.pathname === '/api/v1/public/settings') return json({ data: {} });
        if (url.pathname === '/api/v1/clinic/reports/patients') return json({ data: {
            metrics: [{ label: 'Total Patients', value: 1 }, { label: 'New Patients', value: 1 },
                { label: 'Active Patients', value: 1 }, { label: 'Archived Patients', value: 0 }],
            charts: [{ title: 'Gender Distribution', items: [{ label: 'Female', value: 1 }] }],
            table: [{ patient_number: 'PAT-1', name: 'Amina Yusuf', gender: 'female', age: 30, phone: '0700000000',
                registered_at: '2026-09-19', branch: 'Main Branch' }], message: null,
        } });
        if (url.pathname === '/api/v1/clinic/reports/appointments') return json({ data: {
            metrics: [{ label: 'Total Appointments', value: 2 }, { label: 'Completed', value: 2 },
                { label: 'Cancelled', value: 0 }, { label: 'No Shows', value: 0 }, { label: 'Walk-ins', value: 0 }],
            charts: [{ title: 'Appointments by Doctor', items: [{ label: 'Ahmed', value: 2 }] }],
            table: [{ date: '2026-09-19', time: '09:00', patient: 'Amina Yusuf', doctor: 'Ahmed Hassan', visit_type: 'General', status: 'completed' },
                { date: '2026-09-19', time: '10:00', patient: 'Ali Noor', doctor: 'Ahmed Hassan', visit_type: 'General', status: 'completed' }], message: null,
        } });
        if (url.pathname === '/api/v1/billing/reports/summary') return json({ data: {
            business_name: 'Afriso', filters: { from: '2026-09-01', to: '2026-09-19', timezone: 'Africa/Nairobi', branch_name: 'Main Branch', branch_id: 'all' },
            currencies: [{ currency: 'USD', total_invoiced: '45.00', total_collected: '20.00', outstanding: '25.00',
                invoice_count: 1, statuses: { paid: 0, partial: 1, unpaid: 0 }, payment_methods: [{ method: 'cash', amount: '20.00', count: 1 }] }],
            invoices: { data: [{ id: 1, number: 'INV-1', issued_at: '2026-09-19', customer_label: 'Patient', customer_name: 'Amina Yusuf',
                branch_name: 'Main Branch', currency: 'USD', total: '45.00', paid: '20.00', balance: '25.00', status: 'partial' }],
                from: 1, to: 1, total: 1, current_page: 1, last_page: 1 },
        } });
        if (url.pathname.startsWith('/app/')) return route.fulfill({ contentType: 'text/html; charset=utf-8', body:
            `<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="/build/${stylesheet}">${(entry.css || []).map(css => `<link rel="stylesheet" href="/build/${css}">`).join('')}</head><body><div id="app"></div><script type="module" src="/build/${entry.file}"></script></body></html>` });
        return route.fulfill({ status: 404, body: 'Unexpected request' });
    });
    return errors;
}

test('report browser shows one compact category at a time and filters search', async ({ page }) => {
    const errors = await fixture(page);
    await page.goto('/app/reports');
    await expect(page.locator('.reports-item')).toHaveCount(4);
    await expect(page.getByRole('link', { name: /Daily Appointment Report/ })).toHaveCount(0);
    await page.getByRole('button', { name: 'Appointments', exact: true }).click();
    await expect(page.locator('.reports-item')).toHaveCount(5);
    await page.getByRole('searchbox', { name: 'Search reports' }).fill('billing');
    await expect(page.locator('.reports-item')).toHaveCount(1);
    await expect(page.getByRole('link', { name: /Billing Report/ })).toBeVisible();
    await page.setViewportSize({ width: 375, height: 800 });
    await page.getByRole('searchbox', { name: 'Search reports' }).fill('appointment');
    const cards = page.locator('.reports-item');
    expect(await cards.count()).toBeGreaterThan(1);
    const first = await cards.nth(0).boundingBox(), second = await cards.nth(1).boundingBox();
    expect(second.y).toBeGreaterThan(first.y);
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(375);
    expect(errors).toEqual([]);
});

test('patient, registration, appointment and billing previews put records on page one', async ({ page }) => {
    const errors = await fixture(page);
    for (const [pathName, title, rowText, summaryText] of [
        ['/app/reports/generate/patients', 'Patient List Report', 'PAT-1', '1 patient'],
        ['/app/reports/generate/new_registrations', 'New Patient Registrations', 'PAT-1', '1 new patient registered'],
        ['/app/reports/generate/daily_appointments', 'Daily Appointment Report', 'Ali Noor', '2 appointments'],
    ]) {
        await page.goto(pathName);
        const document = page.locator('.report-document');
        await expect(document).toContainText(title);
        await expect(document.locator('.report-summary')).toContainText(summaryText);
        await expect(document.locator('tbody')).toContainText(rowText);
        await expect(document.locator('.report-breakdown')).toHaveCount(0);
        await page.emulateMedia({ media: 'print' });
        await expect(page.locator('.clinic-sidebar')).toBeHidden();
        await expect(page.locator('.report-filter-form')).toBeHidden();
        await expect(document.locator('tbody')).toBeVisible();
        expect((await document.locator('table').boundingBox()).y).toBeLessThan(450);
        await page.emulateMedia({ media: 'screen' });
    }
    await page.goto('/app/billing/report');
    await expect(page.locator('.report-paper')).toContainText('Total Invoiced');
    await expect(page.locator('.report-paper table tbody')).toContainText('INV-1');
    await page.emulateMedia({ media: 'print' });
    await expect(page.locator('.clinic-sidebar')).toBeHidden();
    expect((await page.locator('.report-paper table').boundingBox()).y).toBeLessThan(600);
    expect(errors).toEqual([]);
});

test('an empty report keeps its compact header and a clear empty state', async ({ page }) => {
    const errors = await fixture(page);
    await page.route('https://billing.test/api/v1/clinic/reports/patients**', route => route.fulfill({ json: {
        data: { metrics: [{ label: 'Total Patients', value: 0 }, { label: 'New Patients', value: 0 },
            { label: 'Active Patients', value: 0 }, { label: 'Archived Patients', value: 0 }],
            charts: [], table: [], message: null },
    } }));
    await page.goto('/app/reports/generate/patients');
    const document = page.locator('.report-document');
    await expect(document).toContainText('Patient List Report');
    await expect(document).toContainText('No records found for the selected filters.');
    await expect(document.locator('.report-summary')).toContainText('0 patients');
    expect(errors).toEqual([]);
});
