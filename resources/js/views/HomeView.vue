<script setup>
import BaseCard from '../components/ui/BaseCard.vue';
import { RouterLink } from 'vue-router';
</script>

<template>
    <BaseCard>
        <h1 class="text-3xl font-semibold tracking-tight">Welcome to Afri Clinic</h1>
        <p class="mt-4 max-w-2xl leading-7 text-slate-600">
            Sign in to access your clinic workspace or manage clinics and subscriptions.
            Clinical modules will be added after the administration foundation.
        </p>
        <RouterLink to="/app/login" class="btn mt-6 inline-block">Continue to your workspace</RouterLink>
    </BaseCard>
</template>
