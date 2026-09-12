<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useClinicContextStore } from '../../stores/clinicContext';
import { reportService } from '../../services/reports';
import AppIcon from '../../components/ui/AppIcon.vue';
import FormErrors from '../../components/ui/FormErrors.vue';

const route = useRoute();
const context = useClinicContextStore();
const busy = ref(false);
const error = ref(null);
const data = ref({ metrics: [], charts: [], table: [] });
const generatedAt = ref(new Date().toLocaleString());

function printReport() {
    window.print();
}

const section = computed(() => route.params.section || 'overview');
const subsection = computed(() => route.params.subsection || null);
const sectionLabel = computed(() => {
    const map = {
        overview: 'Overview',
        patients: 'Patients',
        appointments: 'Appointments',
        clinical: 'Clinical',
        doctors: 'Doctors',
        prescriptions: 'Prescriptions',
        financial: 'Financial',
        branches: 'Branches',
    };
    return map[section.value] || section.value;
});

async function load() {
    busy.value = true;
    error.value = null;
    try {
        const fn = reportService[section.value];
        if (!fn) {
            throw new Error(`Unsupported report section: ${section.value}`);
        }
        const { data: response } = await fn({
            branch_id: context.data?.branch?.id,
            from: route.query.from,
            to: route.query.to,
        });
        data.value = response.data;
    } catch (e) {
        error.value = e;
    } finally {
        busy.value = false;
    }
}

watch(() => [route.params.section, route.params.subsection, route.query.from, route.query.to], load, { deep: true });

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
                    <span>{{ sectionLabel }}</span>
                    <span v-if="subsection">/ {{ subsection }}</span>
                </p>
                <h1>{{ sectionLabel }} Report</h1>
                <p>Clinic reporting data for the selected period.</p>
            </div>
            <button class="btn-secondary" type="button" @click="printReport">Print</button>
        </div>

        <FormErrors :error="error" />

        <div v-if="busy" class="clinic-skeleton-grid" role="status">
            <div v-for="n in 4" :key="n" class="clinic-skeleton"></div>
        </div>

        <div v-else class="space-y-6">
            <section class="clinic-panel p-5">
                <div class="flex flex-col gap-2 border-b border-slate-200 pb-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Generated</p>
                        <h2 class="text-xl font-semibold text-slate-800">{{ sectionLabel }} Report</h2>
                    </div>
                    <p class="text-sm text-slate-500">{{ generatedAt }}</p>
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div v-for="metric in data.metrics" :key="metric.label" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ metric.label }}</p>
                        <p class="mt-2 text-2xl font-bold text-slate-800">{{ metric.value }}</p>
                        <p v-if="metric.help" class="mt-2 text-sm text-slate-500">{{ metric.help }}</p>
                    </div>
                </div>
            </section>

            <section v-for="chart in data.charts" :key="chart.title" class="clinic-panel p-5">
                <h2 class="mb-4 text-lg font-semibold text-slate-800">{{ chart.title }}</h2>
                <div class="grid gap-2">
                    <div v-for="item in chart.items" :key="item.label" class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2">
                        <span>{{ item.label }}</span>
                        <strong>{{ item.value }}</strong>
                    </div>
                </div>
            </section>

            <section v-if="data.table?.length" class="clinic-panel p-5">
                <h2 class="mb-4 text-lg font-semibold text-slate-800">Details</h2>
                <div class="overflow-x-auto">
                    <table class="appointment-list">
                        <thead>
                            <tr>
                                <th v-for="key in Object.keys(data.table[0])" :key="key">{{ key }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in data.table" :key="index">
                                <td v-for="key in Object.keys(row)" :key="`${index}-${key}`">{{ row[key] ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section v-if="data.message" class="clinic-panel p-5">
                <h2 class="mb-2 text-lg font-semibold text-slate-800">Note</h2>
                <p class="text-sm text-slate-600">{{ data.message }}</p>
            </section>
        </div>
    </div>
</template>
