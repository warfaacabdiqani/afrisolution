<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useClinicContextStore } from '../../stores/clinicContext';
import { reportService } from '../../services/reports';
import FormErrors from '../../components/ui/FormErrors.vue';
import ReportDocument from '../../components/reports/ReportDocument.vue';

const route = useRoute();
const router = useRouter();
const context = useClinicContextStore();
const busy = ref(false);
const error = ref(null);
const reportData = ref(null);
const generatedAt = ref(new Date().toLocaleString());
const appliedFilters = ref(null);
let generation = 0;

const reportType = computed(() => route.params.type || 'patients');
const reportMeta = computed(() => {
    const map = {
        patients: { label: 'Patient List Report', service: 'patients', filters: ['date_range', 'branch', 'status'] },
        new_registrations: { label: 'New Patient Registrations', service: 'patients', filters: ['date_range', 'branch'] },
        patient_demographics: { label: 'Patient Demographic Report', service: 'patients', filters: ['date_range', 'branch'] },
        patient_medical_summary: { label: 'Patient Medical Summary', service: 'patients', filters: ['date_range', 'patient', 'branch'] },
        appointments: { label: 'Appointment List Report', service: 'appointments', filters: ['date_range', 'doctor', 'status', 'branch'] },
        daily_appointments: { label: 'Daily Appointment Report', service: 'appointments', filters: ['date_range', 'doctor', 'status', 'branch'] },
        doctor_appointments: { label: 'Doctor Appointment Report', service: 'appointments', filters: ['date_range', 'doctor', 'status', 'branch'] },
        cancelled_appointments: { label: 'Cancelled Appointments Report', service: 'appointments', filters: ['date_range', 'doctor', 'branch'] },
        no_shows: { label: 'No-Show Report', service: 'appointments', filters: ['date_range', 'doctor', 'branch'] },
        consultations: { label: 'Consultation Report', service: 'clinical', filters: ['date_range', 'doctor', 'patient', 'status', 'branch'] },
        consultation_summary: { label: 'Consultation Summary', service: 'clinical', filters: ['date_range', 'doctor', 'branch'] },
        diagnosis_report: { label: 'Diagnosis Report', service: 'clinical', filters: ['date_range', 'doctor', 'branch'] },
        follow_up_report: { label: 'Follow-Up Report', service: 'clinical', filters: ['date_range', 'doctor', 'patient', 'branch'] },
        doctors: { label: 'Doctor List Report', service: 'doctors', filters: ['date_range', 'branch'] },
        doctor_schedule: { label: 'Doctor Schedule Report', service: 'doctors', filters: ['date_range', 'doctor', 'branch'] },
        doctor_activity: { label: 'Doctor Activity Report', service: 'doctors', filters: ['date_range', 'doctor', 'branch'] },
        prescriptions: { label: 'Prescription List Report', service: 'prescriptions', filters: ['date_range', 'doctor', 'patient', 'status', 'branch'] },
        prescription_detail: { label: 'Prescription Detail Report', service: 'prescriptions', filters: ['date_range', 'doctor', 'patient', 'branch'] },
        prescriptions_by_doctor: { label: 'Prescriptions by Doctor', service: 'prescriptions', filters: ['date_range', 'doctor', 'branch'] },
        prescriptions_by_patient: { label: 'Prescriptions by Patient', service: 'prescriptions', filters: ['date_range', 'patient', 'branch'] },
        invoices: { label: 'Invoice Report', service: 'financial', filters: ['date_range', 'patient', 'status', 'branch'] },
        payments: { label: 'Payment Report', service: 'financial', filters: ['date_range', 'patient', 'branch'] },
        outstanding_balances: { label: 'Outstanding Balance Report', service: 'financial', filters: ['date_range', 'patient', 'branch'] },
        daily_collections: { label: 'Daily Collection Report', service: 'financial', filters: ['date_range', 'branch'] },
        receipts: { label: 'Receipt Report', service: 'financial', filters: ['date_range', 'patient', 'branch'] },
        stock_report: { label: 'Stock Report', service: 'pharmacy', filters: ['date_range', 'branch'] },
        low_stock_report: { label: 'Low Stock Report', service: 'pharmacy', filters: ['date_range', 'branch'] },
        expiry_report: { label: 'Expiry Report', service: 'pharmacy', filters: ['date_range', 'branch'] },
        dispensing_report: { label: 'Dispensing Report', service: 'pharmacy', filters: ['date_range', 'branch'] },
        purchase_report: { label: 'Purchase Report', service: 'pharmacy', filters: ['date_range', 'branch'] },
    };

    return map[reportType.value] || { label: 'Report', service: 'overview', filters: ['date_range', 'branch'] };
});

const filters = ref({
    from: '',
    to: '',
    branch_id: String(context.data?.branch?.id || ''),
    doctor_id: '',
    patient_id: '',
    status: '',
});

const rows = computed(() => reportData.value?.table || []);
const metrics = computed(() => reportData.value?.metrics || []);
const dedicatedBreakdowns = new Set(['patient_demographics', 'consultation_summary', 'diagnosis_report', 'follow_up_report', 'doctor_activity']);
const breakdowns = computed(() => dedicatedBreakdowns.has(reportType.value) && !rows.value.length ? (reportData.value?.charts || []) : []);
const summary = computed(() => {
    const preferred = reportType.value === 'patients' ? ['Total Patients', 'Active Patients', 'Archived Patients']
        : reportType.value === 'new_registrations' ? ['New Patients']
        : reportMeta.value.service === 'appointments' ? ['Total Appointments', 'Completed', 'Cancelled', 'No Shows'] : [];
    const selected = preferred.length ? preferred.map(label => metrics.value.find(metric => metric.label === label)).filter(Boolean) : metrics.value.slice(0, 4);
    const names = { 'Total Patients': ['patient', 'patients'], 'Active Patients': ['active', 'active'],
        'Archived Patients': ['archived', 'archived'], 'New Patients': ['new patient registered during this period', 'new patients registered during this period'],
        'Total Appointments': ['appointment', 'appointments'], 'Completed': ['completed', 'completed'],
        'Cancelled': ['cancelled', 'cancelled'], 'No Shows': ['no-show', 'no-shows'] };
    return selected.map(metric => ({ label: names[metric.label]?.[Number(metric.value) === 1 ? 0 : 1] || metric.label.toLowerCase(), value: metric.value }));
});
const branchName = computed(() => (context.data?.branches || []).find(branch => String(branch.id) === String(appliedFilters.value?.branch_id))?.name || context.data?.branch?.name || 'Current branch');
const period = computed(() => [appliedFilters.value?.from, appliedFilters.value?.to].filter(Boolean).join(' â€“ '));

function initializeFilters() {
    const now = new Date();
    const start = new Date(now.getFullYear(), now.getMonth(), 1);
    filters.value.from = start.toISOString().slice(0, 10);
    filters.value.to = now.toISOString().slice(0, 10);
    filters.value.branch_id = String(context.data?.branch?.id || '');
}

function sanitizeParams() {
    return {
        from: filters.value.from || undefined,
        to: filters.value.to || undefined,
        branch_id: filters.value.branch_id && filters.value.branch_id !== 'all' ? filters.value.branch_id : undefined,
        doctor_id: filters.value.doctor_id || undefined,
        patient_id: filters.value.patient_id || undefined,
        status: filters.value.status || undefined,
    };
}

async function generateReport() {
    const token = ++generation;
    busy.value = true;
    error.value = null;
    reportData.value = null;
    try {
        const reportFn = reportService[reportMeta.value.service];
        if (!reportFn) {
            throw new Error('This report is not available in the current build.');
        }

        const { data: response } = await reportFn(sanitizeParams());
        if (token !== generation) return;
        reportData.value = response.data;
        appliedFilters.value = { ...filters.value };
        generatedAt.value = new Date().toLocaleString();
    } catch (e) {
        if (token === generation) error.value = e;
    } finally {
        if (token === generation) busy.value = false;
    }
}

function resetFilters() {
    initializeFilters();
    reportData.value = null;
}

function exportCsv() {
    const list = rows.value;
    if (!list.length) return;

    const headers = Object.keys(list[0]);
    const csv = [headers.join(',')]
        .concat(list.map(row => headers.map(key => `"${String(row[key] ?? '').replace(/"/g, '""')}"`).join(',')))
        .join('\n');

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `${reportType.value}.csv`;
    link.click();
    URL.revokeObjectURL(url);
}

function printReport() {
    window.print();
}

onMounted(() => {
    initializeFilters();
    generateReport();
});
watch(reportType, () => { generation++; reportData.value = null; initializeFilters(); generateReport(); });
onUnmounted(() => { generation++; });
</script>

<template>
    <div class="report-page">
        <header class="patient-page-header no-print">
            <div><p class="patient-breadcrumb"><RouterLink to="/app/reports">Reports</RouterLink><span>/</span>{{ reportMeta.label }}</p><h1>{{ reportMeta.label }}</h1><p>Preview and print records for the selected filters.</p></div>
            <div class="flex gap-2"><button class="btn-secondary" type="button" @click="router.back()">Back</button><button class="btn" type="button" :disabled="busy" @click="generateReport">Generate</button></div>
        </header>
        <form class="clinic-panel report-filter-form no-print" @submit.prevent="generateReport">
            <label class="field">Date From<input v-model="filters.from" type="date"></label>
            <label class="field">Date To<input v-model="filters.to" type="date" :min="filters.from"></label>
            <label class="field">Branch<select v-model="filters.branch_id"><option v-for="branch in context.data?.branches || []" :key="branch.id" :value="String(branch.id)">{{ branch.name }}</option></select></label>
            <label v-if="reportMeta.filters.includes('doctor')" class="field">Doctor ID<input v-model="filters.doctor_id" type="number" min="1"></label>
            <label v-if="reportMeta.filters.includes('patient')" class="field">Patient ID<input v-model="filters.patient_id" type="number" min="1"></label>
            <label v-if="reportMeta.filters.includes('status')" class="field">Status<select v-model="filters.status"><option value="">All statuses</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option><option value="no_show">No Show</option><option value="active">Active</option><option value="draft">Draft</option></select></label>
            <div class="report-filter-actions"><button class="btn-secondary" type="button" @click="resetFilters">Reset</button><button class="btn" type="submit" :disabled="busy">Generate</button></div>
        </form>
        <FormErrors :error="error"/><p v-if="busy" role="status">Loading report…</p>
        <ReportDocument v-else-if="reportData" :title="reportMeta.label" :business-name="context.data?.clinic?.name || 'Business'" :branch-name="branchName" :period="period" :generated-at="generatedAt" :summary="summary" :rows="rows" :breakdowns="breakdowns" :message="reportData.message || ''"/>
        <div v-if="reportData" class="no-print report-bottom-actions"><button class="btn-secondary" type="button" @click="printReport">Print</button><button class="btn-secondary" type="button" :disabled="!rows.length" @click="exportCsv">Export Excel</button></div>
    </div>
</template>
<style scoped>
.report-page{max-width:1280px;margin:auto}.report-filter-form{display:flex;flex-wrap:wrap;align-items:end;gap:12px;padding:16px;margin-bottom:18px}.report-filter-form .field{min-width:150px;flex:1 1 150px}.report-filter-actions,.report-bottom-actions{display:flex;align-items:center;gap:8px}.report-bottom-actions{margin-top:14px}@media(max-width:600px){.report-filter-form .field{flex:1 1 100%}.report-filter-actions{width:100%}}
</style>
