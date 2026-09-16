<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { useClinicContextStore } from '../../stores/clinicContext';
import api from '../../services/api';
import FormErrors from '../ui/FormErrors.vue';

const router = useRouter(), auth = useAuthStore(), context = useClinicContextStore();
const data = ref(null), error = ref(null), busy = ref(false);
let generation = 0, controller;
function reset() {
    ++generation; controller?.abort(); controller = new AbortController();
    data.value = null; error.value = null; busy.value = false;
}
async function load(page = 1) {
    reset();
    if (context.data?.clinic.id !== auth.user?.active_tenant_id) return;
    const token = generation;
    busy.value = true;
    try {
        const response = await api.get('/v1/salon/billing/sources', { params: { page }, headers: context.headers(), signal: controller.signal });
        if (token === generation) data.value = response.data;
    } catch (e) { if (token === generation && e.code !== 'ERR_CANCELED') error.value = e; }
    finally { if (token === generation) busy.value = false; }
}
async function issue(row) {
    if (busy.value) return;
    const token = generation;
    busy.value = true; error.value = null;
    try {
        const response = await api.post('/v1/salon/appointments/' + row.id + '/invoice', {}, { headers: context.headers(), signal: controller.signal });
        if (token === generation) await router.push('/app/billing/invoices/' + response.data.data.id);
    } catch (e) { if (token === generation && e.code !== 'ERR_CANCELED') error.value = e; }
    finally { if (token === generation) busy.value = false; }
}
watch(() => [auth.user?.active_tenant_id, context.data?.clinic.id, context.data?.branch?.id], () => load(), { immediate: true, flush: 'sync' });
onBeforeUnmount(reset);
</script>
<template>
    <section class="clinic-panel p-4 mb-4" aria-label="Completed appointments awaiting invoices">
        <h2 class="font-semibold">Completed appointments awaiting invoices</h2>
        <FormErrors :error="error" />
        <p v-if="busy" role="status">Loading…</p>
        <template v-if="data">
            <ul><li v-for="row in data.data" :key="row.id" class="flex flex-wrap items-center gap-3 py-3">
                <span>{{ row.appointment_number }} · {{ row.customer.name }} · {{ row.branch.name }} · {{ row.currency }} {{ row.total }}</span>
                <button class="btn-secondary" :disabled="busy" @click="issue(row)">Create Invoice</button>
            </li></ul>
            <p v-if="!data.data.length">No completed appointments awaiting invoices.</p>
            <div v-if="data.meta.last_page>1" class="flex gap-3 mt-3">
                <button class="btn-secondary" :disabled="busy||data.meta.current_page<=1" @click="load(data.meta.current_page-1)">Previous appointments</button>
                <span>{{ data.meta.current_page }} / {{ data.meta.last_page }}</span>
                <button class="btn-secondary" :disabled="busy||data.meta.current_page>=data.meta.last_page" @click="load(data.meta.current_page+1)">Next appointments</button>
            </div>
        </template>
    </section>
</template>
