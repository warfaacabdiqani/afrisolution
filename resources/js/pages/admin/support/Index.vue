<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { platformSupportService as service } from '../../../services/platformSupport';
import FormErrors from '../../../components/ui/FormErrors.vue';
import SupportTicketStats from '../../../components/admin/support/SupportTicketStats.vue';
import SupportTicketFilters from '../../../components/admin/support/SupportTicketFilters.vue';
import SupportTicketTable from '../../../components/admin/support/SupportTicketTable.vue';

const emptyFilters = () => ({ search: '', tenant_id: '', business_type_id: '', status: '', priority: '', category: '', date_from: '', date_to: '' });
const filters = ref(emptyFilters()), tickets = ref([]), stats = ref(null), options = ref({}), error = ref(null), loading = ref(true);
const page = ref(1), meta = ref({ last_page: 1, total: 0 });
const filtered = computed(() => Object.values(filters.value).some(Boolean));
let timer, sequence = 0;
async function load() {
    const request = ++sequence;
    loading.value = true; error.value = null;
    try {
        const [list, counts] = await Promise.all([service.list({ ...filters.value, page: page.value }), service.stats()]);
        if (request !== sequence) return;
        tickets.value = list.data.data; meta.value = list.data.meta; stats.value = counts.data.data;
    } catch (e) { if (request === sequence) { error.value = e; tickets.value = []; } }
    finally { if (request === sequence) loading.value = false; }
}
watch(filters, () => { page.value = 1; ++sequence; loading.value = true; clearTimeout(timer); timer = setTimeout(load, 300); });
function navigate(value) { clearTimeout(timer); page.value = value; load(); }
function refresh() { clearTimeout(timer); load(); }
onMounted(async () => {
    try { options.value = (await service.options()).data.data; await load(); }
    catch (e) { error.value = e; loading.value = false; }
});
onUnmounted(() => { clearTimeout(timer); ++sequence; });
</script>
<template>
    <div class="space-y-6">
        <header class="page-header"><div><p class="eyebrow">Platform administration / Support Tickets</p><h1>Support Tickets</h1><p>Manage support requests from all businesses on the platform.</p></div><button class="btn-secondary" :disabled="loading" @click="refresh">Refresh</button></header>
        <FormErrors :error="error" /><SupportTicketStats :stats="stats" />
        <SupportTicketFilters v-model="filters" :options="options" @reset="filters = emptyFilters()" />
        <SupportTicketTable :tickets="tickets" :loading="loading" :filtered="filtered" />
        <nav v-if="!error" class="flex flex-wrap items-center justify-between gap-3 text-sm text-slate-600" aria-label="Ticket pagination"><p>{{ meta.total }} tickets · Page {{ page }} of {{ meta.last_page }}</p><div class="flex gap-2"><button class="btn-secondary" :disabled="loading || page <= 1" @click="navigate(page - 1)">Previous</button><button class="btn-secondary" :disabled="loading || page >= meta.last_page" @click="navigate(page + 1)">Next</button></div></nav>
    </div>
</template>
