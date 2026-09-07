<script setup>
import { reactive, ref, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { usePatientStore } from '../../stores/patients';
import { useClinicContextStore } from '../../stores/clinicContext';
import AppIcon from '../../components/ui/AppIcon.vue';
import FormErrors from '../../components/ui/FormErrors.vue';
import PatientTable from '../../components/patients/PatientTable.vue';
const store = usePatientStore(), context = useClinicContextStore(), route = useRoute(), router = useRouter();
const defaults = { search: '', gender: '', status: 'active', from: '', to: '', blood_group: '', sort: 'registered_at', direction: 'desc', per_page: '25', page: '1' };
const filters = reactive(Object.fromEntries(Object.entries(defaults).map(([key, value]) => [key, route.query[key] || value])));
const more = ref(Boolean(filters.blood_group || filters.from || filters.to));
let timer;
function load() { store.list(filters); }
function apply(page = 1) { filters.page = String(page); router.replace({ query: Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== '')) }); }
function search() { clearTimeout(timer); timer = setTimeout(() => apply(), 350); }
function reset() { Object.assign(filters, defaults); apply(); }
onMounted(load); onUnmounted(() => clearTimeout(timer));
</script>
<template>
    <div class="patient-page-header"><div><p class="patient-breadcrumb"><RouterLink to="/app/dashboard">Dashboard</RouterLink><span>/</span>Patients</p><h1>Patients</h1><p>Manage patient records and healthcare information.</p></div><RouterLink v-if="context.can('patients.create')" to="/app/patients/create" class="btn">＋ Add Patient</RouterLink></div>
    <div class="clinic-kpis"><section v-for="card in [['Total Patients','total','members','mint'],['New This Month','month','patient','blue'],['New Today','today','calendar','violet'],['Archived Patients','archived','storage','patient-rose']]" :key="card[1]" class="clinic-panel clinic-kpi"><span class="clinic-kpi-icon" :class="card[3]"><AppIcon :name="card[2]" :size="28" /></span><div><h2>{{ card[0] }}</h2><strong>{{ store.stats?.[card[1]] ?? '—' }}</strong><p>Across your clinic</p></div></section></div>
    <form class="clinic-panel patient-filters" @submit.prevent="apply()"><input v-model="filters.search" type="search" placeholder="Search by name, ID, phone or email..." aria-label="Search patients" class="filter-input" @input="search"><select v-model="filters.gender" aria-label="Gender filter" class="compact-select" @change="apply()"><option value="">All genders</option><option v-for="value in ['male','female','other','unknown']" :key="value">{{ value }}</option></select><select v-model="filters.status" aria-label="Status filter" class="compact-select" @change="apply()"><option v-for="value in ['active','inactive','archived','all']" :key="value">{{ value }}</option></select><button class="btn-secondary" type="button" :aria-expanded="more" @click="more = !more">More Filters</button><button class="btn-secondary" type="button" @click="reset">Reset</button><div v-if="more" class="patient-filter-extra"><label class="field">Registered from<input v-model="filters.from" type="date" @change="apply()"></label><label class="field">Registered to<input v-model="filters.to" type="date" @change="apply()"></label><label class="field">Blood group<select v-model="filters.blood_group" @change="apply()"><option value="">All blood groups</option><option v-for="value in ['A+','A-','B+','B-','AB+','AB-','O+','O-']" :key="value">{{ value }}</option></select></label></div></form>
    <FormErrors :error="store.error" />
    <section class="clinic-panel patient-list-panel"><div class="patient-list-toolbar"><span>{{ store.meta?.total ?? 0 }} matching patients</span><label>Sort by <select v-model="filters.sort" class="compact-select" @change="apply()"><option value="registered_at">Registration date</option><option value="name">Patient name</option><option value="patient_number">Patient number</option><option value="status">Status</option></select></label><button class="btn-secondary" @click="filters.direction = filters.direction === 'asc' ? 'desc' : 'asc'; apply()">{{ filters.direction === 'asc' ? 'Ascending ↑' : 'Descending ↓' }}</button></div>
        <div v-if="store.busy" class="p-6 space-y-3" role="status" aria-label="Loading patients"><div v-for="i in 5" :key="i" class="h-12 animate-pulse rounded-lg bg-slate-100"></div></div>
        <div v-else-if="store.error" class="empty-state"><p>Unable to load patients.</p><button class="btn mt-4" @click="load">Try again</button></div>
        <PatientTable v-else-if="store.rows.length" :patients="store.rows" @changed="load" />
        <div v-else class="clinic-empty"><AppIcon name="patient" :size="36" /><strong>{{ store.stats?.total ? 'No patients match your search.' : 'No patients registered yet.' }}</strong><RouterLink v-if="!store.stats?.total && context.can('patients.create')" class="btn" to="/app/patients/create">Register First Patient</RouterLink></div>
        <div v-if="store.meta && !store.busy" class="patient-pagination"><span>Showing {{ store.meta.from || 0 }} to {{ store.meta.to || 0 }} of {{ store.meta.total }} patients</span><label>Per page <select v-model="filters.per_page" class="compact-select" @change="apply()"><option>25</option><option>50</option><option>100</option></select></label><button class="btn-secondary" :disabled="store.meta.current_page <= 1" @click="apply(store.meta.current_page - 1)">Previous</button><span>{{ store.meta.current_page }} / {{ store.meta.last_page }}</span><button class="btn-secondary" :disabled="store.meta.current_page >= store.meta.last_page" @click="apply(store.meta.current_page + 1)">Next</button></div>
    </section>
</template>
