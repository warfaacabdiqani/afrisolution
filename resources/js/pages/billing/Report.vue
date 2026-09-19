<script setup>
import { onUnmounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { useClinicContextStore } from '../../stores/clinicContext';
import { billingApi } from '../../services/billing';
import FormErrors from '../../components/ui/FormErrors.vue';

const auth = useAuthStore(), context = useClinicContextStore(), router = useRouter();
const report = ref(null), error = ref(null), busy = ref(false);
const from = ref(''), to = ref(''), branch = ref('all');
let generation = 0, controller, previousTenant;
const money = value => Number(value ?? 0).toFixed(2);
const printReport = () => window.print();
function reset() {
    generation++; controller?.abort(); report.value = null; error.value = null; busy.value = false;
    const today = context.data?.today || new Date().toISOString().slice(0, 10);
    from.value = today.slice(0, 7) + '-01'; to.value = today; branch.value = 'all';
}
async function load(page = 1) {
    const token = ++generation;
    controller?.abort(); controller = new AbortController();
    report.value = null; error.value = null; busy.value = true;
    if (context.data?.clinic.id !== auth.user?.active_tenant_id || !context.allowed('billing')) { busy.value = false; return; }
    try {
        const response = await billingApi.report({ from: from.value, to: to.value, branch_id: branch.value, page },
            { headers: context.headers(), signal: controller.signal });
        if (token === generation) report.value = response.data.data;
    } catch (e) { if (token === generation && e.code !== 'ERR_CANCELED') error.value = e; }
    finally { if (token === generation) busy.value = false; }
}
watch(() => [auth.user?.active_tenant_id, context.data?.clinic.id, context.data?.branch?.id], () => {
    const tenant = auth.user?.active_tenant_id;
    const changed = previousTenant && previousTenant !== tenant;
    previousTenant = tenant;
    reset();
    if (changed) { router.replace('/app/billing/invoices'); return; }
    if (context.data) load();
}, { immediate: true, flush: 'sync' });
onUnmounted(reset);
</script>
<template>
    <div class="billing-report">
        <header class="report-actions patient-page-header"><div><RouterLink to="/app/billing/invoices">← Billing</RouterLink><h1>Billing Report</h1><p>Issued invoices and recorded payments.</p></div><button class="btn-secondary" :disabled="!report" @click="printReport">Print / Save PDF</button></header>
        <form class="report-actions clinic-panel p-5 report-filters" @submit.prevent="load(1)"><label>From Date<input v-model="from" type="date" required></label><label>To Date<input v-model="to" type="date" :min="from" required></label><label v-if="(context.data?.branches?.length || 0) > 1">Branch<select v-model="branch"><option value="all">All Authorized Branches</option><option v-for="b in context.data?.branches || []" :key="b.id" :value="String(b.id)">{{ b.name }}</option></select></label><span v-else>Branch: {{ context.data?.branches?.[0]?.name || 'Current branch' }}</span><button class="btn" :disabled="busy">Apply</button></form>
        <FormErrors :error="error"/><p v-if="busy" role="status">Loading billing report…</p>
        <article v-if="report" class="report-paper">
            <header class="report-title"><div><h2>{{ report.business_name }}</h2><strong>Billing Report</strong></div><div>{{ report.filters.from }} to {{ report.filters.to }}<br>{{ report.filters.branch_name }}<br>Timezone: {{ report.filters.timezone }}</div></header>
            <p class="report-note">Invoice figures use issue dates. Collections use payment dates. Outstanding is the current balance on invoices issued in this period. Each currency is reported separately.</p>
            <div v-if="!report.currencies.length" class="clinic-panel p-5">No invoices or payments in this period.</div>
            <section v-for="c in report.currencies" :key="c.currency" class="report-currency"><h3>{{ c.currency }}</h3><div class="report-metrics"><div><span>Total Invoiced</span><strong>{{ c.currency }} {{ money(c.total_invoiced) }}</strong></div><div><span>Total Collected</span><strong>{{ c.currency }} {{ money(c.total_collected) }}</strong></div><div><span>Outstanding Balance</span><strong>{{ c.currency }} {{ money(c.outstanding) }}</strong></div></div><div class="report-breakdowns"><div><h4>Invoices</h4><p>Total: {{ c.invoice_count }}</p><p>Paid: {{ c.statuses.paid }}</p><p>Partially Paid: {{ c.statuses.partial }}</p><p>Unpaid: {{ c.statuses.unpaid }}</p></div><div><h4>Payments by Method</h4><p v-for="m in c.payment_methods" :key="m.method">{{ m.method.replaceAll('_',' ') }}: {{ c.currency }} {{ money(m.amount) }}</p><p v-if="!c.payment_methods.length">No payments in this period.</p></div></div></section>
            <section class="report-invoices"><h3>Filtered Invoices</h3><p>Showing {{ report.invoices.from || 0 }}–{{ report.invoices.to || 0 }} of {{ report.invoices.total }} invoices.</p><div class="report-table-scroll"><table><thead><tr><th>Invoice #</th><th>Issued</th><th>Customer</th><th>Branch</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th class="report-actions">Action</th></tr></thead><tbody><tr v-for="i in report.invoices.data" :key="i.id"><td>{{ i.number }}</td><td>{{ i.issued_at?.slice(0,10) }}</td><td>{{ i.customer_label }}: {{ i.customer_name }}</td><td>{{ i.branch_name }}</td><td>{{ i.currency }} {{ money(i.total) }}</td><td>{{ money(i.paid) }}</td><td>{{ money(i.balance) }}</td><td>{{ i.status }}</td><td class="report-actions"><RouterLink :to="`/app/billing/invoices/${i.id}`">View Invoice</RouterLink></td></tr><tr v-if="!report.invoices.data.length"><td colspan="9">No invoices issued in this period.</td></tr></tbody></table></div><div class="report-actions report-pages"><button class="btn-secondary" :disabled="report.invoices.current_page <= 1 || busy" @click="load(report.invoices.current_page - 1)">Previous</button><span>Page {{ report.invoices.current_page }} of {{ report.invoices.last_page }}</span><button class="btn-secondary" :disabled="report.invoices.current_page >= report.invoices.last_page || busy" @click="load(report.invoices.current_page + 1)">Next</button></div></section>
        </article>
    </div>
</template>
<style scoped>
.billing-report{max-width:1200px;margin:auto}.report-filters{display:flex;gap:16px;align-items:end;flex-wrap:wrap;margin-bottom:18px}.report-filters label{display:grid;gap:5px}.report-filters input,.report-filters select{border:1px solid #b9c9d8;border-radius:7px;padding:8px}.report-paper{background:white;color:#102334;padding:30px;border:1px solid #d8e1e8;border-radius:10px}.report-title{display:flex;justify-content:space-between;border-bottom:2px solid #00615d;padding-bottom:16px}.report-title h2{font-size:24px;font-weight:700}.report-title strong{font-size:19px}.report-title>div:last-child{text-align:right}.report-note{font-size:12px;color:#56657a;margin:16px 0}.report-currency{border-top:1px solid #d8e1e8;padding:18px 0}.report-currency h3,.report-invoices h3{font-size:20px;font-weight:700}.report-metrics,.report-breakdowns{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin:16px 0}.report-metrics>div{padding:15px;background:#f0f8f6;display:grid}.report-metrics strong{font-size:20px}.report-breakdowns{grid-template-columns:repeat(2,1fr)}.report-breakdowns h4{font-weight:700}.report-table-scroll{overflow-x:auto}.report-invoices table{width:100%;border-collapse:collapse}.report-invoices th,.report-invoices td{text-align:left;padding:8px;border-bottom:1px solid #d8e1e8}.report-pages{display:flex;gap:12px;align-items:center;margin-top:16px}@media print{:global(.clinic-sidebar),:global(.clinic-topbar),:global(.clinic-trial),:global(.clinic-overlay){display:none!important}:global(.clinic-workspace){margin:0!important;min-height:0!important}:global(.clinic-content){padding:0!important;max-width:none!important}.billing-report{max-width:none}.report-actions{display:none!important}.report-paper{border:0;padding:0}.report-currency,.report-invoices tr{break-inside:avoid}@page{size:A4 landscape;margin:14mm}}
</style>
