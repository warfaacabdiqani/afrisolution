<script setup>import {usePatientStore} from '../../../stores/patients';import ProfileAppointments from '../../../components/appointments/ProfileAppointments.vue';const store=usePatientStore();</script>
<template><ProfileAppointments :patient-id="store.patient.id"/></template>
