<script setup>
import { computed, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import api from '../services/api';
import { useAuthStore } from '../stores/auth';
import { usePlatformSettingsStore } from '../stores/platformSettings';
import FormErrors from '../components/ui/FormErrors.vue';

const auth = useAuthStore();
const settings = usePlatformSettingsStore();
const route = useRoute();
const router = useRouter();
const busy = ref(false), error = ref(null), notice = ref('');
const verified = computed(() => auth.user?.email_verified === true);
const justVerified = computed(() => route.query.verified === '1' && !auth.user);
async function resend() {
    if (busy.value || !auth.user || verified.value) return;
    busy.value = true; error.value = null; notice.value = '';
    try { const response = await api.post('/v1/email/verification/resend'); notice.value = response.data.message; }
    catch (e) { error.value = e; }
    finally { busy.value = false; }
}
async function continueToApp() {
    await auth.restore();
    if (!auth.user) return router.replace('/app/login');
    if (!auth.user.email_verified) return;
    return router.replace(auth.user.is_platform_admin ? '/app/admin' : auth.user.active_tenant_id ? '/app/dashboard' : '/app/clinics');
}
</script>

<template>
    <main class="verify-page">
        <section class="verify-card">
            <RouterLink to="/" class="verify-brand"><img v-if="settings.values['branding.login_logo'] || settings.logo" :src="settings.values['branding.login_logo'] || settings.logo" alt=""><span><strong>{{ settings.name }}</strong><small>{{ settings.footer }}</small></span></RouterLink>
            <div class="verify-mark" aria-hidden="true">{{ verified || justVerified ? '✓' : '@' }}</div>
            <h1>{{ verified || justVerified ? 'Email verified successfully.' : 'Verify your email' }}</h1>
            <p v-if="verified || justVerified">Your email address has been verified. Continue to your Afriso workspace.</p>
            <template v-else><p>We sent a verification link to <strong>{{ auth.user?.email || 'your email address' }}</strong>.</p><p>Check your inbox and click the link to activate access to your business workspace.</p></template>
            <FormErrors :error="error" />
            <p v-if="notice" class="verify-notice" role="status">{{ notice }}</p>
            <button v-if="verified" class="verify-primary" @click="continueToApp">Continue to Dashboard</button>
            <RouterLink v-else-if="justVerified || !auth.user" class="verify-primary" to="/app/login">Sign in to continue</RouterLink>
            <button v-else class="verify-primary" :disabled="busy" @click="resend">{{ busy ? 'Sending...' : 'Resend Verification Email' }}</button>
            <p v-if="!verified" class="verify-footer">Already verified? <button v-if="auth.user" @click="continueToApp">Continue</button><RouterLink v-else to="/app/login">Sign in</RouterLink></p>
        </section>
    </main>
</template>

<style scoped>
.verify-page{min-height:100svh;display:grid;place-items:center;padding:24px;background:#f5fafb;color:#102344;font-family:Arial,sans-serif}.verify-card{width:min(100%,520px);padding:clamp(26px,5vw,46px);border:1px solid #dce8ef;border-radius:20px;background:#fff;box-shadow:0 12px 34px #17475c0b}.verify-brand{display:flex;align-items:center;gap:14px;margin-bottom:34px}.verify-brand img{max-width:110px;max-height:55px;object-fit:contain}.verify-brand strong,.verify-brand small{display:block}.verify-brand strong{font-size:22px}.verify-brand small{margin-top:3px;color:#607990;font-size:13px}.verify-mark{display:grid;place-items:center;width:52px;height:52px;margin-bottom:20px;border-radius:50%;background:#e2f5f1;color:#006c65;font-size:25px;font-weight:700}.verify-card h1{font-size:clamp(25px,4vw,32px);font-weight:700;line-height:1.2}.verify-card>p{margin-top:14px;color:#607990;line-height:1.55}.verify-card>p strong{color:#102344;overflow-wrap:anywhere}.verify-primary{display:block;width:100%;margin-top:28px;padding:14px 18px;border-radius:10px;background:#006c65;color:white;font-weight:700;text-align:center}.verify-primary:disabled{opacity:.6}.verify-notice{padding:12px 14px;border-radius:8px;background:#e7f6ee;color:#166534}.verify-footer{text-align:center;font-size:14px}.verify-footer a,.verify-footer button{color:#006c65;font-weight:700;text-decoration:underline}
</style>
