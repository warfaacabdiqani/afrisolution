<script setup>
import { computed } from 'vue';import { useAppointmentStore } from '../../stores/appointments';
const props=defineProps({status:String});const store=useAppointmentStore();
const definition=computed(()=>store.options?.statuses?.[props.status]||[props.status?.replaceAll('_',' '),'gray']);
</script>
<template><span class="appointment-badge" :class="`appointment-color-${definition[1]}`">{{definition[0]}}</span></template>
