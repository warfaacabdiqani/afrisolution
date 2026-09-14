<script setup>
import DashboardStatCard from './DashboardStatCard.vue';
defineProps({ widgets: Array });
</script>
<template><div class="clinic-kpis"><DashboardStatCard v-for="widget in widgets" :key="widget.key" :widget="widget" /></div></template>
