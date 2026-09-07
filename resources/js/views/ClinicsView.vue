<script setup>
import { ref } from 'vue';
import { useAuthStore } from '../stores/auth';
import FormErrors from '../components/ui/FormErrors.vue';
import AppIcon from '../components/ui/AppIcon.vue';
import { useRouter } from 'vue-router';
const router = useRouter();
const auth = useAuthStore();
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
    <section>
        <p class="text-sm font-semibold text-teal-700">Your workspace</p>
        <h1 class="mt-2 text-3xl font-bold tracking-tight">Choose your clinic</h1>
        <p class="mt-3 text-slate-500">Open a clinic to access its dashboard and care workflows.</p>
        <FormErrors class="mt-4" :error="error" />
        <p v-if="!auth.user.clinics.length" class="mt-6 rounded-xl border border-slate-200 bg-white p-6 text-slate-600">You have no active clinic memberships. Contact your platform administrator.</p>
        <div class="mt-7 grid gap-4 sm:grid-cols-2">
            <button v-for="clinic in auth.user.clinics" :key="clinic.id" class="group rounded-2xl border border-slate-200 bg-white p-6 text-left shadow-sm transition hover:border-teal-500 hover:shadow-md disabled:opacity-50" :disabled="busy" @click="select(clinic.id)">
                <span class="flex items-center justify-between"><span class="grid size-12 place-items-center rounded-xl bg-teal-50 text-teal-700"><AppIcon name="clinics" :size="26" /></span><span v-if="clinic.id === auth.user.active_tenant_id" class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Current clinic</span></span>
                <strong class="mt-5 block text-xl">{{ clinic.name }}</strong>
                <span class="mt-1 block text-sm capitalize text-slate-500">{{ clinic.role }}</span>
                <span class="mt-6 flex items-center gap-2 text-sm font-semibold text-teal-700">Open dashboard <AppIcon name="chevronRight" :size="17" /></span>
            </button>
        </div>
        <p v-if="busy" class="mt-4 text-sm text-teal-700" role="status">Opening your clinic...</p>
    </section>
</template>
