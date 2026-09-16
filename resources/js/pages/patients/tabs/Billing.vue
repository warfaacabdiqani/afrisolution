<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import Ledger from '../../billing/Ledger.vue';
const route = useRoute();
const customer = computed(() => ({ customer_type: 'patient', customer_id: Number(route.params.id) }));
</script>
<template><Ledger :customer="customer"/></template>
