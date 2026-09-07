<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import BaseCard from '../components/ui/BaseCard.vue';
import FormErrors from '../components/ui/FormErrors.vue';
const auth = useAuthStore();
const router = useRouter();
const form = reactive({ email: '', password: '' });
const busy = ref(false);
const error = ref(null);
async function submit() {
    busy.value = true; error.value = null;
    try {
        await auth.login(form);
        await router.replace(auth.user.is_platform_admin ? '/app/admin' : auth.user.active_tenant_id ? '/app/dashboard' : '/app/clinics');
    } catch (e) { error.value = e; }
    finally { form.password = ''; busy.value = false; }
}
</script>
<template>
    <BaseCard class="mx-auto max-w-md">
        <h1 class="text-2xl font-semibold">Sign in</h1>
        <p class="mt-2 text-slate-600">Access your clinic or platform administration.</p>
        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <FormErrors :error="error" />
            <label class="field">Email<input v-model="form.email" type="email" autocomplete="username" required maxlength="255"></label>
            <label class="field">Password<input v-model="form.password" type="password" autocomplete="current-password" required></label>
            <button class="btn" :disabled="busy">{{ busy ? 'Signing in…' : 'Sign in' }}</button>
        </form>
    </BaseCard>
</template>
