<script setup>
import { computed, onUnmounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { useClinicContextStore } from '../../stores/clinicContext';
import { billingApi } from '../../services/billing';
import FormErrors from '../../components/ui/FormErrors.vue';

const route = useRoute(), router = useRouter(), auth = useAuthStore(), context = useClinicContextStore();
const data = ref(null), identity = ref(null), error = ref(null), loading = ref(false), historical = ref(true);
const receipt = computed(() => route.path.endsWith('/receipt'));
const snapshot = computed(() => receipt.value ? data.value?.snapshot : null);
const money = value => Number(value ?? 0).toFixed(2);
const print = () => window.print();
let generation = 0, controller, previousTenant;
function clear() { generation++; controller?.abort(); data.value = null; identity.value = null; error.value = null; loading.value = false; }
async function load() {
    clear();
    if (!context.data || context.data.clinic.id !== auth.user?.active_tenant_id || !context.allowed('billing')) return;
    const token = generation;
    controller = new AbortController();
    loading.value = true;
    try {
        const response = await (receipt.value ? billingApi.receipt(route.params.id, { headers: context.headers(), signal: controller.signal })
            : billingApi.invoicePrint(route.params.id, { headers: context.headers(), signal: controller.signal }));
        if (token !== generation) return;
        data.value = response.data.data;
        identity.value = receipt.value ? response.data.data.snapshot : response.data.document.identity;
        historical.value = receipt.value || response.data.document.historical_identity_available;
    } catch (e) { if (token === generation && e.code !== 'ERR_CANCELED') error.value = e; }
    finally { if (token === generation) loading.value = false; }
}
watch(() => [auth.user?.active_tenant_id, context.data?.clinic.id, context.data?.branch?.id, route.params.id, route.path], () => {
    const tenant = auth.user?.active_tenant_id;
    const changed = previousTenant && tenant !== previousTenant;
    previousTenant = tenant;
    if (changed) { clear(); router.replace('/app/billing/invoices'); return; }
    load();
}, { immediate: true, flush: 'sync' });
onUnmounted(clear);
</script>
<template>
    <div class="billing-print">
        <nav class="print-actions"><RouterLink :to="receipt && data ? `/app/billing/invoices/${data.invoice_id}` : `/app/billing/invoices/${route.params.id}`">Back to invoice</RouterLink><button class="btn" :disabled="!data" @click="print">Print / Save PDF</button></nav>
        <FormErrors :error="error"/><p v-if="loading" role="status">Loading document…</p>
        <article v-if="data && identity" class="print-paper">
            <header><div><h1>{{ identity.business_name }}</h1><p>{{ identity.branch_name }}</p><p>{{ identity.business_address }}</p><p>{{ identity.business_phone }} <span v-if="identity.business_email"> · {{ identity.business_email }}</span></p></div><div class="print-heading"><h2>{{ receipt ? 'Payment Receipt' : 'Invoice' }}</h2><strong>{{ data.number }}</strong></div></header>
            <p v-if="!historical" class="print-note">Business details shown are current; historical business details were not captured when this invoice was issued.</p>
            <dl class="print-details"><div><dt>{{ identity.customer_label }}</dt><dd>{{ receipt ? snapshot.customer_name : data.customer.name }}</dd></div><div><dt>Invoice</dt><dd>{{ receipt ? snapshot.invoice_number : data.number }}</dd></div><div><dt>{{ receipt ? 'Paid at (UTC)' : 'Issued at (UTC)' }}</dt><dd>{{ receipt ? snapshot.payment_at : data.issued_at }}</dd></div><div v-if="!receipt"><dt>Status</dt><dd>{{ data.status }}</dd></div><div v-if="!receipt"><dt>Source</dt><dd>{{ data.source?.type?.replaceAll('_',' ') }} #{{ data.source?.id }}</dd></div></dl>
            <template v-if="receipt"><table><tbody><tr><th>Payment method</th><td>{{ snapshot.payment_method?.replaceAll('_',' ') }}</td></tr><tr><th>Reference</th><td>{{ snapshot.payment_reference || '—' }}</td></tr><tr><th>Invoice total</th><td>{{ snapshot.currency }} {{ money(snapshot.invoice_total) }}</td></tr><tr><th>Previously paid</th><td>{{ snapshot.currency }} {{ money(snapshot.previously_paid) }}</td></tr><tr class="total"><th>Payment received</th><td>{{ snapshot.currency }} {{ money(snapshot.payment_amount) }}</td></tr><tr><th>Balance after payment</th><td>{{ snapshot.currency }} {{ money(snapshot.balance_after) }}</td></tr></tbody></table><p v-if="snapshot.recorded_by_name" class="print-note">Recorded by {{ snapshot.recorded_by_name }}</p></template>
            <template v-else><table><thead><tr><th>Description</th><th>Qty</th><th>Unit</th><th>Discount</th><th>Tax</th><th>Line total</th></tr></thead><tbody><tr v-for="item in data.items" :key="item.id"><td>{{ item.description }}</td><td>{{ item.quantity }}</td><td>{{ money(item.unit_price) }}</td><td>{{ money(item.discount_amount) }}</td><td>{{ money(item.tax_amount) }}</td><td>{{ money(item.line_total ?? item.amount) }}</td></tr></tbody></table><dl class="print-totals"><div><dt>Subtotal</dt><dd>{{ data.currency }} {{ money(data.subtotal) }}</dd></div><div><dt>Discount</dt><dd>{{ money(data.discount) }}</dd></div><div><dt>Tax</dt><dd>{{ money(data.tax) }}</dd></div><div class="total"><dt>Total</dt><dd>{{ data.currency }} {{ money(data.total) }}</dd></div><div><dt>Paid</dt><dd>{{ money(data.paid) }}</dd></div><div><dt>Balance</dt><dd>{{ money(data.balance) }}</dd></div></dl></template>
        </article>
    </div>
</template>
<style scoped>
.billing-print{max-width:900px;margin:auto;padding:24px}.print-actions{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px}.print-paper{background:white;color:#102334;padding:40px;box-shadow:0 2px 12px #0002}.print-paper header{display:flex;justify-content:space-between;border-bottom:2px solid #00615d;padding-bottom:20px;margin-bottom:22px}.print-paper h1{font-size:24px;font-weight:700}.print-heading{text-align:right}.print-heading h2{font-size:22px;font-weight:700}.print-details{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin:24px 0}.print-details dt,.print-totals dt{color:#56657a}.print-details dd{font-weight:600}.print-paper table{width:100%;border-collapse:collapse;margin:24px 0}.print-paper th,.print-paper td{padding:10px;border-bottom:1px solid #d8e1e8;text-align:left}.print-paper td:last-child{text-align:right}.print-totals{margin-left:auto;max-width:300px}.print-totals div{display:flex;justify-content:space-between;padding:5px}.total{font-weight:700;border-top:2px solid #00615d}.print-note{font-size:12px;color:#5f6b7a;margin-top:16px}@media print{:global(.clinic-sidebar),:global(.clinic-topbar),:global(.clinic-trial),:global(.clinic-overlay){display:none!important}:global(.clinic-workspace){margin:0!important;min-height:0!important}:global(.clinic-content){padding:0!important;max-width:none!important}.billing-print{padding:0;max-width:none}.print-actions{display:none}.print-paper{padding:0;box-shadow:none}@page{size:A4;margin:18mm}}
</style>
