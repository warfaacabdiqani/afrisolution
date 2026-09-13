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
const stats = computed(() => {
    const profile = context.businessProfile?.dashboard_profile?.length ? context.businessProfile.dashboard_profile : [
        { key: 'total_patients', label: 'Total Patients' },
        { key: 'today_appointments', label: 'Today\'s Appointments' },
        { key: 'active_doctors', label: 'Active Doctors' },
        { key: 'monthly_revenue', label: 'Monthly Revenue' },
    ];
    const currency = context.data?.plan?.currency || 'USD';
    const formatMoney = (value) => new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(value || 0);
    return profile.map((item) => {
        const itemKey = item.key;
        const common = {
            key: itemKey,
            label: item.label,
            icon: 'members',
            tone: 'mint',
            description: 'Current summary',
        };
        const map = {
            total_patients: { value: dashboard.data?.stats.total_patients ?? 0, icon: 'members', tone: 'mint', description: 'Active patients across your workspace', module: 'patients' },
            today_appointments: { value: dashboard.data?.stats.today_appointments ?? 0, icon: 'calendar', tone: 'blue', description: 'Appointments in this branch today', module: 'appointments' },
            active_doctors: { value: dashboard.data?.stats.active_doctors ?? 0, icon: 'doctor', tone: 'blue', description: 'Clinicians active in this branch', module: 'doctors' },
            monthly_revenue: { value: formatMoney(dashboard.data?.stats.monthly_revenue ?? 0), icon: 'revenue', tone: 'mint', description: 'No revenue data available', module: 'billing' },
            staff_count: { value: dashboard.data?.staff_count ?? 0, icon: 'members', tone: 'mint', description: 'Team members assigned to this workspace', module: 'staff' },
            branch_count: { value: context.data?.branches?.length ?? 0, icon: 'branch', tone: 'blue', description: 'Authorized branches in this workspace', module: 'settings' },
            subscription: { value: context.data?.subscription?.status ? context.data.subscription.status.replaceAll('_', ' ') : 'Unavailable', icon: 'roles', tone: 'amber', description: context.data?.plan?.name || 'Review your subscription status', module: 'settings' },
            business_name: { value: context.data?.clinic?.name || 'Workspace', icon: 'clinics', tone: 'violet', description: context.businessProfile?.subtitle || 'Business profile', module: 'settings' },
        };
        const resolved = map[itemKey] || { value: '—', icon: common.icon, tone: common.tone, description: common.description, module: 'settings' };
        return { ...resolved, label: item.label, key: itemKey };
    });
});
const actions = computed(() => [
    ['Add Patient', 'Register a new patient', 'patient', 'mint', 'patients', 'patients.create', '/app/patients/create'],
    ['Book Appointment', 'Schedule a visit', 'calendar', 'blue', 'appointments', 'appointments.create', '/app/appointments/create'],
    ['Create Prescription', 'Prescribe medication', 'audit', 'violet', 'prescriptions', 'prescriptions.create', '/app/prescriptions/create'],
    ['New Invoice', 'Create a patient invoice', 'revenue', 'amber', 'billing', 'billing.create', '/app/billing/invoices/create'],
].filter(action => context.allowed(action[4]) && context.can(action[5])));
</script>
<template>
    <div class="clinic-dashboard-header"><div><p>Welcome back,</p><h1>{{ context.data.clinic.name }}</h1><span>{{ context.businessProfile?.subtitle ? `Here’s what’s happening at your ${context.businessProfile.subtitle.toLowerCase()} workspace today.` : 'Here’s what’s happening at your clinic today.' }}</span></div><div class="clinic-header-actions"><time>{{ date }}</time><RouterLink v-if="context.allowed('appointments') && context.can('appointments.create')" class="btn" to="/app/appointments/create">＋ New Appointment</RouterLink></div></div>
    <FormErrors :error="dashboard.error" />
    <div v-if="dashboard.error" class="clinic-panel" role="alert"><h2>Unable to load dashboard</h2><p class="my-3">Please try again to load current clinic information.</p><button class="btn" @click="dashboard.load()">Try again</button></div>
    <div v-else-if="dashboard.busy || !dashboard.data" class="clinic-skeleton-grid" role="status" aria-label="Loading dashboard"><div v-for="n in 7" :key="n" class="clinic-skeleton"></div></div>
    <template v-else>
        <div class="clinic-kpis"><section v-for="stat in stats" :key="stat.key" class="clinic-panel clinic-kpi"><span class="clinic-kpi-icon" :class="stat.tone"><AppIcon :name="stat.icon" :size="29" /></span><div><h2>{{ stat.label }}</h2><strong>{{ context.allowed(stat.module) ? stat.value : '—' }}</strong><p>{{ context.allowed(stat.module) ? stat.description : 'Not available with your access' }}</p></div></section></div>
        <div class="clinic-dashboard-middle">
            <section class="clinic-panel"><div class="clinic-card-heading"><h2>Today’s Appointments</h2><RouterLink v-if="context.allowed('appointments')" to="/app/appointments">View all</RouterLink></div><div v-if="dashboard.data.today_appointments.length"><RouterLink v-for="a in dashboard.data.today_appointments" :key="a.id" :to="`/app/appointments/${a.id}`" class="patient-document-row"><time class="text-xs text-slate-500">{{a.starts_at.slice(11,16)}}</time><span><strong>{{a.patient.full_name}}</strong><small class="block mt-2 text-slate-500">{{a.doctor.full_name}} · {{a.type || 'Appointment'}}</small></span><small class="capitalize text-teal-700">{{a.status.replaceAll('_',' ')}}</small></RouterLink></div><div v-else class="clinic-empty"><span class="clinic-empty-icon blue"><AppIcon name="calendar" :size="30" /></span><strong>{{ context.allowed('appointments') ? 'No appointments scheduled for today.' : 'Appointments are unavailable with your access.' }}</strong><p>Your daily schedule will appear here.</p></div></section>
            <section class="clinic-panel"><div class="clinic-card-heading"><h2>Recent Patients</h2><RouterLink v-if="context.allowed('patients')" to="/app/patients">View all</RouterLink></div><div v-if="dashboard.data.recent_patients.length"><RouterLink v-for="patient in dashboard.data.recent_patients" :key="patient.id" :to="`/app/patients/${patient.id}`" class="patient-document-row"><span><strong>{{ patient.full_name }}</strong><small class="mt-2 block capitalize text-slate-500">{{ patient.gender }} · {{ patient.age === null ? 'Age unknown' : `${patient.age} years` }}</small></span><small class="text-slate-500">{{ new Date(patient.registered_at).toLocaleDateString() }}</small></RouterLink></div><div v-else class="clinic-empty"><span class="clinic-empty-icon mint"><AppIcon name="patient" :size="30" /></span><strong>{{ context.allowed('patients') ? 'No patients have been registered yet.' : 'Patients are unavailable with your access.' }}</strong><p>Recent registrations will appear here.</p></div></section>
            <section class="clinic-panel"><div class="clinic-card-heading"><h2>Clinic Information</h2><RouterLink v-if="context.allowed('settings')" to="/app/settings">Manage</RouterLink></div><ul class="clinic-info"><li><AppIcon name="clinics" /><span>{{ context.data.clinic.name }} <span class="badge badge-green">{{ context.data.clinic.status }}</span></span></li><li><AppIcon name="branch" /><span>{{ context.data.branch.name }}<small>{{ context.data.clinic.timezone }}</small></span></li><li><AppIcon name="doctor" /><span>{{ dashboard.data.stats.active_doctors }} doctors · {{ dashboard.data.staff_count }} staff</span></li><li><AppIcon name="calendar" /><span>Member since {{ new Date(context.data.clinic.created_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) }}</span></li><li><AppIcon name="roles" /><span>{{ context.data.plan?.name || 'No plan' }}<small class="capitalize">{{ context.data.subscription.status }} subscription</small></span></li></ul></section>
        </div>
        <div class="clinic-dashboard-bottom"><section class="clinic-panel"><div class="clinic-card-heading"><h2>Quick Actions</h2></div><div v-if="actions.length" class="clinic-quick-actions"><RouterLink v-for="action in actions" :key="action[0]" :to="action[6]" :class="action[3]"><AppIcon :name="action[2]" :size="27" /><strong>{{ action[0] }}</strong><small>{{ action[1] }}</small></RouterLink></div><p v-else class="clinic-empty">No quick actions are available with your permissions.</p></section>
            <section class="clinic-panel"><div class="clinic-card-heading"><h2>Patients by Visit Type</h2></div><div class="clinic-empty"><div class="clinic-empty-donut"><strong>0<small>Visits</small></strong></div><p>No patient visit data available.</p></div></section>
            <AppointmentCalendar :today="context.data.today" :dates="dashboard.data.calendar" :enabled="context.allowed('appointments')" />
        </div>
    </template>
</template>
