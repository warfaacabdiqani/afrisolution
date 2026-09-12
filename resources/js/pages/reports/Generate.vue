<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useClinicContextStore } from '../../stores/clinicContext';
import { reportService } from '../../services/reports';
import FormErrors from '../../components/ui/FormErrors.vue';

const route = useRoute();
const router = useRouter();
const context = useClinicContextStore();
const busy = ref(false);
const error = ref(null);
const reportData = ref(null);
const generatedAt = ref(new Date().toLocaleString());

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
const charts = computed(() => reportData.value?.charts || []);
const note = computed(() => reportData.value?.message || null);

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
    busy.value = true;
    error.value = null;
    try {
        const reportFn = reportService[reportMeta.value.service];
        if (!reportFn) {
            throw new Error('This report is not available in the current build.');
        }

        const { data: response } = await reportFn(sanitizeParams());
        reportData.value = response.data;
        generatedAt.value = new Date().toLocaleString();
    } catch (e) {
        error.value = e;
    } finally {
        busy.value = false;
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
</script>

<template>
    <div class="report-page">
        <div class="patient-page-header no-print">
            <div>
                <p class="patient-breadcrumb">
                    <RouterLink to="/app/dashboard">Dashboard</RouterLink>
                    <span>/</span>
                    <RouterLink to="/app/reports">Reports</RouterLink>
                    <span>/</span>
                    <span>{{ reportMeta.label }}</span>
                </p>
                <h1>{{ reportMeta.label }}</h1>
                <p>Generate, preview and print a clinic report from live data.</p>
            </div>
            <div class="flex items-center gap-3">
                <button class="btn-secondary" type="button" @click="router.back()">Back</button>
                <button class="btn-primary" type="button" @click="generateReport">Generate</button>
            </div>
        </div>

        <section class="clinic-panel p-5 no-print">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <label class="field">
                    <span>Date From</span>
                    <input v-model="filters.from" type="date" />
                </label>
                <label class="field">
                    <span>Date To</span>
                    <input v-model="filters.to" type="date" />
                </label>
                <label class="field">
                    <span>Branch</span>
                    <select v-model="filters.branch_id">
                        <option :value="String(context.data?.branch?.id || '')">{{ context.data?.branch?.name || 'Current branch' }}</option>
                        <option v-for="branch in context.data?.branches || []" :key="branch.id" :value="String(branch.id)">{{ branch.name }}</option>
                    </select>
                </label>
                <label class="field">
                    <span>Doctor</span>
                    <input v-model="filters.doctor_id" type="number" min="1" placeholder="Doctor ID" />
                </label>
                <label class="field">
                    <span>Patient</span>
                    <input v-model="filters.patient_id" type="number" min="1" placeholder="Patient ID" />
                </label>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-3">
                <label v-if="reportMeta.filters.includes('status')" class="field min-w-[180px]">
                    <span>Status</span>
                    <select v-model="filters.status">
                        <option value="">All statuses</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="no_show">No Show</option>
                        <option value="active">Active</option>
                        <option value="draft">Draft</option>
                    </select>
                </label>
                <button class="btn-secondary" type="button" @click="resetFilters">Reset</button>
                <button class="btn-primary" type="button" @click="generateReport">Generate</button>
            </div>
        </section>

        <FormErrors :error="error" />

        <div v-if="busy" class="clinic-skeleton-grid mt-6" role="status">
            <div v-for="n in 4" :key="n" class="clinic-skeleton"></div>
        </div>

        <div v-else-if="reportData" class="mt-6">
            <section class="report-document clinic-panel p-8">
                <div class="report-header mb-6 border-b border-slate-200 pb-5">
                    <div class="flex items-start justify-between gap-6">
                        <div>
                            <div class="mb-2 flex items-center gap-3">
                                <div class="clinic-logo report-logo">AC</div>
                                <div>
                                    <h2 class="text-2xl font-bold text-slate-800">{{ context.data?.clinic?.name || 'Afri Clinic' }}</h2>
                                    <p class="text-sm text-slate-500">{{ context.data?.branch?.name || 'Main Branch' }}</p>
                                </div>
                            </div>
                            <div class="mt-3 text-sm text-slate-500">
                                <p>{{ context.data?.clinic?.address || 'Clinic address' }}</p>
                                <p>{{ context.data?.clinic?.phone || 'Clinic phone' }} • {{ context.data?.clinic?.email || 'Clinic email' }}</p>
                            </div>
                        </div>
                        <div class="text-right text-sm text-slate-500">
                            <p class="font-semibold text-slate-700">{{ reportMeta.label }}</p>
                            <p>{{ filters.from || '—' }} to {{ filters.to || '—' }}</p>
                            <p>Generated: {{ generatedAt }}</p>
                        </div>
                    </div>
                </div>

                <div class="mb-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div v-for="metric in metrics" :key="metric.label" class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">{{ metric.label }}</p>
                        <p class="mt-2 text-2xl font-bold text-slate-800">{{ metric.value }}</p>
                    </div>
                </div>

                <div v-if="charts.length" class="mb-6 grid gap-4 md:grid-cols-2">
                    <div v-for="chart in charts" :key="chart.title" class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.12em] text-slate-500">{{ chart.title }}</h3>
                        <div class="space-y-2">
                            <div v-for="item in chart.items" :key="item.label" class="flex items-center justify-between rounded-lg bg-white px-3 py-2">
                                <span>{{ item.label }}</span>
                                <strong>{{ item.value }}</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="rows.length" class="report-table-wrap overflow-x-auto">
                    <table class="appointment-list report-table">
                        <thead>
                            <tr>
                                <th v-for="(key, index) in Object.keys(rows[0])" :key="`${key}-${index}`">{{ key }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in rows" :key="`${index}-${Object.values(row).join('-')}`">
                                <td v-for="(value, keyIndex) in Object.values(row)" :key="`${index}-${keyIndex}`">{{ value ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-slate-500">
                    No rows returned for this report.
                </div>

                <div v-if="note" class="mt-6 rounded-xl border border-amber-100 bg-amber-50 p-4 text-sm text-amber-800">
                    {{ note }}
                </div>

                <div class="mt-6 border-t border-slate-200 pt-4 text-sm text-slate-500">
                    <div class="flex items-center justify-between gap-4">
                        <span>Generated by: {{ context.data?.user?.name || 'System' }}</span>
                        <span>Page 1 of 1</span>
                    </div>
                </div>
            </section>

            <div class="no-print mt-6 flex flex-wrap items-center gap-3">
                <button class="btn-secondary" type="button" @click="router.back()">Back</button>
                <button class="btn-secondary" type="button" @click="printReport">Print</button>
                <button class="btn-secondary" type="button" @click="exportCsv">Export Excel</button>
            </div>
        </div>
    </div>
</template>
