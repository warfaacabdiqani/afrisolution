<script setup>
import { onMounted, ref } from 'vue';
import { useClinicContextStore } from '../../../stores/clinicContext';
import { reportService } from '../../../services/reports';
import FormErrors from '../../../components/ui/FormErrors.vue';

const context = useClinicContextStore();
const busy = ref(false);
const error = ref(null);
const items = ref([]);

async function load() {
    busy.value = true;
    error.value = null;
    try {
        const { data } = await reportService.patients({ branch_id: context.data?.branch?.id });
        items.value = data.data.charts[2]?.items || [];
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
                    <RouterLink to="/app/reports/patients">Patients</RouterLink>
                    <span>/</span>
                    <span>Demographics</span>
                </p>
                <h1>Patient Demographics</h1>
            </div>
        </div>
        <FormErrors :error="error" />
        <section class="clinic-panel p-5">
            <div v-if="busy" class="p-4 text-slate-500">Loading demographics…</div>
            <div v-else class="grid gap-2">
                <div v-for="item in items" :key="item.label" class="flex justify-between rounded-lg bg-slate-50 px-3 py-2">
                    <span>{{ item.label }}</span>
                    <strong>{{ item.value }}</strong>
                </div>
            </div>
        </section>
    </div>
</template>
