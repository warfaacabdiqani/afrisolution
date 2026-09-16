<script setup>
import { computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { usePatientStore } from '../../stores/patients';
import { useClinicContextStore } from '../../stores/clinicContext';
import PatientActions from '../../components/patients/PatientActions.vue';
import PatientStatus from '../../components/patients/PatientStatus.vue';
import FormErrors from '../../components/ui/FormErrors.vue';
const store = usePatientStore(), context = useClinicContextStore(), route = useRoute();
const tabs = computed(() => [['overview','Overview'],['medical-history','Medical History','patients.medical_history.view'],['appointments','Appointments',null,'appointments'],['consultations','Consultations',null,'emr'],['vitals','Vital Signs',null,'vital_signs'],['prescriptions','Prescriptions','prescriptions.view','prescriptions'],['laboratory','Laboratory'],['documents','Documents','patients.documents.view'],['billing','Billing','billing.view','billing'],['activity','Activity']].filter(([, , permission, feature]) => (!permission || context.can(permission)) && (permission !== 'billing.view' || context.allowed('billing')) && (!feature || context.data.features[feature])));
function load() { store.load(route.params.id); }
onMounted(load);
</script>
<template>
    <RouterLink class="back-link" to="/app/patients">← Patients</RouterLink><p v-if="store.notice" class="clinic-trial mt-4" role="status">{{ store.notice }}<button class="ml-auto" aria-label="Dismiss notification" @click="store.notice = ''">×</button></p><FormErrors class="mt-4" :error="store.error" />
    <div v-if="store.busy" class="loading-state mt-5">Loading patient profile...</div><div v-else-if="store.error" class="clinic-panel"><button class="btn" @click="load">Try again</button></div>
    <template v-else-if="store.patient"><section class="clinic-panel patient-profile-header"><span class="patient-profile-avatar">{{ store.patient.first_name[0] }}{{ store.patient.last_name[0] }}</span><div><h1>{{ store.patient.full_name }}</h1><p>{{ store.patient.patient_number }} <PatientStatus :status="store.patient.status" /></p><p class="capitalize">{{ store.patient.age === null ? 'Age unknown' : `${store.patient.age} years` }} · {{ store.patient.gender }} · {{ store.patient.blood_group || 'Blood group unknown' }}</p><p>{{ store.patient.phone || 'No phone recorded' }}</p></div><div class="patient-profile-actions"><RouterLink v-if="context.allowed('appointments') && context.can('appointments.create') && store.patient.status !== 'archived'" class="btn-secondary" :to="`/app/appointments/create?patient_id=${store.patient.id}`">New Appointment</RouterLink><RouterLink v-if="context.can('patients.update') && store.patient.status !== 'archived'" class="btn" :to="`/app/patients/${store.patient.id}/edit`">Edit Patient</RouterLink><PatientActions :patient="store.patient" @changed="load" /></div></section>
        <div v-if="store.patient.allergies?.some(a => a.severity === 'severe')" class="patient-allergy-alert" role="note"><strong>Severe allergies:</strong> {{ store.patient.allergies.filter(a => a.severity === 'severe').map(a => a.allergen).join(', ') }}</div>
        <nav class="clinic-tabs mt-6" aria-label="Patient profile"><RouterLink v-for="[path,label] in tabs" :key="path" :to="`/app/patients/${store.patient.id}/${path}`">{{ label }}</RouterLink></nav><RouterView />
    </template>
</template>
