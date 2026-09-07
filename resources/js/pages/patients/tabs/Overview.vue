<script setup>
import { computed } from 'vue';
import { usePatientStore } from '../../../stores/patients';
import { useClinicContextStore } from '../../../stores/clinicContext';
const store = usePatientStore(), context = useClinicContextStore();
const groups = computed(() => [
    ['Personal information', [['Full name',store.patient.full_name],['Date of birth',store.patient.date_of_birth],['Gender',store.patient.gender],['Blood group',store.patient.blood_group],['Marital status',store.patient.marital_status]]],
    ['Contact information', [['Phone',store.patient.phone],['Email',store.patient.email],['Address',store.patient.address],['City',store.patient.city],['Country',store.patient.country]]],
    ['Emergency contact', [['Name',store.patient.emergency_contact_name],['Relationship',store.patient.emergency_contact_relationship],['Phone',store.patient.emergency_contact_phone]]],
    ['Registration information', [['Patient number',store.patient.patient_number],['Registration branch',store.patient.registration_branch],['Registered',new Date(store.patient.registered_at).toLocaleString()],['Status',store.patient.status]]],
]);
</script>
<template><div class="patient-overview-grid"><section v-for="[title,fields] in groups" :key="title" class="clinic-panel"><h2 class="font-bold text-base mb-4">{{ title }}</h2><dl class="detail-list"><div v-for="[label,value] in fields" :key="label"><dt>{{ label }}</dt><dd class="break-words">{{ value || 'Not recorded' }}</dd></div></dl></section><section v-if="context.can('patients.medical_history.view')" class="clinic-panel"><h2 class="font-bold text-base">Allergies</h2><p class="mt-4 text-slate-600">{{ store.patient.allergies?.map(a => a.allergen).join(', ') || 'No known allergies recorded.' }}</p><RouterLink class="mt-4 inline-block back-link" :to="`/app/patients/${store.patient.id}/medical-history`">View medical history →</RouterLink></section><section v-if="context.can('patients.medical_history.view')" class="clinic-panel"><h2 class="font-bold text-base">Intake notes</h2><p class="mt-4 whitespace-pre-wrap text-slate-600">{{ store.patient.notes || 'No intake notes recorded.' }}</p></section></div></template>
