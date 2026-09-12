<script setup>
import { ref, onMounted } from 'vue';
import { useClinicContextStore } from '../../../stores/clinicContext';
import { reportService } from '../../../services/reports';
import FormErrors from '../../../components/ui/FormErrors.vue';

const context = useClinicContextStore();
const busy = ref(false);
const error = ref(null);
const data = ref({ metrics: [], charts: [], table: [] });

async function load() {
    busy.value = true;
    error.value = null;
    try {
        const { data: response } = await reportService.patients({
            branch_id: context.data?.branch?.id,
        });
        data.value = response.data;
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
                    <span>Patients</span>
                </p>
                <h1>Patient Reports</h1>
                <p>Track patient registrations, activity and demographics with real clinic data.</p>
            </div>
        </div>

        <FormErrors :error="error" />

        <div v-if="busy" class="clinic-skeleton-grid" role="status">
            <div v-for="n in 4" :key="n" class="clinic-skeleton"></div>
        </div>

        <div v-else class="space-y-6">
            <div class="clinic-kpis">
                <section v-for="metric in data.metrics" :key="metric.label" class="clinic-panel clinic-kpi">
                    <div>
                        <h2>{{ metric.label }}</h2>
                        <strong>{{ metric.value }}</strong>
                    </div>
                </section>
            </div>

            <section v-for="chart in data.charts" :key="chart.title" class="clinic-panel p-5">
                <h2 class="mb-4 text-lg font-semibold text-slate-800">{{ chart.title }}</h2>
                <div class="grid gap-2">
                    <div v-for="item in chart.items" :key="item.label" class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2">
                        <span>{{ item.label }}</span>
                        <strong>{{ item.value }}</strong>
                    </div>
                </div>
            </section>

            <section class="clinic-panel p-5">
                <h2 class="mb-4 text-lg font-semibold text-slate-800">Recent Registrations</h2>
                <table class="appointment-list">
                    <thead>
                        <tr>
                            <th>Patient ID</th>
                            <th>Name</th>
                            <th>Gender</th>
                            <th>Age</th>
                            <th>Registered Date</th>
                            <th>Branch</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in data.table" :key="row.patient_number">
                            <td>{{ row.patient_number }}</td>
                            <td>{{ row.name }}</td>
                            <td>{{ row.gender || '—' }}</td>
                            <td>{{ row.age ?? '—' }}</td>
                            <td>{{ row.registered_at || '—' }}</td>
                            <td>{{ row.branch || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>
    </div>
</template>
