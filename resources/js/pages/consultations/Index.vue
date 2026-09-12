<script setup>
import { onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { useClinicContextStore } from '../../stores/clinicContext';
import { appointmentService } from '../../services/appointments';
import FormErrors from '../../components/ui/FormErrors.vue';
import AppointmentStatusBadge from '../../components/appointments/AppointmentStatusBadge.vue';
import AppIcon from '../../components/ui/AppIcon.vue';
import { appointmentLink, formatDate } from '../../utils/appointmentDates';

const props = defineProps({ patientId: { type: Number, default: null } });
const context = useClinicContextStore();
const rows = ref([]);
const meta = ref(null);
const options = ref({ doctors: [], statuses: {}, types: [] });
const error = ref(null);
const busy = ref(false);
const stats = ref({ today: 0, waiting: 0, in_progress: 0, completed: 0 });
const filters = reactive({
    search: '',
    status: '',
    doctor_id: '',
    branch_id: context.data?.branch?.id ?? '',
    from: '',
    to: '',
    page: 1,
    per_page: 20,
});
let timer = null;

function normalizeParams(params) {
    const next = { ...params };
    Object.keys(next).forEach((key) => {
        if (next[key] === '' || next[key] === null || next[key] === undefined) {
            delete next[key];
        }
    });
    return next;
}

async function loadOptions() {
    try {
        const branchId = filters.branch_id || context.data?.branch?.id;
        if (!branchId) return;
        const { data } = await appointmentService.options(branchId);
        options.value = data.data;
    } catch (e) {
        error.value = e;
    }
}

async function load(page = 1) {
    busy.value = true;
    error.value = null;
    filters.page = page;

    const params = normalizeParams({
        branch_id: filters.branch_id || context.data?.branch?.id,
        doctor_id: filters.doctor_id,
        status: filters.status,
        search: filters.search,
        start: filters.from,
        end: filters.to,
        page: filters.page,
        per_page: filters.per_page,
        ...(props.patientId ? { patient_id: props.patientId } : {}),
    });

    try {
        const [listResponse, todayResponse] = await Promise.all([
            appointmentService.list(params),
            appointmentService.today({
                ...(props.patientId ? { patient_id: props.patientId } : {}),
                branch_id: params.branch_id,
            }),
        ]);

        rows.value = listResponse.data.data;
        meta.value = listResponse.data.meta;

        const summary = todayResponse.data.summary ?? {};
        stats.value = {
            today: Object.values(summary).reduce((total, value) => total + Number(value || 0), 0),
            waiting: Number(summary.waiting || 0),
            in_progress: Number(summary.in_consultation || 0),
            completed: Number(summary.completed || 0),
        };
    } catch (e) {
        error.value = e;
    } finally {
        busy.value = false;
    }
}

function apply() {
    filters.page = 1;
    load(1);
}

function search() {
    clearTimeout(timer);
    timer = setTimeout(() => {
        apply();
    }, 300);
}

function reset() {
    filters.search = '';
    filters.status = '';
    filters.doctor_id = '';
    filters.from = '';
    filters.to = '';
    filters.page = 1;
    apply();
}

function initials(fullName = '') {
    return fullName
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('') || 'PT';
}

watch(() => filters.branch_id, async (branchId) => {
    if (!branchId) return;
    filters.doctor_id = '';
    await loadOptions();
    await load(1);
});

onMounted(async () => {
    if (context.data?.branch?.id) {
        filters.branch_id = context.data.branch.id;
        await loadOptions();
    }
    await load(1);
});

onUnmounted(() => {
    clearTimeout(timer);
});
</script>

<template>
    <div>
        <div class="patient-page-header">
            <div>
                <p class="patient-breadcrumb">
                    <RouterLink to="/app/dashboard">Dashboard</RouterLink>
                    <span>/</span>
                    <span>{{ props.patientId ? 'Patient Consultations' : 'Consultations' }}</span>
                </p>
                <h1>Consultations</h1>
                <p>Manage patient clinical encounters, diagnoses and treatment plans.</p>
            </div>
            <RouterLink v-if="context.can('appointments.create')" class="btn" to="/app/appointments/create">
                + New Consultation
            </RouterLink>
        </div>

        <div class="clinic-kpis">
            <section class="clinic-panel clinic-kpi">
                <span class="clinic-kpi-icon mint"><AppIcon name="activity" :size="28" /></span>
                <div>
                    <h2>Today's Consultations</h2>
                    <strong>{{ stats.today }}</strong>
                    <p>Encountered today</p>
                </div>
            </section>
            <section class="clinic-panel clinic-kpi">
                <span class="clinic-kpi-icon blue"><AppIcon name="calendar" :size="28" /></span>
                <div>
                    <h2>Waiting</h2>
                    <strong>{{ stats.waiting }}</strong>
                    <p>Currently waiting</p>
                </div>
            </section>
            <section class="clinic-panel clinic-kpi">
                <span class="clinic-kpi-icon violet"><AppIcon name="activity" :size="28" /></span>
                <div>
                    <h2>In Progress</h2>
                    <strong>{{ stats.in_progress }}</strong>
                    <p>Currently active</p>
                </div>
            </section>
            <section class="clinic-panel clinic-kpi">
                <span class="clinic-kpi-icon green"><AppIcon name="audit" :size="28" /></span>
                <div>
                    <h2>Completed Today</h2>
                    <strong>{{ stats.completed }}</strong>
                    <p>Finished today</p>
                </div>
            </section>
        </div>

        <div class="clinic-panel patient-filters">
            <input
                v-model="filters.search"
                class="filter-input"
                type="search"
                placeholder="Search patient name, consultation ID, doctor..."
                aria-label="Search consultations"
                @input="search"
            />
            <select v-model="filters.status" class="compact-select" aria-label="Consultation status" @change="apply">
                <option value="">Status</option>
                <option v-for="(label, value) in options.statuses" :key="value" :value="value">
                    {{ label[0] }}
                </option>
            </select>
            <select v-model="filters.doctor_id" class="compact-select" aria-label="Doctor filter" @change="apply">
                <option value="">Doctor</option>
                <option v-for="doctor in options.doctors" :key="doctor.id" :value="doctor.id">
                    {{ doctor.full_name }}
                </option>
            </select>
            <label class="field compact-field">
                <span>Date from</span>
                <input v-model="filters.from" type="date" @change="apply" />
            </label>
            <label class="field compact-field">
                <span>Date to</span>
                <input v-model="filters.to" type="date" :min="filters.from" @change="apply" />
            </label>
            <button type="button" class="btn-secondary" @click="reset">Reset</button>
        </div>

        <FormErrors :error="error" />

        <section class="clinic-panel patient-list-panel">
            <div v-if="busy" class="p-6" role="status">Loading consultations…</div>
            <div v-else-if="!rows.length" class="clinic-empty">
                <AppIcon name="activity" :size="36" />
                <strong>{{ props.patientId ? 'No consultations have been recorded for this patient yet.' : 'No consultations have been created yet.' }}</strong>
                <RouterLink v-if="context.can('appointments.create')" class="btn" to="/app/appointments/create">
                    Create First Consultation
                </RouterLink>
            </div>

            <div v-else class="patient-list-table-wrap">
                <table class="appointment-list">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Consultation</th>
                            <th>Doctor / Clinician</th>
                            <th>Appointment</th>
                            <th>Fee</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="appointment in rows" :key="appointment.id">
                            <td>
                                <div class="patient-inline">
                                    <span class="patient-avatar small">{{ initials(appointment.patient.full_name) }}</span>
                                    <div>
                                        <strong>{{ appointment.patient.full_name }}</strong>
                                        <small>{{ appointment.patient.patient_number }} · {{ appointment.patient.phone || 'No phone' }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>{{ appointment.appointment_number }}</td>
                            <td>
                                <div class="patient-inline">
                                    <span class="patient-avatar small accent">{{ initials(appointment.doctor.full_name) }}</span>
                                    <div>
                                        <strong>{{ appointment.doctor.full_name }}</strong>
                                        <small>{{ appointment.type || 'Appointment' }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                {{ formatDate(appointment.starts_at.slice(0, 10)) }}
                                <small>{{ appointment.starts_at.slice(11, 16) }} – {{ appointment.ends_at.slice(11, 16) }}</small>
                            </td>
                            <td>
                                <strong>{{ appointment.currency || context.data.plan?.currency || 'USD' }} {{ Number(appointment.consultation_fee || 0).toFixed(2) }}</strong>
                                <small>{{ appointment.consultation_fee_source === 'doctor' ? 'Doctor fee' : 'Clinic default' }}</small>
                            </td>
                            <td>
                                <AppointmentStatusBadge :status="appointment.status" />
                            </td>
                            <td>
                                <RouterLink :to="appointmentLink(appointment)" class="btn-secondary">Open</RouterLink>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="meta && !busy" class="patient-pagination">
                <span>Showing {{ meta.from || 0 }} to {{ meta.to || 0 }} of {{ meta.total }} consultations</span>
                <button class="btn-secondary" :disabled="meta.current_page <= 1" @click="load(meta.current_page - 1)">Previous</button>
                <span>{{ meta.current_page }} / {{ meta.last_page }}</span>
                <button class="btn-secondary" :disabled="meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)">Next</button>
            </div>
        </section>
    </div>
</template>
