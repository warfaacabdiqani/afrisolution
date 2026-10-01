<script setup>
import { ref, watch } from 'vue';
import { collectDeposit } from '../../services/deposits';
import FormErrors from '../ui/FormErrors.vue';
const props = defineProps({ type: String, sourceId: Number, deposit: Object, currency: String, canCollect: Boolean });
const emit = defineEmits(['collected']);
const amount = ref(props.deposit?.remaining || ''), method = ref('cash'), reference = ref(''), busy = ref(false), error = ref(null);
let idempotencyKey = crypto.randomUUID();
watch(() => props.deposit?.remaining, value => { amount.value = value || ''; });
async function collect() {
    if (busy.value) return;
    busy.value = true; error.value = null;
    try {
        await collectDeposit(props.type, props.sourceId, { amount: amount.value, method: method.value, reference: reference.value || null, idempotency_key: idempotencyKey });
        idempotencyKey = crypto.randomUUID(); reference.value = ''; emit('collected');
    } catch (e) { error.value = e; } finally { busy.value = false; }
}
</script>
<template>
  <section v-if="Number(deposit?.required || 0) > 0" class="deposit-panel">
    <div><h3>Deposit</h3><p>Required {{ currency }} {{ deposit.required }} · Collected {{ currency }} {{ deposit.collected }}</p><p v-if="Number(deposit.refunded)">Refunded {{ currency }} {{ deposit.refunded }}</p></div>
    <RouterLink v-if="deposit.invoice_id" class="text-emerald-700" :to="`/app/billing/invoices/${deposit.invoice_id}`">View deposit invoice</RouterLink>
    <FormErrors :error="error" />
    <form v-if="canCollect && Number(deposit.remaining) > 0" class="deposit-form" @submit.prevent="collect">
      <label class="field">Amount<input v-model="amount" type="number" min="0.01" :max="deposit.remaining" step="0.01" required></label>
      <label class="field">Method<select v-model="method"><option value="cash">Cash</option><option value="card">Card</option><option value="mobile_money">Mobile money</option><option value="bank_transfer">Bank transfer</option></select></label>
      <label class="field">Reference<input v-model="reference" maxlength="100"></label>
      <button class="btn" :disabled="busy">{{ busy ? 'Collecting…' : 'Collect Deposit' }}</button>
    </form>
  </section>
</template>
<style scoped>.deposit-panel{border:1px solid #cce8df;background:#f3fbf8;border-radius:12px;padding:16px;margin:16px 0}.deposit-panel h3{font-weight:700}.deposit-panel p{font-size:13px;color:#526b7c;margin-top:4px}.deposit-form{display:grid;grid-template-columns:repeat(3,minmax(110px,1fr)) auto;align-items:end;gap:10px;margin-top:14px}@media(max-width:650px){.deposit-form{grid-template-columns:1fr 1fr}.deposit-form .field:first-child{grid-column:1/-1}}</style>
