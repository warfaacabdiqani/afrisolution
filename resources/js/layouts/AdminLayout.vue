<script setup>
import { computed, ref } from 'vue';
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const menu = ref(false);
const links = [
    ['admin.dashboard', 'Dashboard', 'D'],
    ['admin.clinics', 'Clinics / Tenants', 'C'],
    ['admin.plans', 'Subscription Plans', 'P'],
    ['admin.subscriptions', 'Subscriptions', 'S'],
    ['admin.users', 'Users', 'U'],
    ['admin.audit', 'Audit Log', 'A'],
    ['admin.settings', 'System Settings', 'G'],
];
const title = computed(() => route.meta.title || 'Platform administration');

async function logout() {
    await auth.logout();
    await router.replace('/app/login');
}
</script>

<template>
    <div class="admin-shell">
        <div v-if="menu" class="fixed inset-0 z-30 bg-slate-950/40 lg:hidden" @click="menu = false"></div>
        <aside :class="['admin-sidebar', menu ? 'translate-x-0' : '-translate-x-full lg:translate-x-0']">
            <div class="flex h-20 items-center gap-3 border-b border-white/10 px-6">
                <div class="grid size-11 place-items-center rounded-2xl bg-white text-xl text-teal-800">&hearts;</div>
                <div><p class="text-xl font-bold">Afri Clinic</p><p class="text-xs text-teal-100">Healthcare SaaS</p></div>
            </div>
            <nav class="flex-1 space-y-1 px-3 py-6" aria-label="Platform administration">
                <RouterLink v-for="[name, label, icon] in links" :key="name" :to="{ name }" :class="['admin-nav', route.name === name || route.name?.startsWith(name + '.') ? 'admin-nav-active' : '']" @click="menu = false">
                    <span class="admin-nav-icon">{{ icon }}</span>{{ label }}
                </RouterLink>
            </nav>
            <div class="border-t border-white/10 p-4">
                <div class="flex items-center gap-3 rounded-xl bg-white/10 p-3">
                    <div class="grid size-10 place-items-center rounded-full bg-white font-bold text-teal-800">{{ auth.user?.name?.charAt(0) }}</div>
                    <div class="min-w-0"><p class="truncate text-sm font-semibold">{{ auth.user?.name }}</p><p class="text-xs text-teal-100">Platform Administrator</p></div>
                </div>
                <button class="mt-3 w-full rounded-xl px-4 py-2 text-left text-sm hover:bg-white/10" @click="logout">Sign out</button>
            </div>
        </aside>
        <div class="min-w-0 lg:pl-64">
            <header class="admin-topbar">
                <button class="rounded-lg border px-3 py-2 text-sm lg:hidden" aria-label="Open menu" @click="menu = true">Menu</button>
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
