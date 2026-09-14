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
const benefits = [ ['patient','Manage patients','Keep patient records organized and accessible'], ['calendar','Schedule appointments','Plan visits and follow patient progress'], ['doctor','Support your team','Tools for doctors, staff and administrators'], ['branch','Grow your clinic','A connected workspace for your clinic'] ];
async function submit() {
    if (busy.value) return;
    busy.value = true; error.value = null;
    try {
        await auth.login(form);
        await router.replace(auth.user.is_platform_admin ? '/app/admin' : auth.user.active_tenant_id ? '/app/dashboard' : '/app/clinics');
    } catch (e) { error.value = e; }
    finally { form.password = ''; showPassword.value = false; busy.value = false; }
}
</script>
<template>
    <div class="login-page">
        <section class="login-story" aria-label="About the platform">
            <img :src="'/images/login-clinician.png'" alt="" class="login-photo" fetchpriority="high">
            <div class="login-wash"></div>
            <RouterLink to="/" class="login-brand"><img v-if="logo" :src="logo" :alt="settings.name"><span v-else class="login-brand-icon"><AppIcon name="activity" :size="34" /></span><span><strong>{{ settings.name }}</strong><small>{{ settings.footer }}</small></span></RouterLink>
            <div class="login-message"><h2>Better healthcare <br>for brighter <br><span>communities.</span></h2><p>A connected clinic management platform<br class="desktop-break"> built for African healthcare providers.</p>
                <ul><li v-for="[icon,title,description] in benefits" :key="icon"><span class="benefit-icon"><AppIcon :name="icon" :size="23" /></span><div><strong>{{ title }}</strong><p>{{ description }}</p></div></li></ul>
                <p class="login-tagline"><span></span>Technology for healthier tomorrows</p>
            </div>
            <div class="login-note"><span aria-hidden="true">“</span><p>Empowering clinics.<br>Healthier communities.</p><i></i></div>
        </section>
        <section class="login-form-side">
            <RouterLink to="/" class="login-home"><AppIcon name="chevronLeft" :size="16" /> Back to home</RouterLink>
            <div class="login-card"><h1>Welcome back</h1><p class="login-subtitle">Sign in to your {{ settings.name }} account.</p>
                <form @submit.prevent="submit" :aria-busy="busy">
                    <FormErrors :error="error" />
                    <label for="login-email">Email address</label><div class="login-input"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/></svg><input id="login-email" v-model="form.email" type="email" autocomplete="username" placeholder="you@clinic.com" required maxlength="255" :disabled="busy"></div>
                    <label for="login-password">Password</label><div class="login-input"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V6a4 4 0 0 1 8 0v4M12 14v3"/></svg><input id="login-password" v-model="form.password" :type="showPassword ? 'text' : 'password'" autocomplete="current-password" placeholder="Enter your password" required :disabled="busy"><button type="button" class="password-toggle" :aria-label="showPassword ? 'Hide password' : 'Show password'" :aria-pressed="showPassword" @click="showPassword = !showPassword"><AppIcon name="view" :size="20" /></button></div>
                    <div class="login-recovery"><button type="button" :aria-expanded="recovery" aria-controls="recovery-help" @click="recovery = !recovery">Forgot password?</button></div>
                    <div v-if="recovery" id="recovery-help" class="recovery-help">Contact your clinic administrator to recover access.<template v-if="settings.values['general.support_email']"> You can also email <a :href="`mailto:${settings.values['general.support_email']}`">{{ settings.values['general.support_email'] }}</a>.</template></div>
                    <button class="login-submit" :disabled="busy">{{ busy ? 'Signing in…' : 'Sign in' }} <AppIcon v-if="!busy" name="chevronRight" :size="21" /></button>
                </form>
            </div>
            <p class="login-footer">{{ settings.name }} · {{ settings.footer }}</p>
        </section>
    </div>
</template>
<style scoped>
.login-page{min-height:100svh;display:grid;grid-template-columns:minmax(0,1.6fr) minmax(420px,1fr);background:#f9fcfd;color:#102344}.login-story{position:relative;overflow:hidden;min-height:800px;padding:65px 6%;background:#e5f5f3;border-radius:0 0 26px 0}.login-photo{position:absolute;right:0;bottom:0;width:62%;height:100%;object-fit:cover;object-position:55% center}.login-wash{position:absolute;inset:0;background:linear-gradient(90deg,#f4fbfb 0%,#f3fbfb 32%,#f1fbfae8 42%,#ecf9f951 61%,transparent 75%)}.login-brand{position:relative;display:inline-flex;align-items:center;gap:16px}.login-brand img{width:clamp(110px, 11vw, 150px);height:auto;max-height:82px;object-fit:contain}.login-brand-icon{background:#006a64;color:white;border-radius:22px;padding:12px}.login-brand strong{display:block;font-size:29px;line-height:1.2;overflow-wrap:anywhere}.login-brand small{display:block;color:#627f9c;font-size:15px;margin-top:5px}.login-message{position:relative;max-width:460px;margin-top:70px}.login-message h2{font-size:clamp(30px,3vw,47px);font-weight:750;line-height:1.1;letter-spacing:-.025em}.login-message h2 span{color:#006c65}.login-message>p{color:#627f9c;font-size:17px;line-height:1.6;margin-top:20px}.login-message ul{display:grid;gap:22px;margin-top:32px;max-width:390px}.login-message li{display:flex;gap:17px;align-items:center}.benefit-icon{background:#d9f4ef;color:#00726c;padding:14px;border-radius:50%;flex-shrink:0}.login-message li strong{font-size:15px}.login-message li p{color:#607d98;font-size:12px;margin-top:5px;line-height:1.6;max-width:270px}.login-message .login-tagline{font-size:13px;font-style:italic;display:flex;align-items:center;gap:16px;margin-top:34px}.login-tagline span{width:40px;height:2px;background:#008076}.login-note{position:absolute;right:4%;bottom:6%;padding:22px 26px;background:#fffffff0;border:1px solid #e7eff3;border-radius:23px;box-shadow:0 15px 40px #173c5220}.login-note>span{font-family:Georgia,serif;color:#00776a;font-size:42px;line-height:.6}.login-note p{font-size:16px;line-height:1.7;margin-top:9px}.login-note i{display:block;width:20px;height:2px;background:#008076;margin-top:15px}.login-form-side{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:65px 9%;position:relative;background:radial-gradient(ellipse at center,#edf7fa70,transparent 75%)}.login-home{align-self:flex-start;display:flex;align-items:center;gap:5px;color:#607990;font-size:13px;margin-bottom:28px}.login-card{width:100%;max-width:500px;padding:46px 34px;background:white;border:1px solid #e0e9f0;border-radius:20px;box-shadow:0 12px 40px #17475c08}.login-card h1{font-size:32px;font-weight:700;letter-spacing:-.025em}.login-subtitle{color:#627e9b;font-size:15px;line-height:1.6;margin-top:8px}.login-card form{margin-top:32px}.login-card label{display:block;font-size:14px;font-weight:600;margin:25px 0 10px}.login-input{display:flex;align-items:center;border:1px solid #cddce9;border-radius:10px;padding:0 14px;gap:12px;background:white}.login-input:focus-within{border-color:#009484;box-shadow:0 0 0 3px #00948416}.login-input>svg{width:20px;height:20px;color:#4b6b89;flex-shrink:0}.login-input input{width:100%;min-width:0;border:0;background:transparent;padding:15px 0;outline:none;box-shadow:none;font-size:15px}.login-input input::placeholder{color:#8196ac}.password-toggle{padding:7px;color:#4b6b89;flex-shrink:0}.login-recovery{display:flex;justify-content:flex-end;margin:20px 0 28px}.login-recovery button{font-size:14px;color:#006d64;text-decoration:underline;text-underline-offset:3px}.login-submit{display:flex;align-items:center;justify-content:center;gap:14px;width:100%;padding:15px;border-radius:10px;background:linear-gradient(110deg,#00635f,#008075);color:white;font-weight:600;font-size:16px}.login-submit:hover{background:#005c57}.login-submit:disabled{opacity:.65;cursor:wait}.login-footer{font-size:12px;color:#7b8e9e;margin-top:27px;text-align:center}.recovery-help{background:#eef8f5;border-radius:8px;padding:14px;font-size:13px;line-height:1.7;margin-bottom:20px;overflow-wrap:anywhere}.recovery-help a{color:#006c65;text-decoration:underline}.login-page button:focus-visible,.login-page a:focus-visible{outline:3px solid #00a28f;outline-offset:4px}
@media(min-width:1600px){.login-story{padding:75px 7%}.login-message{margin-top:85px}.login-message ul{gap:25px}.login-form-side{padding:70px 9%}.login-card{padding:52px 36px}}
@media(max-width:1150px){.login-page{grid-template-columns:1.1fr 1fr}.login-story{padding:45px 7%}.login-photo{width:100%;opacity:.3}.login-wash{background:linear-gradient(90deg,#f3fbfb,#f3fbfb8c)}.login-note{display:none}.login-message{margin-top:50px}.login-form-side{padding:40px 7%}.login-card{padding:35px 25px}}
@media(max-width:760px){.login-page{display:flex;flex-direction:column}.login-story{min-height:0;padding:28px 25px;border-radius:0}.login-brand strong{font-size:24px}.login-brand small{font-size:13px}.login-brand-icon{padding:10px}.login-message{margin-top:25px}.login-message h2{font-size:29px}.login-message h2 br{display:none}.login-message h2 br::after{content:' '}.login-message>p{font-size:14px;margin-top:12px}.login-message ul,.login-tagline,.login-message .login-tagline{display:none}.login-photo{width:45%;object-position:50% 25%;opacity:.15}.login-form-side{padding:24px;flex:1}.login-card{max-width:500px;padding:30px 24px}.login-home{margin-bottom:18px}.login-card h1{font-size:28px}.login-card form{margin-top:20px}.desktop-break{display:none}}
</style>
