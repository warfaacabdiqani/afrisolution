<script setup>
import { computed, onMounted, provide } from 'vue';
import { useRoute } from 'vue-router';
import { useClinicAdmin } from '../../../composables/useClinicAdmin';
import ClinicStatusBadge from '../../../components/admin/ClinicStatusBadge.vue';
import FormErrors from '../../../components/ui/FormErrors.vue';

const route = useRoute();
const state = useClinicAdmin();
provide('clinicAdmin', state);
onMounted(() => state.load(route.params.id));
const status = computed(() => state.clinic.value?.status === 'suspended' ? 'suspended' : state.subscription.value?.status);
const tabs = [
    ['admin.clinics.show','Overview'], ['admin.clinics.subscription','Subscription'],
    ['admin.clinics.branches','Branches'], ['admin.clinics.members','Members'],
    ['admin.clinics.usage','Usage'], ['admin.clinics.activity','Activity'],
    ['admin.clinics.settings','Settings'],
];
const format = value => value ? new Intl.DateTimeFormat(undefined,{dateStyle:'medium'}).format(new Date(value)) : '-';
</script>
<template>
    <div>
        <p v-if="state.loading.value" class="loading-state">Loading clinic...</p>
        <FormErrors v-else-if="state.error.value && !state.clinic.value" :error="state.error.value" />
        <template v-else-if="state.clinic.value">
            <header class="page-header">
                <div>
                    <RouterLink class="back-link" :to="{name:'admin.clinics'}">Back to Clinics</RouterLink>
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <h1>{{ state.clinic.value.name }}</h1>
                        <ClinicStatusBadge :status="status" />
                    </div>
                    <p>Clinic code: <strong>{{ state.clinic.value.slug }}</strong></p>
                </div>
            </header>
            <p v-if="route.query.created || state.notice.value" class="mb-5 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">
                {{ state.notice.value || 'Clinic created successfully.' }}
            </p>
            <FormErrors v-if="state.error.value" :error="state.error.value" />
            <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <article class="summary-card"><p>Subscription</p><strong>{{ state.clinic.value.plan_name || '-' }}</strong></article>
                <article class="summary-card"><p>Members</p><strong>{{ state.members.value.length }}</strong></article>
                <article class="summary-card"><p>Branches</p><strong>{{ state.branches.value.length }}</strong></article>
                <article class="summary-card"><p>Patients / Usage</p><strong>Not available</strong></article>
                <article class="summary-card"><p>Trial Ends</p><strong>{{ format(state.subscription.value?.trial_ends_at) }}</strong></article>
            </div>
            <nav class="clinic-tabs" aria-label="Clinic management">
                <RouterLink v-for="[name,label] in tabs" :key="name" :to="{name,params:{id:route.params.id}}">{{ label }}</RouterLink>
            </nav>
            <RouterView />
        </template>
    </div>
</template>
