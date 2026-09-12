<script setup>
import { onMounted, ref } from 'vue';
import { useClinicContextStore } from '../../../stores/clinicContext';
import { reportService } from '../../../services/reports';
import FormErrors from '../../../components/ui/FormErrors.vue';

const context = useClinicContextStore();
const busy = ref(false);
const error = ref(null);
const statuses = ref([]);

async function load() {
    busy.value = true;
    error.value = null;
    try {
        const { data } = await reportService.appointments({ branch_id: context.data?.branch?.id });
        statuses.value = data.data.charts[1]?.items || [];
    } catch (e) {
        error.value = e;
    } finally {
        busy.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div>
        <div class="patient-page-header">
            <div>
                <p class="patient-breadcrumb">
                    <RouterLink to="/app/dashboard">Dashboard</RouterLink>
                    <span>/</span>
                    <RouterLink to="/app/reports">Reports</RouterLink>
                    <span>/</span>
                    <RouterLink to="/app/reports/appointments">Appointments</RouterLink>
                    <span>/</span>
                    <span>Status Report</span>
                </p>
                <h1>Appointment Status Report</h1>
            </div>
        </div>
        <FormErrors :error="error" />
        <section class="clinic-panel p-5">
            <div v-if="busy" class="p-4 text-slate-500">Loading status data…</div>
            <div v-else class="grid gap-2">
                <div v-for="item in statuses" :key="item.label" class="flex justify-between rounded-lg bg-slate-50 px-3 py-2">
                    <span>{{ item.label }}</span>
                    <strong>{{ item.value }}</strong>
                </div>
            </div>
        </section>
    </div>
</template>
