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
const links = [
    ['admin.dashboard', 'Dashboard', 'dashboard'],
    ['admin.clinics', 'Businesses / Tenants', 'clinics'],
    ['admin.business-types', 'Business Types', 'clinics'],
    ['admin.plans', 'Subscription Plans', 'plans'],
    ['admin.subscriptions', 'Subscriptions', 'subscriptions'],
    ['admin.users', 'Users', 'members'],
    ['admin.roles', 'Roles & Permissions', 'roles'],
    ['admin.support', 'Support Tickets', 'support'],
    ['admin.audit', 'Audit Log', 'audit'],
    ['admin.settings', 'System Settings', 'settings'],
];
const visibleLinks = computed(() => links.filter(([name]) => name !== 'admin.support' || auth.user?.platform_permissions?.includes('support_tickets.view')));
const title = computed(() => route.meta.title || 'Platform administration');

async function logout() {
    await auth.logout();
    await router.replace('/app/login');
}
function toggleCollapsed() { collapsed.value = !collapsed.value; localStorage.setItem('admin-sidebar-collapsed', String(collapsed.value)); }
</script>

<template>
    <div class="admin-shell">
        <div v-if="menu" class="fixed inset-0 z-30 bg-slate-950/40 lg:hidden" @click="menu = false"></div>
        <aside :class="['admin-sidebar', collapsed ? 'lg:w-20' : 'lg:w-64', menu ? 'translate-x-0' : '-translate-x-full lg:translate-x-0']">
            <div :class="['flex h-20 items-center gap-3 border-b border-white/10',collapsed?'lg:justify-center lg:px-3':'px-6']">
                <div class="grid size-11 place-items-center overflow-hidden rounded-2xl bg-white text-xl text-teal-800"><img v-if="platformSettings.smallLogo" :src="platformSettings.smallLogo" alt=""><span v-else>&hearts;</span></div>
                <div :class="collapsed?'lg:hidden':''"><p class="text-xl font-bold">{{ platformSettings.name }}</p><p class="text-xs text-teal-100">{{ platformSettings.footer }}</p></div>
            </div>
            <nav class="flex-1 space-y-1 px-3 py-6" aria-label="Platform administration">
                <RouterLink v-for="[name, label, icon] in visibleLinks" :key="name" :to="{ name }" :data-tooltip="collapsed?label:null" :class="['admin-nav', collapsed?'admin-nav-tooltip lg:justify-center lg:px-2':'', route.name === name || route.name?.startsWith(name + '.') ? 'admin-nav-active' : '']" @click="menu = false">
                    <span class="admin-nav-icon"><AppIcon :name="icon" :size="21"/></span><span :class="collapsed?'lg:hidden':''">{{ label }}</span>
                </RouterLink>
            </nav>
            <div class="border-t border-white/10 p-4">
                <div :class="['flex items-center gap-3 rounded-xl bg-white/10 p-3',collapsed?'lg:justify-center lg:p-2':'']">
                    <div class="grid size-10 place-items-center rounded-full bg-white font-bold text-teal-800">{{ auth.user?.name?.charAt(0) }}</div>
                    <div :class="['min-w-0',collapsed?'lg:hidden':'']"><p class="truncate text-sm font-semibold">{{ auth.user?.name }}</p><p class="text-xs text-teal-100">Platform Administrator</p></div>
                </div>
                <button :class="['mt-3 w-full rounded-xl px-4 py-2 text-left text-sm hover:bg-white/10',collapsed?'lg:text-center lg:px-1':'']" :title="collapsed?'Sign out':null" @click="logout"><span :class="collapsed?'lg:hidden':''">Sign out</span><span class="hidden lg:inline" :class="collapsed?'!inline':''">{{collapsed?'↪':''}}</span></button>
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
    </div>
</template>
