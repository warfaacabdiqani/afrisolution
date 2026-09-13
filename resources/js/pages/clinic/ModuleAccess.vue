<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import AppIcon from '../../components/ui/AppIcon.vue';
const route = useRoute();
const reason = computed(() => route.query.reason || 'business');
const title = computed(() => {
    if (route.meta.support) return 'Support unavailable';
    if (reason.value === 'permission') return 'Permission required';
    if (reason.value === 'feature') return 'Plan feature unavailable';
    return 'Module unavailable';
});
const message = computed(() => {
    if (route.meta.support) {
        return 'For account access, branch assignments, or subscription assistance, contact your clinic administrator. Clinical modules will be introduced in upcoming releases.';
    }
    if (reason.value === 'permission') {
        return 'Your role does not currently have permission to access this module.';
    }
    if (reason.value === 'feature') {
        return 'This module is not included in your current subscription plan.';
    }
    return 'This workspace is not configured to use this business module.';
});
const icon = computed(() => (reason.value === 'permission' ? 'roles' : reason.value === 'feature' ? 'revenue' : 'activity'));
</script>
<template>
    <section class="clinic-panel max-w-2xl">
        <AppIcon :name="icon" :size="36" />
        <h1 class="mt-5 text-2xl font-bold">{{ title }}</h1>
        <p class="mt-3 leading-7 text-slate-500">{{ message }}</p>
        <RouterLink to="/app/dashboard" class="btn mt-6 inline-block">Back to dashboard</RouterLink>
    </section>
</template>
