<script setup>
import { computed, ref } from 'vue';
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { usePlatformSettingsStore } from '../stores/platformSettings';
import AppIcon from '../components/ui/AppIcon.vue';

const auth = useAuthStore();
const platformSettings = usePlatformSettingsStore();
const route = useRoute();
const router = useRouter();
const menu = ref(false);
const collapsed = ref(localStorage.getItem('admin-sidebar-collapsed') === 'true');
const tooltip = ref({ visible: false, label: '', top: 0, left: 0 });
const groups = [
    { label: null, links: [['admin.dashboard', 'Dashboard', 'dashboard']] },
    { label: 'Business', links: [['admin.clinics', 'Businesses / Tenants', 'clinics'], ['admin.business-types', 'Business Types', 'clinics']] },
    { label: 'Billing', links: [['admin.plans', 'Subscription Plans', 'plans'], ['admin.subscriptions', 'Subscriptions', 'subscriptions']] },
    { label: 'Access', links: [['admin.users', 'Users', 'members'], ['admin.roles', 'Roles & Permissions', 'roles']] },
    { label: 'Communication', links: [['admin.support', 'Support Tickets', 'support'], ['admin.whatsapp', 'WhatsApp', 'support']] },
    { label: 'System', links: [['admin.audit', 'Audit Log', 'audit'], ['admin.settings', 'System Settings', 'settings']] },
];
const visibleGroups = computed(() => groups.map(group => ({
    ...group,
    links: group.links.filter(([name]) => (name !== 'admin.support' || auth.user?.platform_permissions?.includes('support_tickets.view')) && (name !== 'admin.whatsapp' || auth.user?.platform_permissions?.includes('platform_whatsapp.view'))),
})).filter(group => group.links.length));
const title = computed(() => route.meta.title || 'Platform administration');

async function logout() {
    await auth.logout();
    await router.replace('/app/login');
}
function toggleCollapsed() { collapsed.value = !collapsed.value; localStorage.setItem('admin-sidebar-collapsed', String(collapsed.value)); }
function showTooltip(event, label) {
    if (!collapsed.value || !window.matchMedia('(min-width: 1024px)').matches) return;
    const bounds = event.currentTarget.getBoundingClientRect();
    tooltip.value = { visible: true, label, top: bounds.top + bounds.height / 2, left: bounds.right + 12 };
}
function hideTooltip() { tooltip.value.visible = false; }
</script>

<template>
    <div class="admin-shell">
        <div v-if="menu" class="fixed inset-0 z-30 bg-slate-950/40 lg:hidden" @click="menu = false"></div>
        <aside :class="['admin-sidebar', collapsed ? 'lg:w-20' : 'lg:w-64', menu ? 'translate-x-0' : '-translate-x-full lg:translate-x-0']">
            <div :class="['flex h-[4.5rem] shrink-0 items-center gap-3 border-b border-white/10',collapsed?'lg:justify-center lg:px-3':'px-5']">
                <div class="grid size-11 shrink-0 place-items-center overflow-hidden rounded-2xl bg-white text-xl text-teal-800 shadow-sm"><img v-if="platformSettings.smallLogo" :src="platformSettings.smallLogo" alt=""><span v-else>&hearts;</span></div>
                <div :class="collapsed?'lg:hidden':''"><p class="truncate text-lg font-bold tracking-tight">{{ platformSettings.name }}</p><p class="truncate text-[11px] text-teal-100">{{ platformSettings.footer }}</p></div>
            </div>
            <nav class="admin-navigation min-h-0 max-w-full flex-1 overflow-x-hidden overflow-y-auto overscroll-contain px-3 py-3" aria-label="Platform administration">
                <section v-for="group in visibleGroups" :key="group.label || 'primary'" class="admin-nav-group">
                    <p v-if="group.label" :class="['admin-nav-heading', collapsed ? 'lg:hidden' : '']">{{ group.label }}</p>
                    <RouterLink v-for="[name, label, icon] in group.links" :key="name" :to="{ name }" :aria-label="collapsed ? label : undefined" :class="['admin-nav', collapsed?'lg:justify-center lg:px-2':'', route.name === name || route.name?.startsWith(name + '.') ? 'admin-nav-active' : '']" @click="menu = false" @mouseenter="showTooltip($event, label)" @mouseleave="hideTooltip" @focus="showTooltip($event, label)" @blur="hideTooltip">
                        <span class="admin-nav-icon"><AppIcon :name="icon" :size="19"/></span><span :class="collapsed?'lg:hidden':''">{{ label }}</span>
                    </RouterLink>
                </section>
            </nav>
            <div class="shrink-0 border-t border-white/10 p-3">
                <div :class="['flex items-center gap-3 rounded-xl bg-white/10 p-2.5',collapsed?'lg:justify-center lg:p-2':'']">
                    <div class="grid size-10 place-items-center rounded-full bg-white font-bold text-teal-800">{{ auth.user?.name?.charAt(0) }}</div>
                    <div :class="['min-w-0',collapsed?'lg:hidden':'']"><p class="truncate text-sm font-semibold">{{ auth.user?.name }}</p><p class="text-xs text-teal-100">Platform Administrator</p></div>
                </div>
                <button :class="['mt-1.5 flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm text-teal-50 transition hover:bg-white/10',collapsed?'lg:justify-center lg:px-1':'']" aria-label="Sign out" @click="logout" @mouseenter="showTooltip($event, 'Sign out')" @mouseleave="hideTooltip" @focus="showTooltip($event, 'Sign out')" @blur="hideTooltip"><AppIcon name="chevronLeft" :size="18"/><span :class="collapsed?'lg:hidden':''">Sign out</span></button>
            </div>
        </aside>
        <div :class="['min-w-0 transition-[padding] duration-200',collapsed?'lg:pl-20':'lg:pl-64']">
            <header class="admin-topbar">
                <button class="rounded-lg border px-3 py-2 text-sm lg:hidden" aria-label="Open menu" @click="menu = true">Menu</button>
                <button class="hidden rounded-lg border border-slate-200 p-2 text-slate-600 hover:bg-slate-100 lg:grid" :aria-label="collapsed?'Expand sidebar':'Collapse sidebar'" @click="toggleCollapsed"><AppIcon :name="collapsed?'chevronRight':'chevronLeft'"/></button>
                <div class="hidden max-w-xl flex-1 sm:block"><input class="w-full rounded-xl border-0 bg-slate-100 px-4 py-2.5 text-sm" :placeholder="'Search ' + title.toLowerCase()" disabled></div>
                <div class="ml-auto flex items-center gap-3">
                    <div class="grid size-10 place-items-center rounded-full bg-teal-800 font-semibold text-white">{{ auth.user?.name?.charAt(0) }}</div>
                    <div class="hidden sm:block"><p class="text-sm font-semibold">{{ auth.user?.name }}</p><p class="text-xs text-slate-500">Platform Admin</p></div>
                </div>
            </header>
            <main class="p-4 sm:p-6 lg:p-8"><div class="mx-auto max-w-[1500px]"><RouterView /></div></main>
        </div>
        <Teleport to="body">
            <Transition name="admin-tooltip">
                <div v-if="tooltip.visible" class="admin-fixed-tooltip" role="tooltip" :style="{ top: `${tooltip.top}px`, left: `${tooltip.left}px` }">{{ tooltip.label }}</div>
            </Transition>
        </Teleport>
    </div>
</template>
