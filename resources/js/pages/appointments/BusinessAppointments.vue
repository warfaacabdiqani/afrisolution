<script setup>
import { defineAsyncComponent } from 'vue';
import { useClinicContextStore } from '../../stores/clinicContext';
const props = defineProps({ form: Boolean });
const context = useClinicContextStore();
const ClinicIndex = defineAsyncComponent(() => import('./Index.vue'));
const ClinicForm = defineAsyncComponent(() => import('./FormPage.vue'));
const SalonIndex = defineAsyncComponent(() => import('../salon/bookings/Index.vue'));
const SalonForm = defineAsyncComponent(() => import('../salon/bookings/Form.vue'));
</script>
<template><component :is="context.businessTypeSlug === 'beauty-salon' ? (props.form ? SalonForm : SalonIndex) : (props.form ? ClinicForm : ClinicIndex)" :key="`${context.data?.clinic.id}:${context.data?.branch.id}`" /></template>
