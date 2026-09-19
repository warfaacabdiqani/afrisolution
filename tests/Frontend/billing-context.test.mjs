import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';
import * as vue from 'vue';

// Exercise the real composable with Vue reactivity/lifecycle and deliberately late API responses.
async function harness(customer = null) {
    const route = vue.reactive({ params: { id: '10' } });
    const auth = vue.reactive({ user: { active_tenant_id: 1 } });
    const context = vue.reactive({ data: { clinic: { id: 1 }, branch: { id: 11 } },
        allowed: () => true,
        headers() { return { 'X-Clinic-Context': String(auth.user.active_tenant_id), 'X-Branch-Context': String(this.data.branch.id) }; },
    });
    const requests = [];
    const request = (kind, id, payload, config) => new Promise((resolve, reject) => requests.push({ kind, id, payload, config, resolve, reject }));
    const api = { list: (page, config, filter) => request('list', page, filter, config), show: (id, config) => request('show', id, null, config),
        payment: (id, payload, config) => request('payment', id, payload, config) };
    const replacements = [];
    const router = { replace(path) { replacements.push(path); route.params = {}; } };
    const sandbox = vm.createContext({ AbortController, crypto: globalThis.crypto });
    const imports = {
        vue,
        'vue-router': { useRoute: () => route, useRouter: () => router },
        '../stores/auth': { useAuthStore: () => auth },
        '../stores/clinicContext': { useClinicContextStore: () => context },
        '../services/billing': { billingApi: api },
    };
    const module = new vm.SourceTextModule(await readFile(new URL('../../resources/js/composables/useBillingLedger.js', import.meta.url), 'utf8'), { context: sandbox });
    await module.link(name => {
        const exports = imports[name];
        return new vm.SyntheticModule(Object.keys(exports), function () { for (const [key, value] of Object.entries(exports)) this.setExport(key, value); }, { context: sandbox });
    });
    await module.evaluate();
    const renderer = vue.createRenderer({ createElement: () => ({}), createText: () => ({}), createComment: () => ({}),
        insert() {}, remove() {}, setText() {}, setElementText() {}, parentNode() {}, nextSibling() {}, patchProp() {} });
    let state;
    const app = renderer.createApp({ setup() { state = module.namespace.useBillingLedger(() => customer); return () => vue.h('div'); } });
    app.mount({});
    return { route, auth, context, requests, replacements, state, stop: () => app.unmount() };
}
const flush = async () => { await Promise.resolve(); await vue.nextTick(); await Promise.resolve(); };
const detail = (name = 'Business A customer') => ({ data: { data: { id: 10, customer: { name }, balance: '45.00' }, methods: ['cash'] } });

test('patient profile IDs are customer filters, never invoice IDs, and changing patient ignores stale history', async () => {
    const customer = vue.reactive({ customer_type: 'patient', customer_id: 7 });
    const h = await harness(customer);
    try {
        const old = h.requests[0];
        assert.equal(old.kind, 'list'); assert.equal(old.payload.customer_id, 7);
        assert.equal(h.state.invoiceId.value, null);
        customer.customer_id = 8;
        assert.equal(h.state.data.value, null);
        assert.equal(h.requests.at(-1).payload.customer_id, 8);
        h.requests.at(-1).resolve({ data: { data: { data: [], current_page: 1, last_page: 1 }, methods: ['cash'] } }); await flush();
        old.resolve(detail()); await flush();
        assert.equal(h.state.data.value.data.length, 0);
    } finally { h.stop(); }
});

test('Salon Client history is server filtered and clears its filter across business switches', async () => {
    const customer = vue.reactive({ customer_type: 'salon_client', customer_id: 17 });
    const h = await harness(customer);
    try {
        const old = h.requests[0];
        assert.equal(old.kind, 'list');
        assert.equal(old.payload.customer_type, 'salon_client');
        assert.equal(old.payload.customer_id, 17);
        h.auth.user.active_tenant_id = 2;
        assert.equal(old.config.signal.aborted, true);
        assert.equal(h.state.data.value, null);
        assert.equal(h.replacements.at(-1), '/app/billing/invoices');
        h.context.data = { clinic: { id: 2 }, branch: { id: 22 } };
        assert.equal(h.requests.length, 1, 'the old Client ID must not be sent to the next business');
        old.resolve(detail('Salon A private name')); await flush();
        assert.equal(h.state.data.value, null);
        h.state.load();
        assert.equal(h.requests.length, 1);
    } finally { h.stop(); }
});

test('switching business clears detail, payment form and selection, and ignores stale responses', async () => {
    const h = await harness();
    try {
        h.requests[0].resolve(detail()); await flush();
        assert.equal(h.state.data.value.customer.name, 'Business A customer');
        h.state.reference.value = 'Private reference'; h.state.amount.value = '20';
        const payment = h.state.pay();
        const pending = h.requests.at(-1);
        h.auth.user.active_tenant_id = 2;
        assert.equal(h.state.data.value, null);
        assert.equal(h.state.reference.value, ''); assert.equal(h.state.amount.value, '');
        assert.equal(h.state.busy.value, false); assert.equal(pending.config.signal.aborted, true);
        assert.equal(h.replacements.at(-1), '/app/billing/invoices');
        h.context.data = { clinic: { id: 2 }, branch: { id: 22 } };
        const next = h.requests.at(-1);
        assert.equal(next.kind, 'list'); assert.equal(next.config.headers['X-Clinic-Context'], '2');
        pending.resolve(detail()); await payment; await flush();
        assert.equal(h.state.data.value, null);
        next.resolve({ data: { data: { data: [], current_page: 1, last_page: 1 }, methods: ['cash'] } }); await flush();
        assert.equal(h.state.data.value.data.length, 0);
        h.route.params = { id: '20' }; h.requests.at(-1).resolve(detail('Business B customer')); await flush();
        const nextPayment = h.state.pay(); const request = h.requests.at(-1);
        assert.notEqual(request.payload.idempotency_key, pending.payload.idempotency_key);
        request.reject(new Error('network')); await nextPayment;
    } finally { h.stop(); }
});

test('late detail response cannot overwrite the new business list', async () => {
    const h = await harness();
    try {
        const old = h.requests[0];
        h.auth.user.active_tenant_id = 2; h.context.data = { clinic: { id: 2 }, branch: { id: 22 } };
        h.requests.at(-1).resolve({ data: { data: { data: [], current_page: 1, last_page: 1 }, methods: ['cash'] } }); await flush();
        old.resolve(detail()); await flush();
        assert.equal(h.state.data.value.data.length, 0); assert.equal(h.state.error.value, null);
    } finally { h.stop(); }
});

test('branch changes clear state and payment retries retain their key within one context', async () => {
    const h = await harness();
    try {
        h.requests[0].resolve(detail()); await flush();
        let pending = h.state.pay(); const first = h.requests.at(-1);
        first.reject(new Error('response lost')); await pending;
        pending = h.state.pay(); const retry = h.requests.at(-1);
        assert.equal(first.payload.idempotency_key, retry.payload.idempotency_key);
        h.context.data.branch.id = 12;
        assert.equal(h.state.data.value, null); assert.equal(h.state.amount.value, '');
        assert.equal(h.requests.at(-1).config.headers['X-Branch-Context'], '12');
        retry.reject(new Error('old context')); await pending;
        assert.equal(h.state.error.value, null);
    } finally { h.stop(); }
});
