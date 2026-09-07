<script setup>
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useClinicContextStore } from '../stores/clinicContext';
import { useAuthStore } from '../stores/auth';
import AppIcon from '../components/ui/AppIcon.vue';
import FormErrors from '../components/ui/FormErrors.vue';
const clinic = useClinicContextStore();
const auth = useAuthStore();
const router = useRouter();
const route = useRoute();
const drawer = ref(false);
const error = ref(null);
const trialDays = computed(() => Math.max(0, Math.ceil((new Date(clinic.data?.subscription?.trial_ends_at) - new Date()) / 86400000)));
async function switchBranch(event) {
    try { await clinic.switchBranch(Number(event.target.value)); await router.replace('/app/dashboard'); }
    catch (e) { error.value = e; }
}
async function logout() {
    try { await auth.logout(); clinic.$reset(); await router.replace('/app/login'); }
    catch (e) { error.value = e; }
}
</script>
<template>
    <div class="clinic-shell" @keydown.esc="drawer = false">
        <button v-if="drawer" class="clinic-overlay" aria-label="Close navigation" @click="drawer = false"></button>
        <aside class="clinic-sidebar" :class="{ 'is-open': drawer }">
            <RouterLink class="clinic-brand" to="/app/dashboard"><span class="clinic-logo"><AppIcon name="activity" :size="28" /></span><span>Afri Clinic<small>Healthcare SaaS</small></span></RouterLink>
            <nav class="clinic-navigation" aria-label="Clinic navigation">
                <template v-for="(item, index) in clinic.modules" :key="item.key">
                    <p v-if="item.group && clinic.modules[index - 1]?.group !== item.group" class="clinic-nav-label">{{ item.group }}</p>
                    <RouterLink :to="`/app/${item.key}`" :class="{ selected: route.path.split('/')[2] === item.key }" @click="drawer = false"><AppIcon :name="item.icon" />{{ item.label }}</RouterLink>
                </template>
            </nav>
            <div class="clinic-sidebar-bottom">
                <small>MY CLINIC</small><div class="clinic-current"><AppIcon name="clinics" /><span>{{ clinic.data?.clinic.name || 'Select clinic' }}<small>{{ clinic.data?.branch?.name || 'No branch selected' }}</small></span></div>
                <RouterLink to="/app/clinics?switch=1">＋ Switch Clinic</RouterLink>
                <RouterLink to="/app/support"><AppIcon name="roles" />Help &amp; Support</RouterLink>
                <button @click="logout"><AppIcon name="chevronLeft" />Sign out</button>
            </div>
        </aside>
        <div class="clinic-workspace">
            <header class="clinic-topbar">
                <button class="clinic-menu" aria-label="Open navigation" :aria-expanded="drawer" @click="drawer = !drawer"><AppIcon name="menu" /></button>
                <details class="clinic-switcher">
                    <summary><AppIcon name="clinics" :size="25" /><span><strong>{{ clinic.data?.clinic.name || 'Clinic workspace' }}</strong><small>{{ clinic.data?.branch?.name || 'Select a branch' }}</small></span><span>⌄</span></summary>
                    <div class="clinic-switcher-panel"><small>CURRENT CLINIC</small><strong>{{ clinic.data?.clinic.name }}</strong><label class="field">Branch<select :value="clinic.data?.branch?.id" :disabled="clinic.busy || !clinic.data?.operational" @change="switchBranch"><option v-for="branch in clinic.data?.branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option></select></label><RouterLink to="/app/clinics?switch=1">Switch Clinic →</RouterLink></div>
                </details>
                <div class="clinic-user"><span class="clinic-avatar">{{ auth.user?.name?.slice(0, 1) }}</span><span><strong>{{ auth.user?.name }}</strong><small>{{ clinic.data?.role?.replaceAll('_', ' ') || 'Clinic member' }}</small></span></div>
            </header>
            <div class="clinic-content">
                <FormErrors :error="error || clinic.error" />
                <div v-if="clinic.error" class="clinic-panel"><p>Unable to load your clinic.</p><button class="btn mt-4" @click="clinic.load()">Try again</button><RouterLink class="ml-4" to="/app/clinics?switch=1">Switch clinic</RouterLink></div>
                <div v-else-if="!clinic.data" class="clinic-panel" role="status">Loading clinic…</div>
                <div v-else-if="!clinic.data.operational" class="clinic-panel"><AppIcon name="roles" :size="36" /><h1 class="mt-4 text-2xl font-bold">Clinic access restricted</h1><p class="mt-3 text-slate-500">{{ clinic.data.restriction }}</p><p class="mt-3">Subscription: {{ clinic.data.subscription?.status || 'Unavailable' }}</p><RouterLink class="mt-5 inline-block btn" to="/app/clinics?switch=1">Switch clinic</RouterLink></div>
                <template v-else>
                    <div v-if="clinic.data.subscription?.status === 'trial'" class="clinic-trial"><AppIcon name="trial" />Your trial ends in {{ trialDays }} {{ trialDays === 1 ? 'day' : 'days' }}.<span>{{ clinic.data.plan?.name }} plan</span></div>
                    <RouterView :key="`${clinic.data.clinic.id}:${clinic.data.branch.id}:${route.fullPath}`" />
                </template>
            </div>
        </div>
    </div>
</template>
