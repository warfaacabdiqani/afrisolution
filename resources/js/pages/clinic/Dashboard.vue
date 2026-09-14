<script setup>
import { onMounted } from 'vue';
import { useClinicDashboardStore } from '../../stores/clinicDashboard';
import FormErrors from '../../components/ui/FormErrors.vue';
import AppointmentCalendar from '../../components/clinic/AppointmentCalendar.vue';
import DashboardHeader from '../../components/dashboard/DashboardHeader.vue';
import DashboardStatsGrid from '../../components/dashboard/DashboardStatsGrid.vue';
import DashboardSection from '../../components/dashboard/DashboardSection.vue';
import BusinessInformation from '../../components/dashboard/BusinessInformation.vue';
import QuickActions from '../../components/dashboard/QuickActions.vue';
const dashboard = useClinicDashboardStore();
onMounted(() => dashboard.load());
</script>
<template>
    <FormErrors :error="dashboard.error" />
    <div v-if="dashboard.error" class="clinic-panel" role="alert"><h2>Unable to load dashboard</h2><p class="my-3">Please try again to load current business information.</p><button class="btn" @click="dashboard.load">Try again</button></div>
    <div v-else-if="dashboard.busy || !dashboard.data" class="clinic-skeleton-grid" role="status" aria-label="Loading dashboard"><div v-for="n in 7" :key="n" class="clinic-skeleton"></div></div>
    <template v-else>
        <DashboardHeader :business="dashboard.business" :today="dashboard.data.today" />
        <DashboardStatsGrid :widgets="dashboard.widgets" />
        <div class="clinic-dashboard-middle">
            <DashboardSection v-if="dashboard.sections.appointments" :title="dashboard.sections.appointments.title">
                <template #action><RouterLink to="/app/appointments">View all</RouterLink></template>
                <template v-if="dashboard.sections.appointments.items.length"><RouterLink v-for="a in dashboard.sections.appointments.items" :key="a.id" :to="`/app/appointments/${a.id}`" class="patient-document-row"><time class="text-xs text-slate-500">{{ a.starts_at.slice(11,16) }}</time><span><strong>{{ a.patient.full_name }}</strong><small class="block mt-2 text-slate-500">{{ a.doctor.full_name }} � {{ a.type || 'Appointment' }}</small></span><small class="capitalize text-teal-700">{{ a.status.replaceAll('_', ' ') }}</small></RouterLink></template>
                <div v-else class="clinic-empty"><strong>No appointments scheduled for today.</strong><p>Your daily schedule will appear here.</p></div>
            </DashboardSection>
            <DashboardSection v-if="dashboard.sections.recent_customers" :title="dashboard.sections.recent_customers.title">
                <template #action><RouterLink to="/app/patients">View all</RouterLink></template>
                <template v-if="dashboard.sections.recent_customers.items.length"><RouterLink v-for="patient in dashboard.sections.recent_customers.items" :key="patient.id" :to="`/app/patients/${patient.id}`" class="patient-document-row"><span><strong>{{ patient.full_name }}</strong><small class="mt-2 block capitalize text-slate-500">{{ patient.gender }} � {{ patient.age === null ? 'Age unknown' : `${patient.age} years` }}</small></span><small class="text-slate-500">{{ new Date(patient.registered_at).toLocaleDateString() }}</small></RouterLink></template>
                <div v-else class="clinic-empty"><strong>No patients have been registered yet.</strong><p>Recent registrations will appear here.</p></div>
            </DashboardSection>
            <BusinessInformation v-if="dashboard.sections.business_information" :section="dashboard.sections.business_information" :business="dashboard.business" />
        </div>
        <div class="clinic-dashboard-bottom">
            <QuickActions :actions="dashboard.quickActions" />
            <AppointmentCalendar v-if="dashboard.sections.calendar" :today="dashboard.sections.calendar.today" :dates="dashboard.sections.calendar.dates" :enabled="true" />
        </div>
    </template>
</template>

<style scoped>
.clinic-dashboard-middle, .clinic-dashboard-bottom { grid-template-columns: repeat(auto-fit, minmax(min(100%, 300px), 1fr)); }
.clinic-dashboard-middle > :only-child { max-width: 760px; width: 100%; }
@media (max-width: 600px) { .clinic-dashboard-middle, .clinic-dashboard-bottom { grid-template-columns: 1fr; } }
</style>
