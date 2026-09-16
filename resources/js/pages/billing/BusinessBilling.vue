<script setup>
import { useClinicContextStore } from '../../stores/clinicContext';
import Ledger from './Ledger.vue';
const context=useClinicContextStore();
</script>
<template><Ledger v-if="context.allowed('billing')"/></template>
