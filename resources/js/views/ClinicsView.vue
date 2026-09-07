<script setup>
import { computed, ref } from 'vue';
import { useClinicContextStore } from '../stores/clinicContext';
import { useAuthStore } from '../stores/auth';
import FormErrors from '../components/ui/FormErrors.vue';
import AppIcon from '../components/ui/AppIcon.vue';
import { useRouter } from 'vue-router';
const router = useRouter();
const auth = useAuthStore();
const context = useClinicContextStore();
const search = ref('');
const clinics = computed(() => auth.user.clinics.filter(clinic => clinic.name.toLowerCase().includes(search.value.trim().toLowerCase())));
const busy = ref(false);
const error = ref(null);
async function select(id) {
    error.value = null; busy.value = true;
    try { await auth.selectClinic(id); await router.push('/app/dashboard'); }
    catch (e) { error.value = e; }
    finally { busy.value = false; }
}
</script>
<template>
    <section class="clinic-chooser">
        <div class="clinic-chooser-heading"><div>
        <p class="text-sm font-semibold text-teal-700">Your workspace</p>
        <h1 class="mt-2 text-3xl font-bold tracking-tight">Choose your clinic</h1>
        <p class="mt-3 text-slate-500">Open a clinic to access its dashboard and care workflows.</p>
        </div><RouterLink v-if="context.data?.operational" to="/app/dashboard" class="btn-secondary inline-flex items-center gap-2"><AppIcon name="chevronLeft" :size="16" />Back to dashboard</RouterLink></div>
        <FormErrors class="mt-4" :error="error" />
        <div class="clinic-chooser-banner"><span class="clinic-chooser-banner-icon"><AppIcon name="clinics" :size="32" /></span><div><small>CONNECTED WORKSPACES</small><h2>Your clinics, one place.</h2><p>Choose where you’ll be working today.</p></div><span class="clinic-chooser-count"><strong>{{ auth.user.clinics.length }}</strong>{{ auth.user.clinics.length === 1 ? 'Clinic available' : 'Clinics available' }}</span></div>
        <div class="clinic-panel clinic-chooser-panel"><div class="clinic-chooser-toolbar"><div><h2>Your clinics <span>{{ auth.user.clinics.length }}</span></h2><p>Workspaces you have access to</p></div><label v-if="auth.user.clinics.length > 1" class="field"><span class="sr-only">Search clinics</span><input v-model="search" type="search" placeholder="Search clinics..."></label></div>
        <p v-if="!auth.user.clinics.length" class="mt-6 rounded-xl border border-slate-200 bg-white p-6 text-slate-600">You have no active clinic memberships. Contact your platform administrator.</p>
        <div class="clinic-chooser-grid">
            <button v-for="clinic in clinics" :key="clinic.id" class="clinic-choice" :class="{ current: clinic.id === auth.user.active_tenant_id }" :disabled="busy" @click="select(clinic.id)">
                <span class="flex items-center justify-between"><span class="grid size-12 place-items-center rounded-xl bg-teal-50 text-teal-700"><AppIcon name="clinics" :size="26" /></span><span v-if="clinic.id === auth.user.active_tenant_id" class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Current clinic</span></span>
                <strong class="mt-5 block text-xl">{{ clinic.name }}</strong>
                <span class="mt-1 block text-sm capitalize text-slate-500">{{ clinic.role }}</span>
                <span class="clinic-choice-detail"><AppIcon name="roles" :size="16" />Authorized clinic member</span>
                <span v-if="clinic.id === context.data?.clinic.id && context.data?.branch" class="clinic-choice-detail"><AppIcon name="branch" :size="16" />{{ context.data.branch.name }}</span>
                <span class="clinic-choice-action">Open dashboard <AppIcon name="chevronRight" :size="17" /></span>
            </button>
        </div>
        <p v-if="auth.user.clinics.length && !clinics.length" class="empty-state">No clinics match your search.</p>
        <div class="clinic-chooser-footer"><AppIcon name="roles" :size="18" /><span>Missing a clinic? Ask your administrator to add you to its team.</span></div></div>
        <p v-if="busy" class="mt-4 text-sm text-teal-700" role="status">Opening your clinic...</p>
    </section>
</template>
