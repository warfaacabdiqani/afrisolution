<script setup>
import { ref, onMounted } from 'vue';
import { useAuthStore } from '../stores/auth';
import api from '../services/api';
import BaseCard from '../components/ui/BaseCard.vue';
import FormErrors from '../components/ui/FormErrors.vue';
const auth = useAuthStore();
const branches = ref([]);
const busy = ref(false);
const error = ref(null);
async function load() {
    branches.value = []; error.value = null; busy.value = true;
    try { if (auth.user.active_tenant_id) branches.value = (await api.get('/v1/clinic/branches')).data.data; }
    catch(e) { error.value = e; }
    finally { busy.value = false; }
}
async function select(id) {
    branches.value = []; error.value = null; busy.value = true;
    try { await auth.selectClinic(id); await load(); }
    catch(e) { error.value = e; busy.value = false; }
}
onMounted(load);
</script>
<template>
    <BaseCard>
        <h1 class="text-2xl font-semibold">Your clinics</h1>
        <FormErrors class="mt-4" :error="error" />
        <p v-if="!auth.user.clinics.length" class="mt-4 text-slate-600">You have no active clinic memberships. Contact your platform administrator.</p>
        <div class="mt-5 flex flex-wrap gap-3">
            <button v-for="clinic in auth.user.clinics" :key="clinic.id" class="btn" :disabled="busy" :aria-pressed="clinic.id === auth.user.active_tenant_id" @click="select(clinic.id)">
                {{ clinic.name }} {{ clinic.id === auth.user.active_tenant_id ? '(selected)' : '' }}
            </button>
        </div>
        <p v-if="busy" class="mt-4" role="status">Loading clinic…</p>
        <div v-else-if="branches.length" class="mt-6">
            <h2 class="font-semibold">Branches</h2>
            <ul class="mt-3 divide-y divide-slate-100"><li v-for="branch in branches" :key="branch.id" class="py-3">{{ branch.name }}</li></ul>
        </div>
    </BaseCard>
</template>

