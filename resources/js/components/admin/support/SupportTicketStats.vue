<script setup>
import AppIcon from '../../ui/AppIcon.vue';
defineProps({ stats: { type: Object, default: null } });
const cards = [['open', 'Open', 'stat-blue'], ['in_progress', 'In Progress', 'stat-mint'], ['waiting', 'Waiting for Customer', 'stat-violet'], ['resolved', 'Resolved', 'stat-mint'], ['total', 'Total Tickets', 'stat-blue']];
</script>
<template><div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5"><article v-for="[key, label, tone] in cards" :key="key" :class="['stat-card', tone]"><span class="stat-icon"><AppIcon name="support" :size="24" /></span><div><p>{{ label }}</p><strong>{{ stats ? stats[key] : '—' }}</strong></div></article></div></template>
