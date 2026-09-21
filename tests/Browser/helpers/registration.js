import { readFile } from 'node:fs/promises';
import path from 'node:path';

export async function fixture(page, failRegistration = false) {
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
        if (url.pathname === '/register/validate') return route.fulfill({ json: { valid: true } });
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

