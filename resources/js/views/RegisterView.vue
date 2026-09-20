<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import api from '../services/api';
import { useAuthStore } from '../stores/auth';
import { usePlatformSettingsStore } from '../stores/platformSettings';
import FormErrors from '../components/ui/FormErrors.vue';

const router = useRouter();
const auth = useAuthStore();
const settings = usePlatformSettingsStore();
const step = ref(0);
const busy = ref(false);
const error = ref(null);
const options = ref({ enabled: true, business_types: [], plans: [] });
const success = ref(null);
const steps = ['Account', 'Business Type', 'Plan', 'Business Details', 'Trial Started'];
const form = reactive({ owner_name: '', owner_email: '', owner_password: '', owner_password_confirmation: '', business_type_id: '', plan_id: '', name: '', timezone: 'Africa/Nairobi' });
const selectedPlan = computed(() => options.value.plans.find(plan => String(plan.id) === String(form.plan_id)));

onMounted(async () => {
    try { options.value = (await api.get('/v1/public/registration')).data.data; }
    catch (e) { error.value = e; }
});

function next() { if (step.value < 3) { error.value = null; step.value++; } else submit(); }
async function submit() {
    if (busy.value) return;
    busy.value = true; error.value = null;
    try {
        await api.get('/sanctum/csrf-cookie', { baseURL: '/' });
        success.value = (await api.post('/register', form, { baseURL: '/' })).data.data;
        await auth.restore();
        form.owner_password = '';
        form.owner_password_confirmation = '';
        step.value = 4;
    } catch (e) { error.value = e; }
    finally { busy.value = false; }
}
</script>

<template>
    <div class="registration-page">
        <aside class="registration-story">
            <img class="registration-photo" :src="'/images/login-clinician.png'" alt="">
            <div class="registration-wash"></div>
            <RouterLink to="/" class="registration-brand">
                <img v-if="settings.values['branding.login_logo'] || settings.logo" :src="settings.values['branding.login_logo'] || settings.logo" alt="">
                <span><strong>{{ settings.name }}</strong><small>{{ settings.footer }}</small></span>
            </RouterLink>
            <div class="registration-message">
                <h1>Start your<br>business with<br><span>Afriso.</span></h1>
                <p>One workspace for your team, customers, and day-to-day operations.</p>
                <ul class="registration-benefits">
                    <li><span class="benefit-icon" aria-hidden="true">♟</span><div><strong>Manage your business</strong><small>Everything in one place</small></div></li>
                    <li><span class="benefit-icon" aria-hidden="true">◷</span><div><strong>Save time</strong><small>Simple and powerful tools</small></div></li>
                    <li><span class="benefit-icon" aria-hidden="true">↗</span><div><strong>Grow faster</strong><small>Make better decisions</small></div></li>
                    <li><span class="benefit-icon" aria-hidden="true">✓</span><div><strong>Secure &amp; reliable</strong><small>Your data is protected</small></div></li>
                </ul>
            </div>
            <p class="registration-quote">“Empowering businesses.<br>Stronger communities.”</p>
        </aside>

        <main class="registration-main">
            <div class="registration-top"><RouterLink to="/app/login">← Sign in</RouterLink></div>
            <div class="registration-card">
                <ol class="registration-steps" aria-label="Registration steps">
                    <li v-for="(name, index) in steps" :key="name" :class="{ current: step === index, done: step > index }"><span>{{ index + 1 }}</span><strong>{{ name }}</strong></li>
                </ol>
                <FormErrors :error="error" />
                <p v-if="!options.enabled" class="registration-notice">New business registration is currently unavailable.</p>
                <form v-else-if="step < 4" @submit.prevent="next">
                    <template v-if="step === 0">
                        <div class="registration-heading"><h2>Create your account</h2><p>Let’s start with your personal information. You will be the owner and administrator of the new business.</p></div>
                        <div class="registration-form-grid">
                            <label>Full name<input v-model.trim="form.owner_name" required maxlength="150" autocomplete="name" placeholder="Enter your full name"></label>
                            <label>Email address<input v-model.trim="form.owner_email" type="email" required maxlength="255" autocomplete="email" placeholder="you@yourcompany.com"></label>
                            <label>Password<input v-model="form.owner_password" type="password" required minlength="12" autocomplete="new-password" placeholder="Create a strong password"></label>
                            <label>Confirm password<input v-model="form.owner_password_confirmation" type="password" required minlength="12" autocomplete="new-password" placeholder="Confirm your password"></label>
                        </div>
                        <p class="registration-assurance"><span aria-hidden="true">✓</span><span><strong>Your information is secure</strong><small>Your account details are protected.</small></span></p>
                    </template>
                    <template v-if="step === 1">
                        <div class="registration-heading"><h2>Select Business Type</h2><p>Choose the kind of business you are creating.</p></div>
                        <div class="registration-options">
                            <label v-for="type in options.business_types" :key="type.id" class="registration-option"><input v-model="form.business_type_id" type="radio" :value="type.id" required><strong>{{ type.name }}</strong></label>
                        </div>
                    </template>
                    <template v-if="step === 2">
                        <div class="registration-heading"><h2>Select Subscription Plan</h2><p>Your free trial uses the selected plan's existing trial period.</p></div>
                        <div class="registration-options registration-plans">
                            <label v-for="plan in options.plans" :key="plan.id" class="registration-option registration-plan"><input v-model="form.plan_id" type="radio" :value="plan.id" required><span><strong>{{ plan.name }}</strong><small>{{ plan.currency }} {{ plan.price }} / {{ plan.billing_period }}</small><small v-if="plan.description">{{ plan.description }}</small><em>{{ plan.trial_days }} trial days</em></span></label>
                        </div>
                    </template>
                    <template v-if="step === 3">
                        <div class="registration-heading"><h2>Business Information</h2><p>We will create your main location and generate a business code automatically.</p></div>
                        <div class="registration-form-grid"><label>Business name<input v-model.trim="form.name" required maxlength="150" autocomplete="organization" placeholder="Enter your business name"></label><label>Timezone<input v-model="form.timezone" required placeholder="Africa/Nairobi"></label></div>
                        <p v-if="selectedPlan" class="registration-notice">{{ selectedPlan.name }} · {{ selectedPlan.currency }} · {{ selectedPlan.trial_days }} trial days</p>
                    </template>
                    <div class="registration-actions"><RouterLink v-if="step === 0" to="/app/login" class="registration-signin">Already have an account? <strong>Sign in</strong></RouterLink><button v-else type="button" class="registration-back" @click="step--">Back</button><button class="registration-next" :disabled="busy || (step === 1 && !form.business_type_id) || (step === 2 && !form.plan_id)">{{ busy ? 'Starting trial…' : step === 3 ? 'Start Free Trial' : 'Continue →' }}</button></div>
                </form>
                <section v-else class="registration-success"><div class="success-mark" aria-hidden="true">✓</div><h2>Your free trial has started</h2><p>Your business workspace is ready.</p><dl><div><dt>Business</dt><dd>{{ success?.business_name }}</dd></div><div><dt>Plan</dt><dd>{{ success?.plan }}</dd></div><div><dt>Trial ends</dt><dd>{{ success?.trial_ends_at ? new Date(success.trial_ends_at).toLocaleDateString() : '' }}</dd></div></dl><button class="registration-next" @click="router.replace('/app/dashboard')">Go to Dashboard →</button></section>
            </div>
        </main>
    </div>
</template>

<style scoped>
.registration-page{min-height:100svh;display:grid;grid-template-columns:minmax(0,42%) minmax(0,58%);background:#f9fcfd;color:#102344;font-family:Arial,sans-serif}
.registration-story{position:relative;isolation:isolate;overflow:hidden;min-height:100svh;padding:clamp(24px,3.4vw,60px);background:#e9f8f7}
.registration-photo{position:absolute;z-index:-2;right:0;bottom:0;width:68%;height:84%;object-fit:cover;object-position:52% top;mask-image:radial-gradient(ellipse 115% 110% at 85% 100%,#000 76%,transparent 100%)}
.registration-wash{position:absolute;z-index:-1;inset:0;background:linear-gradient(90deg,#f4fbfb 0%,#f4fbfbea 35%,#f4fbfb70 54%,transparent 78%)}
.registration-brand{display:flex;align-items:center;gap:18px;width:max-content;max-width:100%;font-size:clamp(20px,1.6vw,29px)}
.registration-brand img{max-width:145px;max-height:66px;object-fit:contain}.registration-brand strong,.registration-brand small{display:block}.registration-brand small{font-size:15px;font-weight:400;color:#627f9c;margin-top:4px}
.registration-message{position:relative;max-width:470px;margin-top:clamp(52px,8vh,100px)}.registration-message h1{font-size:clamp(34px,3.1vw,55px);line-height:1.12;letter-spacing:-.025em;font-weight:750}.registration-message h1 span{color:#006c65}.registration-message>p{max-width:370px;margin-top:20px;color:#536f8b;font-size:clamp(15px,1vw,18px);line-height:1.55}
.registration-benefits{display:grid;gap:16px;margin-top:28px}.registration-benefits li{display:flex;align-items:center;gap:14px}.benefit-icon{display:grid;place-items:center;flex:none;width:48px;height:48px;border-radius:50%;background:#dff5f1;color:#006c65;font-size:23px;font-weight:700}.registration-benefits strong,.registration-benefits small{display:block}.registration-benefits strong{font-size:15px}.registration-benefits small{font-size:13px;color:#607b95;margin-top:3px}
.registration-quote{position:relative;margin-top:clamp(24px,4vh,50px);padding:16px 22px;width:max-content;max-width:100%;border-radius:16px;background:#ffffffdc;color:#4e6b86;font-style:italic;line-height:1.5}
.registration-main{min-width:0;min-height:100svh;display:flex;flex-direction:column;justify-content:center;padding:28px clamp(24px,3.4vw,68px)}.registration-top{width:100%;max-width:900px;margin:0 auto 18px;text-align:right;color:#006c65;font-size:14px}.registration-card{width:100%;max-width:900px;margin:0 auto;padding:clamp(24px,2.4vw,42px);background:#fff;border:1px solid #dce8ef;border-radius:20px;box-shadow:0 10px 30px #17475c08}
.registration-steps{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));margin:0 0 clamp(28px,4vh,48px);padding:0;list-style:none}.registration-steps li{position:relative;display:flex;align-items:center;flex-direction:column;gap:9px;text-align:center;color:#687e98;font-size:12px;white-space:nowrap}.registration-steps li:not(:last-child)::after{content:"";position:absolute;top:20px;left:calc(50% + 25px);width:calc(100% - 50px);height:2px;background:#dce5ed}.registration-steps li.done::after{background:#00736b}.registration-steps li span{display:grid;place-items:center;width:40px;height:40px;border-radius:50%;background:#e8edf4;color:#465f78;font-size:16px}.registration-steps li.current,.registration-steps li.done{color:#006c65;font-weight:700}.registration-steps li.current span,.registration-steps li.done span{background:#006c65;color:#fff}
.registration-heading h2,.registration-success h2{font-size:clamp(25px,2.1vw,34px);font-weight:700;line-height:1.2}.registration-heading p{max-width:620px;margin:10px 0 24px;color:#627e9b;font-size:16px;line-height:1.5}
.registration-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px 22px}.registration-form-grid label{display:grid;gap:8px;font-size:14px;font-weight:600}.registration-form-grid input{width:100%;min-width:0;height:52px;border:1px solid #cddce9;border-radius:9px;padding:0 15px;background:white;font-size:15px;font-weight:400}.registration-form-grid input::placeholder{color:#8196ac}.registration-form-grid input:focus{border-color:#008d80;outline:3px solid #008d801a}
.registration-options{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin:22px 0}.registration-option{display:flex;align-items:center;gap:14px;min-height:86px;padding:18px;border:1px solid #d7e4ea;border-radius:11px;cursor:pointer;background:#fff}.registration-option:has(input:checked){border-color:#00756c;background:#effaf7}.registration-option input{accent-color:#006c65;flex:none}.registration-option strong{display:block;font-size:16px}.registration-option small,.registration-option em{display:block;margin-top:6px;color:#607990;font-size:13px;font-style:normal;line-height:1.4}.registration-option em{color:#006c65;font-weight:700}.registration-plan{align-items:flex-start;min-height:150px}
.registration-assurance,.registration-notice{display:flex;align-items:center;gap:13px;margin-top:25px;padding:15px 18px;border-radius:10px;background:#eef9f7;color:#245971}.registration-assurance>span:first-child{display:grid;place-items:center;width:30px;height:30px;border:2px solid #006c65;border-radius:50%;color:#006c65;font-weight:700;flex:none}.registration-assurance strong,.registration-assurance small{display:block}.registration-assurance small{margin-top:3px;color:#617b93;font-size:13px}
.registration-actions{display:flex;align-items:center;justify-content:space-between;gap:15px;margin-top:28px}.registration-signin{color:#607990;font-size:14px}.registration-signin strong{color:#006c65;margin-left:5px}.registration-next{margin-left:auto;border-radius:9px;background:#006c65;color:#fff;padding:14px 22px;font-size:15px;font-weight:700;min-height:50px}.registration-next:hover{background:#005b56}.registration-next:disabled{opacity:.55}.registration-back{border:1px solid #cddce9;border-radius:9px;padding:13px 20px;min-height:50px}.registration-page button:focus-visible,.registration-page a:focus-visible,.registration-option:focus-within{outline:3px solid #00a28f;outline-offset:2px}
.registration-success{text-align:center;padding:12px 4% 4px}.success-mark{display:grid;place-items:center;width:64px;height:64px;margin:0 auto 22px;border-radius:50%;background:#e0f5f0;color:#006c65;font-size:34px}.registration-success>p{color:#627e9b;margin-top:10px}.registration-success dl{max-width:620px;margin:28px auto;text-align:left;border:1px solid #e4edf1;border-radius:12px;padding:7px 22px}.registration-success dl div{display:flex;justify-content:space-between;gap:20px;border-bottom:1px solid #e4edf1;padding:13px 0}.registration-success dl div:last-child{border-bottom:0}.registration-success dt{color:#607990}.registration-success dd{font-weight:700;text-align:right}.registration-success .registration-next{margin:0}
@media(max-width:1450px){.registration-story{padding:28px}.registration-message{margin-top:45px}.registration-benefits{gap:11px;margin-top:20px}.benefit-icon{width:42px;height:42px}.registration-quote{margin-top:22px}.registration-main{padding:18px 30px}.registration-card{padding:25px}.registration-steps{margin-bottom:27px}.registration-heading p{margin-bottom:17px}.registration-options{gap:11px;margin:17px 0}.registration-option{min-height:72px;padding:14px}.registration-plan{min-height:130px}}
@media(max-height:950px) and (min-width:1451px){.registration-story{padding-top:32px;padding-bottom:32px}.registration-message{margin-top:45px}.registration-quote{display:none}}
@media(max-width:1050px){.registration-page{grid-template-columns:minmax(0,33%) minmax(0,67%)}.registration-story{padding:25px}.registration-story .registration-benefits,.registration-quote{display:none}.registration-photo{width:100%;height:60%}.registration-wash{background:linear-gradient(#f4fbfb 0%,#f4fbfb 35%,#f4fbfb50 70%,transparent)}.registration-message h1{font-size:32px}.registration-message>p{font-size:14px}.registration-main{padding:20px}.registration-card{padding:22px}}
@media(max-height:800px) and (min-width:701px){.registration-story{padding-top:12px;padding-bottom:12px}.registration-message{margin-top:20px}.registration-benefits{gap:8px;margin-top:12px}.registration-quote{display:none}.registration-main{padding-top:16px;padding-bottom:16px}}
@media(max-height:800px) and (min-width:1451px){.registration-main{padding-top:8px;padding-bottom:8px}.registration-top{margin-bottom:10px}}
@media(max-height:680px) and (min-width:701px){.registration-main{padding-top:10px;padding-bottom:10px}.registration-top{margin-bottom:10px}.registration-card{padding:18px}.registration-steps{margin-bottom:16px}.registration-heading p{margin:6px 0 12px}.registration-form-grid{gap:10px 16px}.registration-assurance,.registration-notice{margin-top:12px;padding:10px 14px}.registration-actions{margin-top:16px}.registration-success{padding:0}}
@media(max-width:700px){.registration-page{display:block}.registration-story{min-height:0;padding:20px 22px}.registration-story .registration-message,.registration-photo,.registration-wash{display:none}.registration-brand{font-size:20px}.registration-brand img{max-width:100px;max-height:50px}.registration-brand small{font-size:12px}.registration-main{min-height:0;padding:18px}.registration-top{margin-bottom:14px}.registration-card{padding:20px;border-radius:14px}.registration-steps{margin-bottom:28px}.registration-steps li{font-size:10px;white-space:normal}.registration-steps li span{width:31px;height:31px;font-size:13px}.registration-steps li:not(:last-child)::after{top:15px;left:calc(50% + 19px);width:calc(100% - 38px)}.registration-heading p{font-size:14px}.registration-form-grid,.registration-options{grid-template-columns:1fr}.registration-option,.registration-plan{min-height:70px}.registration-actions{margin-top:22px}.registration-signin{font-size:12px}.registration-next,.registration-back{padding:12px 14px;font-size:13px}}
</style>
