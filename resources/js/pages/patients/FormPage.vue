<script setup>
import { onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { usePatientStore } from '../../stores/patients';
import PatientForm from '../../components/patients/PatientForm.vue';
import FormErrors from '../../components/ui/FormErrors.vue';
const route = useRoute(), store = usePatientStore();
onMounted(() => { if (route.params.id) store.load(route.params.id); });
</script>
<template><div class="patient-page-header"><div><RouterLink to="/app/patients" class="back-link">← Patients</RouterLink><h1>{{ route.params.id ? 'Edit Patient' : 'Register New Patient' }}</h1><p>{{ route.params.id ? 'Update patient demographics and contact information.' : 'Create a new patient record.' }}</p></div></div><FormErrors v-if="route.params.id" :error="store.error" /><div v-if="route.params.id && store.busy" class="loading-state">Loading patient...</div><div v-else-if="route.params.id && store.error" class="clinic-panel"><button class="btn" @click="store.load(route.params.id)">Try again</button></div><div v-else-if="route.params.id && store.patient?.status === 'archived'" class="clinic-panel">Restore this patient before editing.</div><PatientForm v-else-if="!route.params.id || store.patient" :patient="route.params.id ? store.patient : null" /></template>
