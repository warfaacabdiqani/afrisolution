<script setup>
import { RouterLink } from 'vue-router';
import BaseCard from '../components/ui/BaseCard.vue';
</script>

<template>
    <BaseCard>
        <h1 class="text-2xl font-semibold">Page not found</h1>
        <p class="mt-3 text-slate-600">This page is not available.</p>
        <RouterLink :to="{ name: 'home' }" class="mt-6 inline-block font-medium text-teal-800 underline">
            Return home
        </RouterLink>
    </BaseCard>
</template>
