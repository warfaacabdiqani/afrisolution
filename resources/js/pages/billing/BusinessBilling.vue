<script setup>
import { useClinicContextStore } from '../../stores/clinicContext';
import Ledger from './Ledger.vue';
import ClinicBilling from './Index.vue';
const context=useClinicContextStore();
</script>
<template><Ledger v-if="context.businessTypeSlug==='beauty-salon'" :key="context.data.clinic.id"/><ClinicBilling v-else/></template>
