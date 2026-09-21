<script setup>
import { reactive, ref, computed } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { usePlatformSettingsStore } from '../stores/platformSettings';
import AppIcon from '../components/ui/AppIcon.vue';
import FormErrors from '../components/ui/FormErrors.vue';
const auth = useAuthStore();
const settings = usePlatformSettingsStore();
const router = useRouter();
const form = reactive({ email: '', password: '' });
const busy = ref(false), error = ref(null), showPassword = ref(false), recovery = ref(false);
const logo = computed(() => settings.values['branding.login_logo'] || settings.logo);
const benefits = [
    ['members', 'Manage your customers', 'Keep customer records organized'],
    ['calendar', 'Appointments & bookings', 'Schedule services and manage availability'],
    ['payment', 'Billing & payments', 'Create invoices and collect payments'],
    ['subscriptions', 'Your team', 'Manage staff and responsibilities'],
    ['reports', 'Reports & insights', 'Make better decisions with real data'],
];
const businessTypes = [['activity', 'Clinic'], ['dental', 'Dental'], ['salon', 'Beauty'], ['sports', 'Stadium']];
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
        <section class="login-story" aria-label="About AFRI SOLUTION">
            <RouterLink to="/" class="login-brand" aria-label="AFRI SOLUTION home">
                <img v-if="logo" :src="logo" alt="AFRISO" class="login-official-logo">
                <span><strong>AFRI SOLUTION</strong><small>Business Management</small></span>
            </RouterLink>
            <div class="login-story-content">
                <div class="login-message">
                    <p class="login-eyebrow">One platform. Many possibilities.</p>
                    <h2>Run your business<br> from one <span>connected workspace.</span></h2>
                    <p class="login-description">Manage customers, staff, bookings, billing, reporting and communication &mdash; all in one platform for growing businesses.</p>
                    <ul class="login-benefits"><li v-for="[icon, title, description] in benefits" :key="title"><span class="benefit-icon"><AppIcon :name="icon" :size="24" /></span><div><strong>{{ title }}</strong><p>{{ description }}</p></div></li></ul>
                    <p class="login-tagline"><span aria-hidden="true"></span>Built for businesses that care about growth.</p>
                    <ul class="login-business-types" aria-label="Supported business types"><li v-for="[icon, label] in businessTypes" :key="label"><AppIcon :name="icon" :size="27" /><span>{{ label }}</span></li></ul>
                </div>
                <figure class="login-product" aria-label="The AFRISO business management application">
                    <div class="laptop-screen"><span class="laptop-camera" aria-hidden="true"></span><img :src="'/images/afriso-dashboard-demo.png'" alt="AFRISO dashboard in a demo workspace" width="1440" height="1200" fetchpriority="high"></div>
                    <div class="laptop-base" aria-hidden="true"><span></span></div>
                    <figcaption>AFRISO workspace <span>&middot;</span> Actual application, demo account</figcaption>
                </figure>
            </div>
        </section>
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
.login-story{position:relative;isolation:isolate;overflow:hidden;display:flex;flex-direction:column;min-height:100svh;padding:32px 0 28px clamp(28px,4vw,76px);background:radial-gradient(ellipse at 87% 18%,#cee6dd 0,transparent 47%),linear-gradient(110deg,#f4fbfb 34%,#e5f1ed 100%)}
.login-story::after{content:'';position:absolute;z-index:-1;right:-8%;bottom:0;width:70%;height:22%;background:linear-gradient(170deg,#d6c7af30,#d6c7af80);border-radius:50% 0 0 0;filter:blur(8px)}
.login-brand{display:inline-flex;align-items:center;gap:20px;align-self:flex-start;position:relative;z-index:2;max-width:100%;line-height:1.2}.login-official-logo{width:clamp(100px,8vw,155px);max-height:64px;object-fit:contain}.login-brand:has(img)>span{border-left:1px solid #cbdadf;padding-left:20px}.login-brand strong{display:block;font-size:clamp(22px,1.7vw,31px);font-weight:750;letter-spacing:-.02em}.login-brand small{display:block;color:#526d87;font-size:14px;margin-top:5px}
.login-story-content{display:flex;align-items:center;flex:1;position:relative;min-height:0;padding-block:32px 0}.login-message{position:relative;z-index:2;width:37%;max-width:410px}.login-eyebrow{color:#008477;font-size:10px;letter-spacing:.18em;text-transform:uppercase;font-weight:700;margin-bottom:16px}.login-message h2{font-size:clamp(28px,2.3vw,43px);line-height:1.18;font-weight:750;letter-spacing:-.035em}.login-message h2 span{color:#008578}.login-description{font-size:clamp(14px,.98vw,18px);line-height:1.55;color:#58728d;margin-top:16px}.login-benefits{display:grid;gap:14px;margin-top:22px}.login-benefits li{display:flex;gap:15px;align-items:center}.benefit-icon{display:grid;place-items:center;width:48px;height:48px;background:#def5ef;color:#008777;border-radius:50%;flex-shrink:0}.login-benefits strong{font-size:14px;font-weight:700;line-height:1.35;display:block}.login-benefits p{color:#58728d;font-size:12px;line-height:1.5;margin-top:4px}.login-tagline{display:flex;align-items:center;gap:12px;color:#5c7690;font-size:11px;line-height:1.5;font-style:italic;margin-top:26px}.login-tagline>span{height:2px;width:44px;background:#009787;flex-shrink:0}.login-business-types{display:flex;justify-content:space-between;max-width:330px;gap:12px;margin-top:28px}.login-business-types li{display:flex;flex-direction:column;align-items:center;gap:9px;color:#008b7e;font-size:12px}.login-business-types li>span{color:#56718b}
.login-product{position:absolute;width:59%;right:3%;top:50%;transform:translateY(-44%);margin:0;perspective:1600px}.laptop-screen{position:relative;background:#171d21;border:2px solid #747d7e;border-radius:21px 21px 9px 9px;padding:17px 10px 12px;box-shadow:0 25px 45px #133a3930,inset 0 0 0 3px #303a3a;transform:rotateY(-5deg) rotateZ(1deg);transform-origin:bottom center}.laptop-camera{position:absolute;top:6px;left:50%;height:4px;width:4px;border-radius:50%;background:#49626b;box-shadow:0 0 0 2px #101719}.laptop-screen img{display:block;width:100%;height:auto;object-fit:contain;border-radius:4px;background:#f6f9fc}.laptop-base{position:relative;width:112%;height:25px;margin-left:-6%;background:linear-gradient(#c7cecf,#8c989b 45%,#c9cece 48%,#566065);border-radius:2px 2px 50% 50% / 2px 2px 85% 85%;box-shadow:0 15px 16px #334c4928;transform:rotateZ(1deg)}.laptop-base>span{display:block;margin:auto;width:18%;height:8px;background:linear-gradient(#778589,#d8dddd);border-radius:0 0 9px 9px}.login-product figcaption{color:#52716e;text-align:center;font-size:10px;margin-top:20px}.login-product figcaption>span{margin-inline:7px}
.login-form-side{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:36px clamp(24px,3.3vw,64px);min-width:0;background:radial-gradient(ellipse at center,#fff 20%,#f4fafc 100%)}.login-home{display:flex;align-items:center;gap:5px;color:#58728d;font-size:13px;margin:0 0 16px;width:100%;max-width:500px}.login-card{width:100%;max-width:500px;padding:34px;border:1px solid #e0eaf2;background:#fff;border-radius:17px;box-shadow:0 12px 38px #164a6306}.login-card h1{font-size:clamp(28px,2vw,36px);font-weight:750;letter-spacing:-.04em;line-height:1.2}.login-subtitle{color:#59738e;font-size:15px;line-height:1.6;margin-top:12px}.login-card form{margin-top:28px}.login-card label{display:block;font-size:14px;font-weight:600;margin:24px 0 9px}.login-card label:first-of-type{margin-top:0}.login-input{display:flex;align-items:center;gap:12px;border:1px solid #cddce9;border-radius:9px;padding:0 14px;background:#fff;min-height:54px}.login-input:focus-within{border-color:#009484;box-shadow:0 0 0 3px #00948420}.login-input>svg{width:20px;height:20px;color:#53728a;flex-shrink:0}.login-input input{width:100%;min-width:0;border:0;background:transparent;padding:14px 0;outline:none;box-shadow:none;font-size:15px}.login-input input::placeholder{color:#74889c}.password-toggle{display:grid;place-items:center;min-width:36px;min-height:44px;color:#53728a;flex-shrink:0}.login-recovery{display:flex;justify-content:flex-end;margin:16px 0 20px}.login-recovery button{font-size:14px;color:#007c70;text-decoration:underline;text-underline-offset:3px}.login-submit{display:flex;align-items:center;justify-content:center;gap:14px;min-height:55px;width:100%;padding:14px;border-radius:9px;background:linear-gradient(110deg,#006461,#008c7c);color:#fff;font-weight:650;font-size:16px}.login-submit:hover{background:#005e57}.login-submit:disabled{opacity:.65;cursor:wait}.login-input input:disabled{opacity:.65;cursor:wait}.login-footer{font-size:12px;color:#58728d;margin-top:18px;text-align:center;line-height:1.7;max-width:500px}.login-footer a{color:#007c70;text-decoration:underline;text-underline-offset:3px}.recovery-help{background:#eef8f5;border-radius:8px;padding:12px;font-size:13px;line-height:1.6;margin-bottom:16px;overflow-wrap:anywhere}.recovery-help a{color:#006c65;text-decoration:underline}.login-page button:focus-visible,.login-page a:focus-visible{outline:3px solid #008b7d;outline-offset:4px}.login-card :deep([role=alert]){margin-bottom:16px}
@media(min-width:1700px) and (min-height:850px){.login-benefits{gap:12px;margin-top:22px}.login-benefits strong{font-size:16px}.login-benefits p{font-size:14px}.login-eyebrow{font-size:11px}.benefit-icon{width:50px;height:50px}.login-tagline{font-size:12px;margin-top:28px}.login-business-types{margin-top:32px}.login-card{padding:36px}.login-card form{margin-top:30px}.login-card label{margin-top:28px}.login-recovery{margin:18px 0 22px}}
@media(max-width:1399px){.login-story{padding-left:30px}.login-message{width:39%}.login-product{width:57%;right:1.5%}.login-brand{gap:15px}.login-brand:has(img)>span{padding-left:15px}.login-benefits{gap:12px}.login-benefits li{gap:10px}.benefit-icon{width:40px;height:40px}.benefit-icon svg{width:21px;height:21px}.login-benefits strong{font-size:13px}.login-benefits p{font-size:11px}.login-form-side{padding-inline:24px}.login-card{padding:28px 24px}.login-business-types{margin-top:22px}.login-tagline{gap:9px;font-size:10px}.login-tagline>span{width:25px}}
@media(max-width:1199px){.login-page{grid-template-columns:minmax(0,1fr) minmax(390px,1fr)}.login-story{padding:32px}.login-message{width:100%;max-width:420px}.login-story-content{padding-top:34px}.login-product{display:none}.login-message h2{max-width:380px;font-size:36px}.login-description{max-width:350px;font-size:15px}.login-benefits p{font-size:12px}.login-benefits strong{font-size:14px}.login-brand strong{font-size:22px}.login-official-logo{width:90px}.login-brand{gap:12px}.login-brand:has(img)>span{padding-left:12px}.login-brand small{font-size:12px}}
@media(max-width:1023px){.login-page{display:flex;flex-direction:column}.login-story{min-height:0;padding:28px 32px;background:#f0f9f7}.login-story-content{display:none}.login-story::after{display:none}.login-brand strong{font-size:23px}.login-form-side{flex:1;padding:28px 24px 36px}.login-card{padding:32px}.login-home{max-width:500px}.login-footer{margin-top:16px}}
@media(max-width:480px){.login-story{padding:23px 22px}.login-brand strong{font-size:21px}.login-official-logo{width:85px;max-height:48px}.login-brand{gap:12px}.login-brand:has(img)>span{padding-left:12px}.login-brand small{font-size:12px}.login-form-side{padding:20px 20px 28px;justify-content:flex-start}.login-card{padding:26px 22px}.login-card h1{font-size:29px}.login-subtitle{font-size:14px}.login-home{font-size:12px;margin-bottom:14px}.login-footer{font-size:12px}.login-input{padding-inline:11px;gap:9px}.login-input input{font-size:16px}.login-card form{margin-top:24px}}
@media(max-width:359px){.login-story{padding-inline:18px}.login-brand strong{font-size:18px}.login-official-logo{width:64px}.login-brand small{font-size:11px}.login-form-side{padding-inline:14px}.login-card{padding:24px 18px}.login-subtitle{font-size:13px}}
@media(min-width:1024px) and (max-height:780px){.login-story{padding-top:24px;padding-bottom:22px}.login-story-content{padding-top:24px}.login-message h2{font-size:clamp(28px,2.2vw,36px)}.login-eyebrow{margin-bottom:12px}.login-description{font-size:14px;margin-top:12px}.login-benefits{gap:10px;margin-top:16px}.benefit-icon{width:38px;height:38px}.benefit-icon svg{width:21px;height:21px}.login-benefits strong{font-size:13px}.login-benefits p{font-size:11px;margin-top:2px}.login-tagline{margin-top:18px;font-size:10px}.login-business-types{margin-top:20px}.login-business-types li{gap:6px;font-size:11px}.login-business-types svg{width:24px;height:24px}.login-form-side{padding-block:24px}.login-card{padding:26px 24px}.login-card form{margin-top:22px}.login-footer{margin-top:14px}.login-card label{margin-top:22px}}
@media(min-width:1024px) and (max-height:650px){.login-story{padding-top:18px;padding-bottom:18px}.login-brand strong{font-size:22px}.login-brand small{font-size:12px}.login-official-logo{max-height:44px}.login-story-content{padding-top:20px}.login-message h2{font-size:28px}.login-eyebrow{font-size:9px;margin-bottom:8px}.login-description{font-size:12px;margin-top:10px}.login-benefits{gap:8px;margin-top:12px}.benefit-icon{width:32px;height:32px}.benefit-icon svg{width:19px;height:19px}.login-benefits strong{font-size:12px}.login-benefits p{font-size:10px}.login-tagline{margin-top:12px;font-size:9px}.login-business-types{margin-top:14px}.login-business-types svg{width:22px;height:22px}.login-product{max-width:650px}.login-form-side{padding-block:18px}.login-home{margin-bottom:12px}.login-card{padding:23px}.login-card h1{font-size:28px}.login-subtitle{font-size:13px;margin-top:8px}.login-card form{margin-top:18px}.login-card label{font-size:13px;margin-top:18px}.login-input{min-height:48px}.login-input input{padding-block:11px;font-size:14px}.login-recovery{margin:12px 0 16px}.login-submit{min-height:48px;padding:11px}.login-footer{margin-top:12px;font-size:11px}}
</style>
