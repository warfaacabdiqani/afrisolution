<script setup>
import AppIcon from '../ui/AppIcon.vue';
import DashboardSection from './DashboardSection.vue';
defineProps({ actions: Array });
</script>
<template><DashboardSection v-if="actions.length" title="Quick Actions"><div class="clinic-quick-actions"><RouterLink v-for="action in actions" :key="action.key" :to="action.to" :class="action.tone"><AppIcon :name="action.icon" :size="27" /><strong>{{ action.label }}</strong><small>{{ action.description }}</small></RouterLink></div></DashboardSection></template>
