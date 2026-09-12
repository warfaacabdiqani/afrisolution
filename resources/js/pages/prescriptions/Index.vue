<script setup>
import { reactive, ref, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { usePrescriptionStore } from '../../stores/prescriptions';
import { useClinicContextStore } from '../../stores/clinicContext';
import AppIcon from '../../components/ui/AppIcon.vue';
import FormErrors from '../../components/ui/FormErrors.vue';
import PatientSelector from '../../components/prescriptions/PatientSelector.vue';
import PrescriptionTable from '../../components/prescriptions/PrescriptionTable.vue';
import '../../../css/prescriptions.css';
const store = usePrescriptionStore(), context = useClinicContextStore();
store.initialize(`${context.data.clinic.id}:${context.data.branch.id}`);
const defaults = () => ({ search: '', status: '', doctor_id: '', branch_id: context.data.branch.id, from: '', to: '', medication: '', patient_id: '', page: 1, per_page: 20 });
const filters = reactive(defaults()), more = ref(false), dates = ref(false), mobileFilters = ref(false), optionsError = ref(null);
const filterForm = ref(null);
watch(mobileFilters, async open => { await nextTick(); if (open) filterForm.value?.querySelector('input')?.focus(); else document.querySelector('.rx-mobile-search button')?.focus(); });
function trapFilters(event) { if (!mobileFilters.value) return; const elements = [...event.currentTarget.querySelectorAll('input,select,button,textarea,a')].filter(el => el.getClientRects().length && !el.disabled); const first = elements[0], last = elements.at(-1); if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); } else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); } }
let timer;
const load = () => store.list(filters);
function apply() { filters.page = 1; load(); }
function search() { clearTimeout(timer); timer = setTimeout(apply, 300); }
function reset() { Object.assign(filters, defaults()); apply(); }
async function options() { try { await store.loadOptions(filters.branch_id || undefined); } catch(e) { optionsError.value = e; } }
function changed(message) { store.notice = message; load(); }
watch(() => filters.branch_id, () => { filters.doctor_id = ''; options(); });
onMounted(() => { options(); load(); }); onUnmounted(() => clearTimeout(timer));
</script>
<template>
    <div class="patient-page-header"><div><p class="patient-breadcrumb"><RouterLink to="/app/dashboard">Dashboard</RouterLink><span>/</span>Prescriptions</p><h1>Prescriptions</h1><p>Create, manage and track patient prescriptions.</p></div><RouterLink v-if="context.can('prescriptions.create')" to="/app/prescriptions/create" class="btn">＋ New Prescription</RouterLink></div>
    <p v-if="store.notice" class="rx-notice" role="status">{{ store.notice }} <button @click="store.notice = ''" aria-label="Dismiss notification">×</button></p>
    <div class="clinic-kpis rx-kpis"><section v-for="card in [['Total Prescriptions','total','audit','mint','In selected branch'],['Today','today','calendar','blue','Prescribed today'],['Active Prescriptions','active','activity','violet','Currently active'],['Pending / Undispensed','pending','storage','patient-rose','Awaiting dispensing']]" :key="card[1]" class="clinic-panel clinic-kpi"><span class="clinic-kpi-icon" :class="card[3]"><AppIcon :name="card[2]" :size="28" /></span><div><h2>{{ card[0] }}</h2><strong>{{ store.stats?.[card[1]] ?? '—' }}</strong><p>{{ card[4] }}</p></div></section></div>
    <div class="rx-mobile-search"><input v-model="filters.search" class="filter-input" type="search" placeholder="Search prescriptions..." aria-label="Search prescriptions on mobile" @input="search"><button class="btn-secondary" :aria-expanded="mobileFilters" @click="mobileFilters = true">Filters</button></div>
    <div v-if="mobileFilters" class="rx-filter-overlay" @click="mobileFilters = false"></div>
    <form ref="filterForm" @keydown.tab="trapFilters" class="clinic-panel patient-filters rx-filters" :class="{ 'rx-filter-drawer': mobileFilters }" :role="mobileFilters ? 'dialog' : undefined" :aria-modal="mobileFilters || undefined" aria-label="Prescription filters" @keydown.esc="mobileFilters = false" @submit.prevent="apply"><input v-model="filters.search" class="filter-input" type="search" placeholder="Search by patient name, prescription ID, medication..." aria-label="Search prescriptions" @input="search"><select v-model="filters.status" class="compact-select" aria-label="Status" @change="apply"><option value="">Status</option><option v-for="(label, value) in store.options?.statuses" :key="value" :value="value">{{ label }}</option></select><select v-model="filters.doctor_id" class="compact-select" aria-label="Prescriber" @change="apply"><option value="">Prescriber</option><option v-for="d in store.options?.doctors" :key="d.id" :value="d.id">{{ d.full_name }}</option></select><button type="button" class="btn-secondary" :aria-expanded="dates" @click="dates = !dates">Date Range</button><button type="button" class="btn-secondary" :aria-expanded="more" @click="more = !more">More Filters</button><button type="button" class="btn-secondary" @click="reset">Reset</button>
        <div v-if="dates" class="patient-filter-extra"><label class="field">Date from<input v-model="filters.from" type="date" @change="apply"></label><label class="field">Date to<input v-model="filters.to" type="date" :min="filters.from" @change="apply"></label></div>
        <div v-if="more" class="patient-filter-extra"><label class="field">Branch<select v-model="filters.branch_id" @change="apply"><option value="">All accessible branches</option><option v-for="b in context.data.branches" :key="b.id" :value="b.id">{{ b.name }}</option></select></label><label class="field">Medication<input v-model="filters.medication" @input="search"></label><PatientSelector v-model="filters.patient_id" @update:model-value="apply" /></div>
        <button class="btn rx-mobile-filter-close" type="button" @click="mobileFilters = false">Show results</button>
    </form>
    <FormErrors :error="optionsError || store.error" />
    <section class="clinic-panel patient-list-panel rx-list"><div v-if="store.busy" class="p-6 space-y-3" role="status" aria-label="Loading prescriptions"><div v-for="i in 6" :key="i" class="h-12 animate-pulse rounded-lg bg-slate-100"></div></div><div v-else-if="store.error" class="clinic-empty"><p>Unable to load prescriptions.</p><button class="btn-secondary" @click="load">Try again</button></div><PrescriptionTable v-else-if="store.rows.length" :rows="store.rows" :labels="store.options?.statuses" @changed="changed" /><div v-else class="clinic-empty"><AppIcon name="audit" :size="36" /><strong>{{ store.stats?.total ? 'No prescriptions match your search.' : 'No prescriptions have been created yet.' }}</strong><RouterLink v-if="!store.stats?.total && context.can('prescriptions.create')" class="btn" to="/app/prescriptions/create">Create First Prescription</RouterLink></div>
        <div v-if="store.meta && !store.busy" class="patient-pagination"><span>Showing {{ store.meta.from || 0 }} to {{ store.meta.to || 0 }} of {{ store.meta.total }} prescriptions</span><button class="btn-secondary" :disabled="store.meta.current_page <= 1" @click="filters.page--; load()">Previous</button><span>{{ store.meta.current_page }} / {{ store.meta.last_page }}</span><button class="btn-secondary" :disabled="store.meta.current_page >= store.meta.last_page" @click="filters.page++; load()">Next</button></div>
    </section>
    <aside v-if="context.allowed('pharmacy')" class="rx-pharmacy"><AppIcon name="info" :size="26" /><div><strong>Prescriptions are linked to the Pharmacy module.</strong><p>Dispense medications, track stock and manage refills from the Pharmacy module.</p></div><RouterLink class="btn-secondary" to="/app/pharmacy">Go to Pharmacy</RouterLink></aside>
</template>
