<script setup>
import { computed, onMounted } from 'vue';
import { useClinicContextStore } from '../../stores/clinicContext';
import { useClinicDashboardStore } from '../../stores/clinicDashboard';
import AppIcon from '../../components/ui/AppIcon.vue';
import FormErrors from '../../components/ui/FormErrors.vue';
import AppointmentCalendar from '../../components/clinic/AppointmentCalendar.vue';
const context = useClinicContextStore();
const dashboard = useClinicDashboardStore();
onMounted(() => dashboard.load());
const date = computed(() => new Date(`${context.data.today}T12:00:00`).toLocaleDateString(undefined, { weekday: 'long', month: 'short', day: 'numeric', year: 'numeric' }));
const stats = computed(() => [
    ['Total Patients', dashboard.data?.stats.total_patients, 'members', 'mint', 'No patients registered yet', 'patients'],
    ["Today’s Appointments", dashboard.data?.stats.today_appointments, 'calendar', 'blue', 'No appointments today', 'appointments'],
    ['Active Doctors', dashboard.data?.stats.active_doctors, 'doctor', 'blue', 'In this branch', 'doctors'],
    ['Monthly Revenue', new Intl.NumberFormat(undefined, { style: 'currency', currency: context.data.plan?.currency || 'USD' }).format(dashboard.data?.stats.monthly_revenue || 0), 'revenue', 'mint', 'No revenue data available', 'billing'],
]);
const actions = computed(() => [
    ['Add Patient', 'Register a new patient', 'patient', 'mint', 'patients', 'patients.create', '/app/patients/create'],
    ['Book Appointment', 'Schedule a visit', 'calendar', 'blue', 'appointments', 'appointments.create', '/app/appointments/create'],
    ['Create Prescription', 'Prescribe medication', 'audit', 'violet', 'prescriptions', 'prescriptions.create', '/app/prescriptions/create'],
    ['New Invoice', 'Create a patient invoice', 'revenue', 'amber', 'billing', 'billing.create', '/app/billing/invoices/create'],
].filter(action => context.allowed(action[4]) && context.can(action[5])));
</script>
<template>
    <div class="clinic-dashboard-header"><div><p>Welcome back,</p><h1>{{ context.data.clinic.name }}</h1><span>Here’s what’s happening at your clinic today.</span></div><div class="clinic-header-actions"><time>{{ date }}</time><RouterLink v-if="context.allowed('appointments') && context.can('appointments.create')" class="btn" to="/app/appointments/create">＋ New Appointment</RouterLink></div></div>
    <FormErrors :error="dashboard.error" />
    <div v-if="dashboard.error" class="clinic-panel" role="alert"><h2>Unable to load dashboard</h2><p class="my-3">Please try again to load current clinic information.</p><button class="btn" @click="dashboard.load()">Try again</button></div>
    <div v-else-if="dashboard.busy || !dashboard.data" class="clinic-skeleton-grid" role="status" aria-label="Loading dashboard"><div v-for="n in 7" :key="n" class="clinic-skeleton"></div></div>
    <template v-else>
        <div class="clinic-kpis"><section v-for="stat in stats" :key="stat[0]" class="clinic-panel clinic-kpi"><span class="clinic-kpi-icon" :class="stat[3]"><AppIcon :name="stat[2]" :size="29" /></span><div><h2>{{ stat[0] }}</h2><strong>{{ context.allowed(stat[5]) ? stat[1] : '—' }}</strong><p>{{ context.allowed(stat[5]) ? stat[4] : 'Not available with your access' }}</p></div></section></div>
        <div class="clinic-dashboard-middle">
            <section class="clinic-panel"><div class="clinic-card-heading"><h2>Today’s Appointments</h2><RouterLink v-if="context.allowed('appointments')" to="/app/appointments">View all</RouterLink></div><div class="clinic-empty"><span class="clinic-empty-icon blue"><AppIcon name="calendar" :size="30" /></span><strong>{{ context.allowed('appointments') ? 'No appointments scheduled for today.' : 'Appointments are unavailable with your access.' }}</strong><p>Your daily schedule will appear here.</p></div></section>
            <section class="clinic-panel"><div class="clinic-card-heading"><h2>Recent Patients</h2><RouterLink v-if="context.allowed('patients')" to="/app/patients">View all</RouterLink></div><div class="clinic-empty"><span class="clinic-empty-icon mint"><AppIcon name="patient" :size="30" /></span><strong>{{ context.allowed('patients') ? 'No patients have been registered yet.' : 'Patients are unavailable with your access.' }}</strong><p>Recent registrations will appear here.</p></div></section>
            <section class="clinic-panel"><div class="clinic-card-heading"><h2>Clinic Information</h2><RouterLink v-if="context.allowed('settings')" to="/app/settings">Manage</RouterLink></div><ul class="clinic-info"><li><AppIcon name="clinics" /><span>{{ context.data.clinic.name }} <span class="badge badge-green">{{ context.data.clinic.status }}</span></span></li><li><AppIcon name="branch" /><span>{{ context.data.branch.name }}<small>{{ context.data.clinic.timezone }}</small></span></li><li><AppIcon name="doctor" /><span>{{ dashboard.data.stats.active_doctors }} doctors · {{ dashboard.data.staff_count }} staff</span></li><li><AppIcon name="calendar" /><span>Member since {{ new Date(context.data.clinic.created_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) }}</span></li><li><AppIcon name="roles" /><span>{{ context.data.plan?.name || 'No plan' }}<small class="capitalize">{{ context.data.subscription.status }} subscription</small></span></li></ul></section>
        </div>
        <div class="clinic-dashboard-bottom"><section class="clinic-panel"><div class="clinic-card-heading"><h2>Quick Actions</h2></div><div v-if="actions.length" class="clinic-quick-actions"><RouterLink v-for="action in actions" :key="action[0]" :to="action[6]" :class="action[3]"><AppIcon :name="action[2]" :size="27" /><strong>{{ action[0] }}</strong><small>{{ action[1] }}</small></RouterLink></div><p v-else class="clinic-empty">No quick actions are available with your permissions.</p></section>
            <section class="clinic-panel"><div class="clinic-card-heading"><h2>Patients by Visit Type</h2></div><div class="clinic-empty"><div class="clinic-empty-donut"><strong>0<small>Visits</small></strong></div><p>No patient visit data available.</p></div></section>
            <AppointmentCalendar :today="context.data.today" :dates="dashboard.data.calendar" :enabled="context.allowed('appointments')" />
        </div>
    </template>
</template>
