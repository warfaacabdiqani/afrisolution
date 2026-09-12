<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useClinicContextStore } from '../../stores/clinicContext';
import AppIcon from '../../components/ui/AppIcon.vue';

const router = useRouter();
const context = useClinicContextStore();
const busy = ref(false);
const error = ref(null);

const categories = [
    {
        section: 'PATIENT REPORTS',
        items: [
            { key: 'patients', title: 'Patient List', description: 'Printable patient register', icon: 'patient', route: '/app/reports/generate/patients' },
            { key: 'patients/registrations', title: 'New Patient Registrations', description: 'Patients registered within a selected period', icon: 'calendar', route: '/app/reports/generate/new_registrations' },
            { key: 'patients/demographics', title: 'Patient Demographic Report', description: 'Demographic breakdown for reporting', icon: 'members', route: '/app/reports/generate/patient_demographics' },
            { key: 'patients/medical-summary', title: 'Patient Medical Summary', description: 'Single-patient clinical summary document', icon: 'audit', route: '/app/reports/generate/patient_medical_summary' },
        ],
    },
    {
        section: 'APPOINTMENT REPORTS',
        items: [
            { key: 'appointments', title: 'Appointment List', description: 'Official appointment register', icon: 'calendar', route: '/app/reports/generate/appointments' },
            { key: 'appointments/daily', title: 'Daily Appointment Report', description: 'Reception-oriented daily schedule', icon: 'calendar', route: '/app/reports/generate/daily_appointments' },
            { key: 'appointments/doctor', title: 'Doctor Appointment Report', description: 'Appointments by selected doctor', icon: 'doctor', route: '/app/reports/generate/doctor_appointments' },
            { key: 'appointments/cancelled', title: 'Cancelled Appointments', description: 'Cancelled visits and reasons', icon: 'warning', route: '/app/reports/generate/cancelled_appointments' },
            { key: 'appointments/no-shows', title: 'No-Show Report', description: 'Missed visits and follow-up status', icon: 'warning', route: '/app/reports/generate/no_shows' },
        ],
    },
    {
        section: 'CLINICAL REPORTS',
        items: [
            { key: 'clinical', title: 'Consultation Report', description: 'Consultation list with diagnoses and status', icon: 'activity', route: '/app/reports/generate/consultations' },
            { key: 'clinical/summary', title: 'Consultation Summary', description: 'Clinical activity summary by period', icon: 'activity', route: '/app/reports/generate/consultation_summary' },
            { key: 'clinical/diagnoses', title: 'Diagnosis Report', description: 'Diagnosis trends and frequency', icon: 'audit', route: '/app/reports/generate/diagnosis_report' },
            { key: 'clinical/follow-ups', title: 'Follow-Up Report', description: 'Pending and completed follow-up tracking', icon: 'check', route: '/app/reports/generate/follow_up_report' },
        ],
    },
    {
        section: 'DOCTOR REPORTS',
        items: [
            { key: 'doctors', title: 'Doctor List', description: 'Printable doctor directory', icon: 'doctor', route: '/app/reports/generate/doctors' },
            { key: 'doctors/schedule', title: 'Doctor Schedule Report', description: 'Clinic schedule and assignment summary', icon: 'calendar', route: '/app/reports/generate/doctor_schedule' },
            { key: 'doctors/activity', title: 'Doctor Activity Report', description: 'Appointments, consultations and prescriptions', icon: 'activity', route: '/app/reports/generate/doctor_activity' },
        ],
    },
    {
        section: 'PRESCRIPTION REPORTS',
        items: [
            { key: 'prescriptions', title: 'Prescription List', description: 'Printable prescriptions register', icon: 'audit', route: '/app/reports/generate/prescriptions' },
            { key: 'prescriptions/detail', title: 'Prescription Detail Report', description: 'Medication details and instructions', icon: 'pill', route: '/app/reports/generate/prescription_detail' },
            { key: 'prescriptions/by-doctor', title: 'Prescriptions by Doctor', description: 'Doctor prescription activity summary', icon: 'doctor', route: '/app/reports/generate/prescriptions_by_doctor' },
            { key: 'prescriptions/by-patient', title: 'Prescriptions by Patient', description: 'Patient prescription history', icon: 'patient', route: '/app/reports/generate/prescriptions_by_patient' },
        ],
    },
    {
        section: 'BILLING REPORTS',
        items: [
            { key: 'billing/invoices', title: 'Invoice Report', description: 'Printable invoice register', icon: 'revenue', route: '/app/reports/generate/invoices' },
            { key: 'billing/payments', title: 'Payment Report', description: 'Payment activity and methods', icon: 'cash', route: '/app/reports/generate/payments' },
            { key: 'billing/outstanding', title: 'Outstanding Balance Report', description: 'Open balances and overdue accounts', icon: 'warning', route: '/app/reports/generate/outstanding_balances' },
            { key: 'billing/collections', title: 'Daily Collection Report', description: 'Daily cash and payment totals', icon: 'calendar', route: '/app/reports/generate/daily_collections' },
            { key: 'billing/receipts', title: 'Receipt Report', description: 'Receipt summary for issued payments', icon: 'revenue', route: '/app/reports/generate/receipts' },
        ],
    },
    {
        section: 'PHARMACY REPORTS',
        items: [
            { key: 'pharmacy/stock', title: 'Stock Report', description: 'Inventory balance register', icon: 'storage', route: '/app/reports/generate/stock_report' },
            { key: 'pharmacy/low-stock', title: 'Low Stock Report', description: 'Items needing replenishment', icon: 'warning', route: '/app/reports/generate/low_stock_report' },
            { key: 'pharmacy/expiry', title: 'Expiry Report', description: 'Inventory nearing expiry', icon: 'audit', route: '/app/reports/generate/expiry_report' },
            { key: 'pharmacy/dispensing', title: 'Dispensing Report', description: 'Medication dispensed summary', icon: 'pill', route: '/app/reports/generate/dispensing_report' },
            { key: 'pharmacy/purchase', title: 'Purchase Report', description: 'Incoming stock acquisition details', icon: 'revenue', route: '/app/reports/generate/purchase_report' },
        ],
    },
];

function goToGenerate() {
    router.push('/app/reports/generate/patients');
}

onMounted(() => {
    if (!context.data) {
        context.load();
    }
});
</script>

<template>
    <div>
        <div class="patient-page-header">
            <div>
                <p class="patient-breadcrumb">
                    <RouterLink to="/app/dashboard">Dashboard</RouterLink>
                    <span>/</span>
                    <span>Reports</span>
                </p>
                <h1>Reports</h1>
                <p>Generate, preview, print and export clinic reports.</p>
            </div>
            <button class="btn-secondary" type="button" @click="goToGenerate">Generate Report</button>
        </div>

        <div v-if="busy" class="clinic-skeleton-grid mt-6" role="status" aria-label="Loading reports">
            <div v-for="n in 6" :key="n" class="clinic-skeleton"></div>
        </div>

        <div v-else class="mt-6 space-y-8">
            <section v-for="group in categories" :key="group.section" class="space-y-4">
                <h2 class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ group.section }}</h2>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <RouterLink v-for="item in group.items" :key="item.key" :to="item.route" class="clinic-panel block p-5 transition hover:border-teal-400 hover:shadow-md">
                        <div class="mb-4 flex items-center justify-between">
                            <span class="clinic-kpi-icon mint">
                                <AppIcon :name="item.icon" :size="22" />
                            </span>
                        </div>
                        <h3 class="text-lg font-semibold text-slate-800">{{ item.title }}</h3>
                        <p class="mt-2 text-sm text-slate-600">{{ item.description }}</p>
                    </RouterLink>
                </div>
            </section>
        </div>
    </div>
</template>
