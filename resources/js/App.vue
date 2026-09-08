<script setup>
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router';
import { ref, onMounted, onUnmounted } from 'vue';
import { useAuthStore } from './stores/auth';
import { usePlatformSettingsStore } from './stores/platformSettings';
import FormErrors from './components/ui/FormErrors.vue';

const auth = useAuthStore();
const platformSettings = usePlatformSettingsStore();
const router = useRouter();
const route = useRoute();
const error = ref(null);
const busy = ref(false);
let removeErrorHandler;
onMounted(() => { platformSettings.load().catch(e => { error.value = e; }); removeErrorHandler = router.onError(e => { error.value = e; }); });
onUnmounted(() => removeErrorHandler?.());
async function logout() {
    error.value = null; busy.value = true;
    try { await auth.logout(); await router.replace('/app/login'); }
    catch (e) { error.value = e; }
    finally { busy.value = false; }
}
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <a href="#main" class="sr-only focus:not-sr-only focus:block focus:p-4">Skip to content</a>
        <header v-if="!route.meta.adminLayout && !route.meta.clinicLayout" class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-5xl items-center justify-between px-6 py-5">
                <RouterLink :to="{ name: 'home' }" class="text-xl font-semibold tracking-tight text-teal-800">
                    {{ platformSettings.name }}
                </RouterLink>
                <nav aria-label="Account" class="flex flex-wrap items-center gap-4 text-sm">
                    <RouterLink v-if="auth.user?.is_platform_admin" to="/app/admin">Administration</RouterLink>
                    <RouterLink v-if="auth.user" to="/app/clinics?switch=1">Clinics</RouterLink>
                    <button v-if="auth.user" :disabled="busy" @click="logout">Sign out</button>
                    <RouterLink v-else to="/app/login">Sign in</RouterLink>
                </nav>
            </div>
        </header>
        <main v-if="!route.meta.adminLayout && !route.meta.clinicLayout" id="main" class="mx-auto max-w-5xl space-y-6 px-6 py-10">
            <FormErrors :error="error" />
            <RouterView />
        </main>
        <main v-else id="main"><RouterView /></main>
    </div>
</template>
