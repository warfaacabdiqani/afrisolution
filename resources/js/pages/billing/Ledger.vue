<script setup>
import { computed } from 'vue';
import { useBillingLedger } from '../../composables/useBillingLedger';
import FormErrors from '../../components/ui/FormErrors.vue';
import BillingSources from '../../components/salon/BillingSources.vue';
const props = defineProps({ customer: { type: Object, default: null } });
const { context, data, error, busy, loading, amount, method, reference, methods, invoiceId, load, pay } = useBillingLedger(() => props.customer);
const customerLabel = computed(() => context.label('customer', 'Customer'));
const money = value => Number(value ?? 0).toFixed(2);
const sourceLabel = source => ({ dental_treatment: 'Dental Treatment', clinic_appointment: 'Consultation', salon_appointment: 'Appointment' }[source?.type] || 'Charge');
</script>
<template>
    <div>
        <header class="patient-page-header">
            <div><RouterLink v-if="invoiceId" to="/app/billing/invoices">← Invoices</RouterLink>
                <h1>{{ invoiceId ? (data?.number || 'Invoice') : 'Billing' }}</h1><p>Invoices and recorded payments.</p></div>
            <div class="flex gap-2"><RouterLink class="btn-secondary" to="/app/billing/report">Billing Report</RouterLink><RouterLink v-if="context.allowed('settings')" class="btn-secondary" to="/app/settings/billing">Billing Settings</RouterLink></div>
        </header>
        <FormErrors :error="error"/>
        <BillingSources v-if="!invoiceId && !customer && context.businessTypeSlug==='beauty-salon' && context.can('billing.create')" />
        <template v-if="data">
            <section v-if="!invoiceId" class="clinic-panel overflow-x-auto" aria-label="Invoices">
                <table class="w-full text-sm">
                    <thead><tr><th>Invoice</th><th>Date</th><th>Source</th><th>{{ customerLabel }}</th><th>Branch</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th></tr></thead>
                    <tbody><tr v-for="row in data.data" :key="row.id">
                        <td><RouterLink class="text-emerald-700" :to="'/app/billing/invoices/'+row.id">{{ row.number }}</RouterLink></td>
                        <td>{{ row.issued_at?.slice(0,10) || row.created_at?.slice(0,10) }}</td><td>{{ sourceLabel(row.source) }}</td>
                        <td>{{ row.customer.name }}</td><td>{{ row.branch?.name }}</td><td>{{ row.currency }} {{ money(row.total) }}</td>
                        <td>{{ money(row.paid) }}</td><td>{{ money(row.balance) }}</td><td>{{ row.status }}</td>
                    </tr><tr v-if="!data.data.length"><td colspan="9">No invoices yet.</td></tr></tbody>
                </table>
                <div class="p-4 flex gap-3"><button class="btn-secondary" :disabled="data.current_page<=1" @click="load(data.current_page-1)">Previous</button>
                    <span>{{ data.current_page }} / {{ data.last_page }}</span><button class="btn-secondary" :disabled="data.current_page>=data.last_page" @click="load(data.current_page+1)">Next</button></div>
            </section>
            <template v-else>
                <section class="clinic-panel p-6" aria-label="Invoice details">
                    <RouterLink class="btn-secondary" :to="`/app/billing/invoices/${data.id}/print`" target="_blank">Print Invoice</RouterLink>
                    <p>{{ customerLabel }}</p><h2 class="text-lg font-semibold">{{ data.customer.name }}</h2>
                    <p>Branch: {{ data.branch?.name }}</p><p>{{ data.status }}</p><p v-if="data.issued_at">Issued: {{ data.issued_at }}</p>
                    <div class="overflow-x-auto"><table class="w-full mt-4">
                        <thead><tr><th>Description</th><th>Quantity</th><th>Unit price</th><th>Amount</th><th v-if="data.snapshot_version>=2">Discount</th><th v-if="data.snapshot_version>=2">Tax</th><th v-if="data.snapshot_version>=2">Line total</th></tr></thead>
                        <tbody><tr v-for="item in data.items" :key="item.id"><td>{{ item.description }}</td><td>{{ item.quantity }}</td><td>{{ money(item.unit_price) }}</td><td>{{ money(item.amount) }}</td>
                            <td v-if="data.snapshot_version>=2">{{ money(item.discount_amount) }}</td><td v-if="data.snapshot_version>=2">{{ money(item.tax_amount) }}</td><td v-if="data.snapshot_version>=2">{{ money(item.line_total) }}</td>
                        </tr></tbody>
                    </table></div>
                    <p class="mt-4">Subtotal: {{ data.currency }} {{ money(data.subtotal) }}</p><p>Discount: {{ money(data.discount) }} · Tax: {{ money(data.tax) }}</p>
                    <p class="font-semibold">Total: {{ data.currency }} {{ money(data.total) }}</p><p>Paid: {{ money(data.paid) }} · Balance: {{ money(data.balance) }}</p>
                </section>
                <form v-if="context.can('billing.payments') && ['unpaid','partial'].includes(data.status)" class="clinic-panel p-6 mt-4" @submit.prevent="pay">
                    <h2 class="font-semibold">Record Payment</h2><p class="hint">Record a payment already received. This does not charge a card or contact a payment gateway.</p>
                    <p>Total: {{ data.currency }} {{ money(data.total) }} · Paid: {{ money(data.paid) }} · Balance: {{ money(data.balance) }}</p>
                    <div class="form-grid mt-4">
                        <label class="field">Amount<input v-model="amount" type="number" min="0.01" :max="data.balance" step="0.01" required :disabled="busy"></label>
                        <label class="field">Payment Method<select v-model="method" aria-label="Payment Method" :disabled="busy"><option v-for="m in methods" :key="m" :value="m">{{ m.replaceAll('_',' ') }}</option></select></label>
                        <label class="field">Reference<input v-model="reference" maxlength="255" :disabled="busy"></label>
                    </div><button class="btn mt-4" :disabled="busy">{{ busy ? 'Recording…' : 'Record Payment' }}</button>
                </form>
                <section class="clinic-panel p-6 mt-4" aria-label="Payment history"><h2 class="font-semibold">Payments</h2>
                    <p v-for="p in data.payments" :key="p.id" class="mt-2">{{ data.currency }} {{ money(p.amount) }} · {{ p.method.replaceAll('_',' ') }} · {{ p.paid_at }} · {{ p.reference }} · {{ p.recorded_by_name || 'Unknown recorder' }} · <RouterLink v-if="p.receipt" class="text-emerald-700" :to="`/app/billing/payments/${p.id}/receipt`" target="_blank">{{ p.receipt.number }} · View Receipt</RouterLink><span v-else>Receipt unavailable (historical payment)</span></p>
                    <p v-if="!data.payments.length">No payments recorded.</p>
                </section>
            </template>
        </template>
        <p v-else-if="loading" role="status">Loading billing…</p>
    </div>
</template>
<style scoped>th,td{text-align:left;padding:.8rem;border-bottom:1px solid #e2e8f0}</style>
