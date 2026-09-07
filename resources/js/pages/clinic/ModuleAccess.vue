<script setup>
import { useRoute } from 'vue-router';
import AppIcon from '../../components/ui/AppIcon.vue';
const route = useRoute();
</script>
<template>
    <section class="clinic-panel max-w-2xl">
        <AppIcon :name="route.meta.denied ? 'roles' : 'activity'" :size="36" />
        <h1 class="mt-5 text-2xl font-bold">{{ route.meta.denied ? 'Access unavailable' : route.meta.title }}</h1>
        <p class="mt-3 leading-7 text-slate-500">{{ route.meta.denied ? 'Your permissions or subscription plan do not allow access to this page.' : route.meta.support ? 'For account access, branch assignments, or subscription assistance, contact your clinic administrator. Clinical modules will be introduced in upcoming releases.' : 'This module is not available yet. Your clinic dashboard is ready; this workflow will be introduced in an upcoming release.' }}</p>
        <RouterLink to="/app/dashboard" class="btn mt-6 inline-block">Back to dashboard</RouterLink>
    </section>
</template>
