<script setup>
import { computed, onMounted, ref } from 'vue';
import { useClinicContextStore } from '../../stores/clinicContext';
import AppIcon from '../../components/ui/AppIcon.vue';

const context = useClinicContextStore();
const selected = ref('PATIENT REPORTS');
const search = ref('');

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
            { key: 'billing', title: 'Billing Report', description: 'Invoices, collections, balances, and payment methods from the shared ledger', icon: 'revenue', route: '/app/billing/report' },
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

const visibleCategories = computed(() => categories.filter(category => category.section !== 'BILLING REPORTS' || context.allowed('billing')));
const visibleReports = computed(() => {
    const needle = search.value.trim().toLowerCase();
    if (needle) return visibleCategories.value.flatMap(category => category.items.map(item => ({ ...item, section: category.section })))
        .filter(item => [item.title, item.description, item.section].some(value => value.toLowerCase().includes(needle)));
    return (visibleCategories.value.find(category => category.section === selected.value)?.items || []).map(item => ({ ...item, section: selected.value }));
});
const categoryName = section => ({ 'PATIENT REPORTS': 'Patients', 'APPOINTMENT REPORTS': 'Appointments',
    'CLINICAL REPORTS': 'Clinical', 'DOCTOR REPORTS': 'Doctors', 'PRESCRIPTION REPORTS': 'Prescriptions',
    'BILLING REPORTS': 'Billing', 'PHARMACY REPORTS': 'Pharmacy' })[section] || section;

onMounted(() => {
    if (!context.data) {
        context.load();
    }
});
</script>

<template>
    <div class="reports-browser">
        <header class="patient-page-header"><div><p class="patient-breadcrumb"><RouterLink to="/app/dashboard">Dashboard</RouterLink><span>/</span>Reports</p><h1>Reports</h1><p>Choose a category, then open a report.</p></div></header>
        <div class="reports-toolbar"><nav class="reports-categories" aria-label="Report categories"><button v-for="category in visibleCategories" :key="category.section" type="button" :aria-pressed="selected === category.section && !search" :class="{ active: selected === category.section && !search }" @click="selected = category.section; search = ''">{{ categoryName(category.section) }}</button></nav><label class="reports-search"><span class="sr-only">Search reports</span><input v-model="search" type="search" placeholder="Search reports..." aria-label="Search reports"></label></div>
        <section class="reports-results"><div class="reports-results-heading"><h2>{{ search ? 'Search results' : categoryName(selected) }}</h2><span>{{ visibleReports.length }} {{ visibleReports.length === 1 ? 'report' : 'reports' }}</span></div>
            <div v-if="visibleReports.length" class="reports-grid"><RouterLink v-for="item in visibleReports" :key="item.key" :to="item.route" class="reports-item"><span class="reports-icon"><AppIcon :name="item.icon" :size="20"/></span><span class="reports-copy"><strong>{{ item.title }}</strong><small>{{ item.description }}</small><small v-if="search" class="reports-category-name">{{ categoryName(item.section) }}</small></span></RouterLink></div>
            <p v-else class="reports-empty">No reports match your search.</p>
        </section>
    </div>
</template>
<style scoped>
.reports-browser{max-width:1500px;margin:auto}.reports-toolbar{display:flex;align-items:start;justify-content:space-between;gap:18px;margin:16px 0 20px}.reports-categories{display:flex;flex-wrap:wrap;gap:8px}.reports-categories button{border:1px solid #c5d7df;border-radius:999px;background:#fff;color:#2e5060;padding:8px 13px;text-transform:capitalize;font-size:13px;font-weight:600;white-space:nowrap}.reports-categories button.active{background:#006d64;color:#fff;border-color:#006d64}.reports-categories button:focus-visible,.reports-item:focus-visible{outline:3px solid #14b8a6;outline-offset:2px}.reports-search input{width:220px;border:1px solid #c5d7df;border-radius:8px;padding:9px 12px;background:#fff}.reports-results-heading{display:flex;align-items:baseline;gap:10px;margin-bottom:12px}.reports-results-heading h2{text-transform:capitalize;font-size:18px;font-weight:700}.reports-results-heading span{font-size:12px;color:#63798a}.reports-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.reports-item{min-height:90px;display:flex;gap:11px;align-items:flex-start;padding:14px;background:#fff;border:1px solid #d9e5eb;border-radius:10px}.reports-item:hover{border-color:#008478;box-shadow:0 3px 12px #005e5318}.reports-icon{flex:none;color:#00766c;padding-top:2px}.reports-copy{min-width:0;display:grid;gap:3px}.reports-copy strong{font-size:14px;line-height:1.3}.reports-copy small{font-size:12px;color:#5b7181;line-height:1.35}.reports-category-name{text-transform:capitalize}.reports-empty{padding:24px;border:1px dashed #c5d7df;border-radius:10px;color:#63798a}@media(max-width:1200px){.reports-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:900px){.reports-toolbar{flex-direction:column}.reports-search input{width:min(100%,320px)}.reports-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.reports-grid{grid-template-columns:1fr}.reports-toolbar{gap:12px}.reports-search,.reports-search input{width:100%}}
</style>
