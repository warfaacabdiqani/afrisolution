<script setup>
import { inject, reactive, watch } from 'vue';
import api from '../../../../services/api';
const state = inject('clinicAdmin');
const form = reactive({});
watch(state.subscription, value => Object.assign(form, value, {
    trial_ends_at: value?.trial_ends_at ? new Date(value.trial_ends_at).toISOString().slice(0, 16) : '',
}), { immediate: true });
async function save() {
    await state.run(async () => {
        await api.put('/v1/platform/tenants/' + state.clinic.value.id + '/subscription', {
            plan_id: form.plan_id,
            status: form.status,
            trial_ends_at: form.status === 'trial' ? form.trial_ends_at + ':00Z' : null,
        });
        await state.load(state.clinic.value.id);
    }, 'Subscription updated.');
}
</script>
<template>
    <section class="admin-card max-w-3xl">
        <h2 class="text-lg font-bold">Subscription</h2>
        <p class="mt-1 text-sm text-slate-500">Manage the clinic access entitlement. Payment collection is not implemented.</p>
        <form class="form-grid mt-6" @submit.prevent="save">
            <label class="field">Current Plan<select v-model="form.plan_id"><option v-for="plan in state.plans.value" :key="plan.id" :value="plan.id">{{ plan.name }}</option></select></label>
            <label class="field">Subscription Status<select v-model="form.status"><option>trial</option><option>active</option><option>cancelled</option></select></label>
            <label v-if="form.status === 'trial'" class="field">Trial End<input v-model="form.trial_ends_at" type="datetime-local" required></label>
            <button class="btn self-end">Save Subscription</button>
        </form>
    </section>
</template>
