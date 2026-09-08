<script setup>
import {ref,onMounted} from 'vue';import {useRoute} from 'vue-router';import {appointmentService} from '../../services/appointments';import AppointmentForm from '../../components/appointments/AppointmentForm.vue';import FormErrors from '../../components/ui/FormErrors.vue';
const route=useRoute(),appointment=ref(null),busy=ref(true),error=ref(null);
async function load(){busy.value=true;error.value=null;try{if(route.params.id)appointment.value=(await appointmentService.get(route.params.id)).data.data;}catch(e){error.value=e;}finally{busy.value=false;}}onMounted(load);
</script>
<template><div class="patient-page-header"><div><RouterLink class="back-link" to="/app/appointments">← Appointments</RouterLink><h1>{{route.meta.reschedule?'Reschedule Appointment':route.params.id?'Edit Appointment':'New Appointment'}}</h1><p>Choose a patient, clinician and available time.</p></div></div><FormErrors :error="error"/><div v-if="busy" class="loading-state">Loading appointment…</div><button v-else-if="error" class="btn" @click="load">Try again</button><AppointmentForm v-else :appointment="appointment" :reschedule="Boolean(route.meta.reschedule)"/></template>
