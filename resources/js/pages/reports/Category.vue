<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useClinicContextStore } from '../../stores/clinicContext';
import { reportService } from '../../services/reports';
import FormErrors from '../../components/ui/FormErrors.vue';
import ReportDocument from '../../components/reports/ReportDocument.vue';

const route = useRoute();
const context = useClinicContextStore();
const busy = ref(false);
const error = ref(null);
const data = ref({ metrics: [], charts: [], table: [] });
const generatedAt = ref(new Date().toLocaleString());
const summary = computed(() => (data.value.metrics || []).slice(0, 4).map(metric => ({ label: metric.label.toLowerCase(), value: metric.value })));
const breakdowns = computed(() => data.value.table?.length ? [] : (data.value.charts || []));

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
    <div class="report-page">
        <header class="patient-page-header no-print"><div><p class="patient-breadcrumb"><RouterLink to="/app/reports">Reports</RouterLink><span>/</span>{{ sectionLabel }}</p><h1>{{ sectionLabel }} Report</h1></div><button class="btn-secondary" type="button" @click="printReport">Print</button></header>
        <FormErrors :error="error"/><p v-if="busy" role="status">Loading report…</p>
        <ReportDocument v-else :title="sectionLabel + ' Report'" :business-name="context.data?.clinic?.name || 'Business'" :branch-name="context.data?.branch?.name || ''" :period="[route.query.from, route.query.to].filter(Boolean).join(' – ')" :generated-at="generatedAt" :summary="summary" :rows="data.table || []" :breakdowns="breakdowns" :message="data.message || ''"/>
    </div>
</template>
<style scoped>.report-page{max-width:1280px;margin:auto}</style>
