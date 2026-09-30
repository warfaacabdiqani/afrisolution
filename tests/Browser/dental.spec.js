import { test, expect } from '@playwright/test';
import { readFile } from 'node:fs/promises';
import path from 'node:path';

async function fixture(page, permissions = ['*']) {
    const errors = []; page.on('pageerror', e => errors.push(e.message));
    const manifest = JSON.parse(await readFile('public/build/manifest.json', 'utf8'));
    const entry = manifest['resources/js/app.js'];
    const features = { patient_management: true, emr: true, billing: true };
    const context = { clinic: { id: 1, name: 'Dental A', timezone: 'UTC' }, branch: { id: 1, name: 'Main' }, branches: [{ id: 1, name: 'Main' }],
        business_type: { slug: 'dental', name: 'Dental Clinic' }, business_modules: { dental: true, clinical: true, patients: true, billing: true },
        labels: { customer: 'Patient' }, features, permissions,
        modules: [['patients', 'patient_management'], ['dental', 'emr'], ['billing', 'billing']].map(([key, feature]) => ({ key, label: key, allowed: true, business_allowed: true, business_modules: [key], feature, permission: key + '.view', icon: 'activity' })),
        operational: true, subscription: { status: 'active' }, role: 'owner', today: '2026-09-29', idle_timeout_minutes: 120 };
    const patient = { id: 17, first_name: 'Amina', last_name: 'Yusuf', full_name: 'Amina Yusuf', patient_number: 'PAT-17', gender: 'female', age: 30, status: 'active', allergies: [] };
    const procedures = []; const findings = []; const plans = [];
    const options = { numbering: 'Universal', teeth: [...Array.from({ length: 32 }, (_, i) => String(i + 1)), ...'ABCDEFGHIJKLMNOPQRST'],
        surfaces: { M: 'Mesial', D: 'Distal', O: 'Occlusal', I: 'Incisal', B: 'Buccal / facial', L: 'Lingual / palatal' },
        conditions: { sound: 'Sound', caries: 'Caries', missing: 'Missing' }, currency: 'USD' };
    await page.route('https://dental.test/**', async route => {
        const req = route.request(); const url = new URL(req.url()); const p = url.pathname;
        const json = (data, status = 200) => route.fulfill({ json: { data }, status });
        if (p.startsWith('/build/')) {
            const file = path.resolve('public', '.' + p);
            if (!file.startsWith(path.resolve('public/build') + path.sep)) return route.abort();
            return route.fulfill({ body: await readFile(file), contentType: file.endsWith('.css') ? 'text/css' : 'text/javascript' });
        }
        if (p === '/api/v1/session') return json({ id: 1, name: 'Owner', active_tenant_id: 1, email_verified: true });
        if (p === '/api/v1/public/settings') return json({});
        if (p === '/api/v1/clinic/context') return json(context);
        if (p === '/api/v1/clinic/patients/17') return json(patient);
        if (p === '/api/v1/clinic/patients/18') return json({ ...patient, id: 18, first_name: 'Safiya', full_name: 'Safiya Yusuf', patient_number: 'PAT-18' });
        if (p === '/api/v1/dental/patients/18/chart') return json({ findings: [], treatments: [] });
        if (p === '/api/v1/dental/patients/18/plans') return json([]);
        if (p === '/api/v1/dental/options') return json(options);
        if (p === '/api/v1/dental/procedures') {
            if (req.method() === 'POST') {
                const procedure = { id: procedures.length + 1, ...req.postDataJSON() }; procedures.push(procedure); return json(procedure, 201);
            }
            return json({ data: procedures, current_page: 1, last_page: 1 });
        }
        if (p === '/api/v1/dental/patients/17/chart') return json({ findings, treatments: plans.flatMap(plan => plan.items.filter(i => i.status === 'completed')) });
        if (p === '/api/v1/dental/patients/17/findings') {
            const row = { id: findings.length + 1, ...req.postDataJSON(), created_at: '2026-09-29T09:00:00Z', author: { name: 'Owner' } };
            findings.unshift(row); return json(row, 201);
        }
        if (p === '/api/v1/dental/patients/17/plans') {
            if (req.method() === 'POST') {
                const input = req.postDataJSON();
                const items = input.items.map((item, i) => ({ ...item, id: i + 1, procedure_name: procedures[0].name, procedure_code: procedures[0].code, status: 'planned', amount: '45.50', unit_price: '45.50' }));
                const plan = { id: 1, ...input, items, version: 1, status: 'draft', currency: 'USD', subtotal: '91.00', tax: '0.00', total: '91.00' };
                plans.unshift(plan); return json(plan, 201);
            }
            return json(plans);
        }
        if (p === '/api/v1/dental/plans/1/status') { plans[0].status = req.postDataJSON().status; plans[0].version++; return json(plans[0]); }
        if (p === '/api/v1/dental/plans/1/appointments') return json([]);
        const complete = p.match(/\/plans\/1\/items\/(\d+)\/complete$/);
        if (complete) {
            const plan = plans[0]; const item = plan.items.find(i => i.id === Number(complete[1]));
            item.status = 'completed'; item.completion_notes = req.postDataJSON().notes; item.completed_at = '2026-09-29T10:00:00Z';
            if (plan.items.every(i => i.status === 'completed')) plan.status = 'completed';
            plan.version++; return json(plan);
        }
        if (p.endsWith('/items/1/invoice')) { plans[0].items[0].invoice_id = 99; return json({ id: 99 }, 201); }
        if (p.startsWith('/app/')) return route.fulfill({ contentType: 'text/html; charset=utf-8', body: `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/build/${manifest['resources/css/app.css'].file}">${(entry.css || []).map(css => `<link rel="stylesheet" href="/build/${css}">`).join('')}</head><body><div id="app"></div><script type="module" src="/build/${entry.file}"></script></body></html>` });
        return route.fulfill({ status: 404, body: 'Unexpected request: ' + p });
    });
    return { errors, plans, findings, procedures };
}

test('catalog, Universal tooth chart and two-visit treatment plan work together', async ({ page }) => {
    const state = await fixture(page);
    await page.goto('/app/dental/procedures');
    await page.getByRole('button', { name: 'Add Procedure', exact: true }).click();
    await page.getByLabel('Procedure code').fill('FILL');
    await page.getByLabel('Procedure name').fill('Composite filling');
    await page.getByLabel('Default price').fill('45.50');
    await page.getByRole('button', { name: 'Save Procedure', exact: true }).click();
    await expect(page.getByRole('cell', { name: 'Composite filling', exact: true })).toBeVisible();
    await page.goto('/app/patients/17/dental');
    await expect(page.getByRole('heading', { name: 'Dental chart' })).toBeVisible();
    await page.getByRole('button', { name: 'Tooth 3, Uncharted', exact: true }).click();
    await page.getByLabel('Finding', { exact: true }).selectOption('caries');
    await page.getByLabel('Occlusal', { exact: true }).check();
    await page.getByLabel('Finding notes').fill('Observed cavity');
    await page.getByRole('button', { name: 'Record Finding', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Tooth 3, Caries', exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'New Treatment Plan', exact: true }).click();
    await page.getByLabel('Plan title').fill('Restorative treatment');
    await page.getByLabel('Procedure', { exact: true }).selectOption('1');
    await page.getByLabel('Treatment tooth').selectOption('3');
    await page.getByRole('button', { name: 'Add to Plan', exact: true }).click();
    await page.getByLabel('Visit number').fill('2');
    await page.getByLabel('Treatment tooth').selectOption('A');
    await page.getByRole('button', { name: 'Add to Plan', exact: true }).click();
    await page.getByRole('button', { name: 'Save Draft', exact: true }).click();
    await page.getByRole('button', { name: 'Accept Plan', exact: true }).click();
    await page.getByRole('button', { name: 'Complete treatment 1', exact: true }).click();
    await page.getByLabel('Completion notes').fill('Restoration completed');
    await page.getByRole('button', { name: 'Confirm Completion', exact: true }).click();
    await page.getByRole('button', { name: 'Create Invoice', exact: true }).click();
    await expect(page.getByRole('link', { name: 'View Invoice', exact: true })).toHaveAttribute('href', '/app/billing/invoices/99');
    await expect(page.getByText('Visit 2', { exact: true })).toBeVisible();
    expect(state.plans[0].items[1].status).toBe('planned');
    await page.getByRole('button', { name: 'Complete treatment 2', exact: true }).click();
    await page.getByRole('button', { name: 'Confirm Completion', exact: true }).click();
    await expect(page.getByText('Plan completed', { exact: true })).toBeVisible();
    expect(state.errors).toEqual([]);
    await page.screenshot({ path: 'test-results/dental-desktop.png', fullPage: true });
});

test('mobile chart remains usable and read-only staff see no clinical write actions', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const state = await fixture(page, ['dental.view', 'patients.view']);
    await page.goto('/app/patients/17/dental');
    await expect(page.getByRole('heading', { name: 'Dental chart' })).toBeVisible();
    await page.getByRole('button', { name: 'Primary teeth', exact: true }).click();
    await page.getByRole('button', { name: 'Tooth A, Uncharted', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Record Finding', exact: true })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'New Treatment Plan', exact: true })).toHaveCount(0);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    expect(state.errors).toEqual([]);
    await page.screenshot({ path: 'test-results/dental-mobile.png', fullPage: true });
});

test('sound on one surface does not hide another surface finding', async ({ page }) => {
    const state = await fixture(page);
    state.findings.push(
        { id: 2, tooth: '3', condition: 'sound', surfaces: ['M'], created_at: '2026-09-29T10:00:00Z' },
        { id: 1, tooth: '3', condition: 'caries', surfaces: ['O'], created_at: '2026-09-29T09:00:00Z' },
    );
    await page.goto('/app/patients/17/dental');
    await expect(page.getByRole('button', { name: 'Tooth 3, Caries, Sound', exact: true })).toBeVisible();
});

test('switching patients discards a delayed response for the previous patient', async ({ page }) => {
    await fixture(page);
    let release;
    const gate = new Promise(resolve => { release = resolve; });
    await page.route('**/api/v1/clinic/patients/17', async route => {
        await gate;
        await route.fulfill({ json: { data: { id: 17, first_name: 'Amina', last_name: 'Yusuf', full_name: 'Amina Yusuf', patient_number: 'PAT-17', age: 30, status: 'active', allergies: [] } } }).catch(() => {});
    });
    const requested = page.waitForRequest('**/api/v1/clinic/patients/17');
    await page.goto('/app/patients/17/dental'); await requested;
    await page.evaluate(() => document.querySelector('#app').__vue_app__.config.globalProperties.$router.push('/app/patients/18/dental'));
    release();
    await expect(page.getByRole('heading', { name: 'Safiya Yusuf', exact: true })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Amina Yusuf', exact: true })).toHaveCount(0);
});
