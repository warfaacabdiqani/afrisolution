<script setup>
import { computed, ref, watch, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useClinicContextStore } from '../stores/clinicContext';
import { useClinicSettingsStore } from '../stores/clinicSettings';
import { useAuthStore } from '../stores/auth';
import AppIcon from '../components/ui/AppIcon.vue';
import FormErrors from '../components/ui/FormErrors.vue';
const clinic = useClinicContextStore();
const auth = useAuthStore();
const router = useRouter();
const route = useRoute();
const drawer = ref(false);
let idleTimer, heartbeatTimer, lastInteraction = Date.now(), lastHeartbeat = Date.now();
const idleDuration = () => (clinic.data?.idle_timeout_minutes || 120) * 60000;
const navigationSections = computed(() => {
    const lookup = new Map((clinic.data?.modules || []).filter(module => module.allowed).map(module => [module.key, module]));
    const profile = clinic.navigationProfileKey ? clinic.businessProfile?.navigation_profile : [];
    if (Array.isArray(profile) && profile.length) {
        const sections = profile.map((section) => ({
            label: section.label,
            items: (section.items || []).map((key) => lookup.get(key)).filter(Boolean).map((module) => ({
                key: module.key,
                label: module.label,
                to: `/app/${module.key}`,
                icon: module.icon,
            })),
        })).filter(section => section.items.length);
        if (clinic.data?.features?.whatsapp_notifications && clinic.hasBusinessModule('settings') && clinic.can('whatsapp.view')) {
            const administration = sections.findIndex(section => section.label.toLowerCase() === 'administration');
            sections.splice(administration < 0 ? sections.length : administration, 0, {
                label: 'Communications', items: [{ key: 'whatsapp', label: 'WhatsApp', icon: 'support', children: [
                    { key: 'messages', label: 'Messages', to: '/app/whatsapp/messages' },
                    { key: 'templates', label: 'Templates', to: '/app/whatsapp/templates' },
                ] }],
            });
        }
        return sections;
    }
    return [{
        label: '',
        items: (clinic.data?.modules || []).filter(module => module.allowed).map((module) => ({
            key: module.key,
            label: module.label,
            to: `/app/${module.key}`,
            icon: module.icon,
        })),
    }];
});
async function expireSession() {
    try { await auth.logout(); } catch { auth.reset(); } finally { clinic.$reset(); await router.replace('/app/login'); }
}
function interaction() {
    lastInteraction = Date.now(); clearTimeout(idleTimer);
    if (clinic.data?.operational) idleTimer = setTimeout(expireSession, idleDuration());
}
watch(() => clinic.data?.idle_timeout_minutes, interaction);
onMounted(() => {
    for (const event of ['pointerdown','keydown','touchstart']) window.addEventListener(event, interaction, { passive: true });
    interaction();
    heartbeatTimer = setInterval(async () => {
        if (clinic.data?.operational && lastInteraction > lastHeartbeat && Date.now() - lastInteraction < idleDuration()) {
            lastHeartbeat = Date.now();
            try { await clinic.heartbeat(); } catch (e) { if (e.response?.status !== 401) error.value = e; }
        }
    }, 60000);
});
onUnmounted(() => { clearTimeout(idleTimer); clearInterval(heartbeatTimer); for(const event of ['pointerdown','keydown','touchstart']) window.removeEventListener(event, interaction); });
watch(() => route.fullPath, () => { drawer.value = false; });
const error = ref(null);
const trialDays = computed(() => Math.max(0, Math.ceil((new Date(clinic.data?.subscription?.trial_ends_at) - new Date()) / 86400000)));
async function switchBranch(event) {
    if (!(await (useClinicSettingsStore().beforeExit?.() ?? true))) { event.target.value = clinic.data.branch.id; return; }
    try { await clinic.switchBranch(Number(event.target.value)); await router.replace('/app/dashboard'); }
    catch (e) { error.value = e; }
}
async function logout() {
    if (!(await (useClinicSettingsStore().beforeExit?.() ?? true))) return;
    try { await auth.logout(); clinic.$reset(); await router.replace('/app/login'); }
    catch (e) { error.value = e; }
}
</script>
<template>
    <div class="clinic-shell" @keydown.esc="drawer = false">
        <button v-if="drawer" class="clinic-overlay" aria-label="Close navigation" @click="drawer = false"></button>
        <aside class="clinic-sidebar" :class="{ 'is-open': drawer }">
            <RouterLink class="clinic-brand" to="/app/dashboard"><span class="clinic-logo"><AppIcon name="activity" :size="28" /></span><span>{{ clinic.data?.clinic.name || 'Clinic workspace' }}<small>{{ clinic.businessProfile?.subtitle || 'Business workspace' }}</small></span></RouterLink>
            <div class="clinic-sidebar-scroll">
            <nav class="clinic-navigation" aria-label="Business navigation">
                <template v-for="(section, sectionIndex) in navigationSections" :key="section.label || sectionIndex">
                    <p v-if="section.label" class="clinic-nav-label">{{ section.label }}</p>
                    <template v-for="item in section.items" :key="item.key">
                        <div v-if="item.children" class="clinic-nav-label" style="margin-top: 8px; display: flex; align-items: center; gap: 10px"><AppIcon :name="item.icon" />{{ item.label }}</div>
                        <template v-if="item.children"><RouterLink v-for="child in item.children" :key="child.key" :to="child.to" :class="{ selected: route.path === child.to }" style="padding-left: 42px" @click="drawer = false">{{ child.label }}</RouterLink></template>
                        <RouterLink v-else :to="item.to" :class="{ selected: route.path.split('/')[2] === item.key }" @click="drawer = false"><AppIcon :name="item.icon" />{{ item.label }}</RouterLink>
                    </template>
                </template>
            </nav>
            <div class="clinic-sidebar-bottom">
                <small>MY BUSINESS</small><div class="clinic-current"><AppIcon name="clinics" /><span>{{ clinic.data?.clinic.name || 'Select clinic' }}<small>{{ clinic.data?.branch?.name || 'No branch selected' }}</small></span></div>
                <RouterLink to="/app/clinics?switch=1">＋ Switch Business</RouterLink>
                <RouterLink v-if="clinic.allowed('support')" to="/app/support"><AppIcon name="roles" />Help &amp; Support</RouterLink>
                <button @click="logout"><AppIcon name="chevronLeft" />Sign out</button>
            </div>
            </div>
        </aside>
        <div class="clinic-workspace">
            <header class="clinic-topbar">
                <button class="clinic-menu" aria-label="Open navigation" :aria-expanded="drawer" @click="drawer = !drawer"><AppIcon name="menu" /></button>
                <details class="clinic-switcher">
                    <summary><AppIcon name="clinics" :size="25" /><span><strong>{{ clinic.data?.clinic.name || 'Clinic workspace' }}</strong><small>{{ clinic.data?.branch?.name || 'Select a branch' }}</small></span><span>⌄</span></summary>
                    <div class="clinic-switcher-panel"><small>CURRENT BUSINESS</small><strong>{{ clinic.data?.clinic.name }}</strong><label class="field">Branch<select :value="clinic.data?.branch?.id" :disabled="clinic.busy || !clinic.data?.operational" @change="switchBranch"><option v-for="branch in clinic.data?.branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option></select></label><RouterLink to="/app/clinics?switch=1">Switch Business →</RouterLink></div>
                </details>
                <div class="clinic-user"><span class="clinic-avatar">{{ auth.user?.name?.slice(0, 1) }}</span><span><strong>{{ auth.user?.name }}</strong><small>{{ clinic.data?.role?.replaceAll('_', ' ') || 'Clinic member' }}</small></span></div>
            </header>
            <div class="clinic-content">
                <FormErrors :error="error || clinic.error" />
                <RouterView v-if="route.meta.clinicSelection" />
                <div v-else-if="clinic.error" class="clinic-panel"><p>Unable to load your clinic.</p><button class="btn mt-4" @click="clinic.load()">Try again</button><RouterLink class="ml-4" to="/app/clinics?switch=1">Switch clinic</RouterLink></div>
                <div v-else-if="!clinic.data" class="clinic-panel" role="status">Loading clinic…</div>
                <div v-else-if="!clinic.data.operational && route.name !== 'clinic.access'" class="clinic-panel"><AppIcon name="roles" :size="36" /><h1 class="mt-4 text-2xl font-bold">Clinic access restricted</h1><p class="mt-3 text-slate-500">{{ clinic.data.restriction }}</p><p class="mt-3">Subscription: {{ clinic.data.subscription?.status || 'Unavailable' }}</p><RouterLink class="mt-5 inline-block btn" to="/app/clinics?switch=1">Switch clinic</RouterLink></div>
                <template v-else>
                    <div v-if="clinic.data.subscription?.status === 'trial'" class="clinic-trial"><AppIcon name="trial" />Your trial ends in {{ trialDays }} {{ trialDays === 1 ? 'day' : 'days' }}.<span>{{ clinic.data.plan?.name }} plan</span></div>
                    <RouterView :key="`${clinic.data.clinic.id}:${clinic.data.branch?.id}:${route.path}`" />
                </template>
            </div>
        </div>
    </div>
</template>
