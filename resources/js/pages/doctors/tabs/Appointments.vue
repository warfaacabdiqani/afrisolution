<script setup>import {useDoctorStore} from '../../../stores/doctors';import ProfileAppointments from '../../../components/appointments/ProfileAppointments.vue';const store=useDoctorStore();</script>
<template><ProfileAppointments :doctor-id="store.doctor.id"/></template>
