<script setup>
import { useClinicContextStore } from '../../stores/clinicContext';
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import AppIcon from '../../components/ui/AppIcon.vue';
const route = useRoute();
const context = useClinicContextStore();
const reason = computed(() => route.query.reason || 'COMING_SOON');
const moduleLabel = computed(() => context.data?.modules.find(m => m.key === route.query.module)?.label || route.meta.title || 'This module');
const title = computed(() => ({
    BUSINESS_MODULE_UNAVAILABLE: 'Module Not Available',
    PLAN_FEATURE_UNAVAILABLE: 'Plan feature unavailable',
    PERMISSION_DENIED: 'Permission required',
    SUBSCRIPTION_INACTIVE: 'Subscription inactive',
    COMING_SOON: 'Module coming soon',
}[reason.value] || 'Module Not Available'));
const message = computed(() => ({
    BUSINESS_MODULE_UNAVAILABLE: moduleLabel.value + ' are not available for ' + (context.businessType?.name || 'this business type') + ' businesses.',
    PLAN_FEATURE_UNAVAILABLE: 'This module is not included in your current subscription plan.',
    PERMISSION_DENIED: 'Your role does not currently have permission to access this module.',
    SUBSCRIPTION_INACTIVE: context.data?.restriction || 'Your subscription is inactive. Contact your administrator.',
    COMING_SOON: moduleLabel.value + ' workflows for this business are not implemented yet.',
}[reason.value] || 'This module is unavailable.'));
const icon = computed(() => reason.value === 'PERMISSION_DENIED' ? 'roles' : 'activity');
</script>
<template>
    <section class="clinic-panel max-w-2xl">
        <AppIcon :name="icon" :size="36" />
        <h1 class="mt-5 text-2xl font-bold">{{ title }}</h1>
        <p class="mt-3 leading-7 text-slate-500">{{ message }}</p>
        <RouterLink to="/app/dashboard" class="btn mt-6 inline-block">Back to dashboard</RouterLink>
    </section>
</template>
