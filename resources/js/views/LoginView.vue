<script setup>
import { reactive, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { usePlatformSettingsStore } from '../stores/platformSettings';
import AppIcon from '../components/ui/AppIcon.vue';
import FormErrors from '../components/ui/FormErrors.vue';
import AuthMarketingPanel from '../components/auth/AuthMarketingPanel.vue';
const auth = useAuthStore();
const settings = usePlatformSettingsStore();
const router = useRouter();
const form = reactive({ email: '', password: '' });
const busy = ref(false), error = ref(null), showPassword = ref(false), recovery = ref(false);

async function submit() {
    if (busy.value) return;
    busy.value = true; error.value = null;
    try {
        await auth.login(form);
        await router.replace(!auth.user.email_verified ? '/app/verify-email' : auth.user.is_platform_admin ? '/app/admin' : auth.user.active_tenant_id ? '/app/dashboard' : '/app/clinics');
    } catch (e) { error.value = e; }
    finally { form.password = ''; showPassword.value = false; busy.value = false; }
}
</script>
<template>
    <div class="login-page">
        <AuthMarketingPanel />
        <section class="login-form-side">
            <RouterLink to="/" class="login-home"><AppIcon name="chevronLeft" :size="16" /> Back to home</RouterLink>
            <div class="login-card"><h1>Welcome back</h1><p class="login-subtitle">Sign in to your AFRI SOLUTION account.</p>
                <form @submit.prevent="submit" :aria-busy="busy">
                    <FormErrors :error="error" />
                    <label for="login-email">Email address</label><div class="login-input"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/></svg><input id="login-email" v-model="form.email" type="email" autocomplete="email" placeholder="you@yourcompany.com" required maxlength="255" :disabled="busy"></div>
                    <label for="login-password">Password</label><div class="login-input"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V6a4 4 0 0 1 8 0v4M12 14v3"/></svg><input id="login-password" v-model="form.password" :type="showPassword ? 'text' : 'password'" autocomplete="current-password" placeholder="Enter your password" required :disabled="busy"><button type="button" class="password-toggle" :aria-label="showPassword ? 'Hide password' : 'Show password'" :aria-pressed="showPassword" @click="showPassword = !showPassword"><AppIcon name="view" :size="20" /></button></div>
                    <div class="login-recovery"><button type="button" :aria-expanded="recovery" aria-controls="recovery-help" @click="recovery = !recovery">Forgot password?</button></div>
                    <div v-if="recovery" id="recovery-help" class="recovery-help">Contact your business administrator to recover access.<template v-if="settings.values['general.support_email']"> You can also email <a :href="`mailto:${settings.values['general.support_email']}`">{{ settings.values['general.support_email'] }}</a>.</template></div>
                    <button class="login-submit" :disabled="busy">{{ busy ? 'Signing in…' : 'Sign in' }} <AppIcon v-if="!busy" name="chevronRight" :size="21" /></button>
                </form>
            </div>
            <p class="login-footer">New to AFRI SOLUTION? <RouterLink to="/app/register">Create an account</RouterLink> and start a free trial</p>
            <p class="login-footer">AFRI SOLUTION &middot; Business Management</p>
        </section>
    </div>
</template>
<style scoped>

.login-page{min-height:100svh;display:grid;grid-template-columns:minmax(0,66fr) minmax(380px,34fr);background:#f7fbfc;color:#101b39}
.login-form-side{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:36px clamp(24px,3.3vw,64px);min-width:0;background:radial-gradient(ellipse at center,#fff 20%,#f4fafc 100%)}.login-home{display:flex;align-items:center;gap:5px;color:#58728d;font-size:13px;margin:0 0 16px;width:100%;max-width:500px}.login-card{width:100%;max-width:500px;padding:34px;border:1px solid #e0eaf2;background:#fff;border-radius:17px;box-shadow:0 12px 38px #164a6306}.login-card h1{font-size:clamp(28px,2vw,36px);font-weight:750;letter-spacing:-.04em;line-height:1.2}.login-subtitle{color:#59738e;font-size:15px;line-height:1.6;margin-top:12px}.login-card form{margin-top:28px}.login-card label{display:block;font-size:14px;font-weight:600;margin:24px 0 9px}.login-card label:first-of-type{margin-top:0}.login-input{display:flex;align-items:center;gap:12px;border:1px solid #cddce9;border-radius:9px;padding:0 14px;background:#fff;min-height:54px}.login-input:focus-within{border-color:#009484;box-shadow:0 0 0 3px #00948420}.login-input>svg{width:20px;height:20px;color:#53728a;flex-shrink:0}.login-input input{width:100%;min-width:0;border:0;background:transparent;padding:14px 0;outline:none;box-shadow:none;font-size:15px}.login-input input::placeholder{color:#74889c}.password-toggle{display:grid;place-items:center;min-width:36px;min-height:44px;color:#53728a;flex-shrink:0}.login-recovery{display:flex;justify-content:flex-end;margin:16px 0 20px}.login-recovery button{font-size:14px;color:#007c70;text-decoration:underline;text-underline-offset:3px}.login-submit{display:flex;align-items:center;justify-content:center;gap:14px;min-height:55px;width:100%;padding:14px;border-radius:9px;background:linear-gradient(110deg,#006461,#008c7c);color:#fff;font-weight:650;font-size:16px}.login-submit:hover{background:#005e57}.login-submit:disabled{opacity:.65;cursor:wait}.login-input input:disabled{opacity:.65;cursor:wait}.login-footer{font-size:12px;color:#58728d;margin-top:18px;text-align:center;line-height:1.7;max-width:500px}.login-footer a{color:#007c70;text-decoration:underline;text-underline-offset:3px}.recovery-help{background:#eef8f5;border-radius:8px;padding:12px;font-size:13px;line-height:1.6;margin-bottom:16px;overflow-wrap:anywhere}.recovery-help a{color:#006c65;text-decoration:underline}.login-page button:focus-visible,.login-page a:focus-visible{outline:3px solid #008b7d;outline-offset:4px}.login-card :deep([role=alert]){margin-bottom:16px}
@media(min-width:1700px) and (min-height:850px){.login-card{padding:36px}.login-card form{margin-top:30px}.login-card label{margin-top:28px}.login-recovery{margin:18px 0 22px}}
@media(max-width:1399px){.login-form-side{padding-inline:24px}.login-card{padding:28px 24px}}
@media(max-width:1199px){.login-page{grid-template-columns:minmax(0,1fr) minmax(390px,1fr)}}
@media(max-width:1023px){.login-page{display:flex;flex-direction:column}.login-form-side{flex:1;padding:28px 24px 36px}.login-card{padding:32px}.login-home{max-width:500px}.login-footer{margin-top:16px}}
@media(max-width:480px){.login-form-side{padding:20px 20px 28px;justify-content:flex-start}.login-card{padding:26px 22px}.login-card h1{font-size:29px}.login-subtitle{font-size:14px}.login-home{font-size:12px;margin-bottom:14px}.login-footer{font-size:12px}.login-input{padding-inline:11px;gap:9px}.login-input input{font-size:16px}.login-card form{margin-top:24px}}
@media(max-width:359px){.login-form-side{padding-inline:14px}.login-card{padding:24px 18px}.login-subtitle{font-size:13px}}
@media(min-width:1024px) and (max-height:900px){.login-form-side{padding-block:24px}.login-card{padding:26px 24px}.login-card form{margin-top:22px}.login-footer{margin-top:14px}.login-card label{margin-top:22px}}
@media(min-width:1024px) and (max-height:720px){.login-form-side{padding-block:18px}.login-home{margin-bottom:12px}.login-card{padding:23px}.login-card h1{font-size:28px}.login-subtitle{font-size:13px;margin-top:8px}.login-card form{margin-top:18px}.login-card label{font-size:13px;margin-top:18px}.login-input{min-height:48px}.login-input input{padding-block:11px;font-size:14px}.login-recovery{margin:12px 0 16px}.login-submit{min-height:48px;padding:11px}.login-footer{margin-top:12px;font-size:11px}}
/* Size the decorative device from available height as well as width. */

</style>
