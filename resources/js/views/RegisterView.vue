<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import api from '../services/api';
import { useAuthStore } from '../stores/auth';
import AuthMarketingPanel from '../components/auth/AuthMarketingPanel.vue';
import AppIcon from '../components/ui/AppIcon.vue';
import FormErrors from '../components/ui/FormErrors.vue';

const router = useRouter();
const auth = useAuthStore();
const step = ref(0);
const busy = ref(false);
const error = ref(null);
const options = ref({ enabled: true, business_types: [], plans: [] });
const success = ref(null);
const steps = ['Account', 'Business Type', 'Plan', 'Business Details', 'Trial Started'];
const stepLabels = ['Account', 'Business', 'Plan', 'Details', 'Trial'];
const businessPresentations = [
    { slug: 'clinic', name: 'Healthcare / Clinic', icon: 'doctor', description: 'Patients, appointments and clinical workflows' },
    { slug: 'dental', name: 'Dental Clinic', icon: 'dental', description: 'Dental workflows and patient care' },
    { slug: 'beauty-salon', name: 'Beauty Salon', icon: 'salon', description: 'Clients, stylists, services and bookings' },
    { slug: 'stadium', name: 'Stadium / Sports Facility', icon: 'sports', description: 'Customers, facilities and bookings' },
];
function businessPresentation(type) {
    return businessPresentations.find(item => item.slug === type.slug || item.name === type.name)
        || { icon: 'branch', description: 'Tools for your daily business operations' };
}
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
        <AuthMarketingPanel registration />

        <section class="registration-main" aria-label="Create your business workspace">
            <div class="registration-top"><RouterLink to="/app/login">← Sign in</RouterLink></div>
            <div class="registration-card">
                <ol class="registration-steps" aria-label="Registration steps">
                    <li v-for="(name, index) in steps" :key="name" :aria-label="name" :aria-current="step === index ? 'step' : undefined" :class="{ current: step === index, done: step > index }"><span><AppIcon v-if="step > index" name="check" :size="17" /><template v-else>{{ index + 1 }}</template></span><strong>{{ stepLabels[index] }}</strong></li>
                </ol>
                <FormErrors :error="error" />
                <p v-if="!options.enabled" class="registration-notice">New business registration is currently unavailable.</p>
                <form v-else-if="step < 4" @submit.prevent="next" :aria-busy="busy">
                    <template v-if="step === 0">
                        <div class="registration-heading"><h1>Create your account</h1><p>Let's start with your information. You will be the owner and administrator of the new business.</p></div>
                        <div class="registration-form-grid">
                            <label>Full name<input v-model.trim="form.owner_name" required maxlength="150" autocomplete="name" placeholder="Enter your full name"></label>
                            <label>Email address<input v-model.trim="form.owner_email" type="email" required maxlength="255" autocomplete="email" placeholder="you@yourcompany.com"></label>
                            <label>Password<input v-model="form.owner_password" type="password" required minlength="12" autocomplete="new-password" placeholder="Create a strong password"></label>
                            <label>Confirm password<input v-model="form.owner_password_confirmation" type="password" required minlength="12" autocomplete="new-password" placeholder="Confirm your password"></label>
                        </div>
                        <p class="registration-assurance"><AppIcon name="check" :size="16" /> Your account information is protected.</p>
                    </template>
                    <template v-if="step === 1">
                        <div class="registration-heading"><h1>Select Business Type</h1><p>Choose the kind of business you are creating.</p></div>
                        <div class="registration-options">
                            <label v-for="type in options.business_types" :key="type.id" class="registration-option business-option"><span class="business-option-icon"><AppIcon :name="businessPresentation(type).icon" :size="25" /></span><input v-model="form.business_type_id" type="radio" :value="type.id" required><span class="business-option-copy"><strong>{{ type.name }}</strong><small>{{ businessPresentation(type).description }}</small></span></label>
                        </div>
                    </template>
                    <template v-if="step === 2">
                        <div class="registration-heading"><h1>Select Subscription Plan</h1><p>Your free trial uses the selected plan's existing trial period.</p></div>
                        <div class="registration-options registration-plans">
                            <label v-for="plan in options.plans" :key="plan.id" class="registration-option registration-plan"><input v-model="form.plan_id" type="radio" :value="plan.id" required><span><strong>{{ plan.name }}</strong><small class="plan-price"><b>{{ plan.currency }} {{ plan.price }}</b><span> / {{ plan.billing_period }}</span></small><small v-if="plan.description">{{ plan.description }}</small><em>{{ plan.trial_days }} trial days</em></span></label>
                        </div>
                    </template>
                    <template v-if="step === 3">
                        <div class="registration-heading"><h1>Business Information</h1><p>We will create your main location and generate a business code automatically.</p></div>
                        <div class="registration-form-grid"><label>Business name<input v-model.trim="form.name" required maxlength="150" autocomplete="organization" placeholder="Enter your business name"></label><label>Timezone<input v-model="form.timezone" required placeholder="Africa/Nairobi"></label></div>
                        <p v-if="selectedPlan" class="registration-notice">{{ selectedPlan.name }} · {{ selectedPlan.currency }} · {{ selectedPlan.trial_days }} trial days</p>
                    </template>
                    <div class="registration-actions"><RouterLink v-if="step === 0" to="/app/login" class="registration-signin">Already have an account? <strong>Sign in</strong></RouterLink><button v-else type="button" class="registration-back" @click="step--">Back</button><button class="registration-next" :disabled="busy || (step === 1 && !form.business_type_id) || (step === 2 && !form.plan_id)">{{ busy ? 'Starting trial…' : step === 3 ? 'Start Free Trial' : 'Continue →' }}</button></div>
                </form>
                <section v-else class="registration-success"><div class="success-mark"><AppIcon name="check" :size="30" /></div><h1>Your free trial has started</h1><p>{{ success?.verification_email_sent === false ? 'We could not send the verification email. Use the resend button on the next screen.' : 'Your business workspace is ready. Verify your email to continue.' }}</p><dl><div><dt>Business</dt><dd>{{ success?.business_name }}</dd></div><div v-if="success?.business_type"><dt>Business type</dt><dd>{{ success.business_type }}</dd></div><div><dt>Plan</dt><dd>{{ success?.plan }}</dd></div><div><dt>Trial ends</dt><dd>{{ success?.trial_ends_at ? new Date(success.trial_ends_at).toLocaleDateString() : '' }}</dd></div><div><dt>Email</dt><dd>{{ success?.email }}</dd></div><div><dt>Status</dt><dd>Email verification required</dd></div></dl><button class="registration-next" @click="router.replace('/app/verify-email')">Verify Email →</button></section>
            </div>
        </section>
    </div>
</template>

<style scoped>
.registration-page{min-height:100svh;display:grid;grid-template-columns:minmax(0,55fr) minmax(540px,45fr);background:#f7fbfc;color:#101b39}
.registration-main{min-width:0;min-height:100svh;display:flex;flex-direction:column;justify-content:center;padding:28px clamp(24px,2.6vw,50px);background:radial-gradient(ellipse at center,#fff 20%,#f4fafc 100%)}
.registration-top{width:100%;max-width:760px;margin:0 auto 16px;color:#58728d;font-size:13px}.registration-top a{display:inline-flex;align-items:center;min-height:24px}
.registration-card{width:100%;max-width:760px;margin-inline:auto;padding:30px;border:1px solid #e0eaf2;background:#fff;border-radius:17px;box-shadow:0 12px 38px #164a6306}
.registration-steps{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:4px;margin-bottom:26px;list-style:none;padding:0}.registration-steps li{position:relative;display:flex;align-items:center;flex-direction:column;gap:7px;text-align:center;color:#667a8e;font-size:11px;min-width:0}.registration-steps li:not(:last-child)::after{content:'';position:absolute;top:17px;left:calc(50% + 22px);width:calc(100% - 40px);height:2px;background:#e0e8ed}.registration-steps li.done::after{background:#008578}.registration-steps li>span{display:grid;place-items:center;width:34px;height:34px;border-radius:50%;background:#edf1f5;color:#526b81;font-size:14px}.registration-steps li.current,.registration-steps li.done{color:#007b6f}.registration-steps li.current>span,.registration-steps li.done>span{background:#008578;color:#fff}.registration-steps li.current>span{box-shadow:0 0 0 4px #e3f5f0}.registration-steps strong{font-weight:600}
.registration-heading h1,.registration-success h1{font-size:clamp(26px,2vw,34px);font-weight:750;line-height:1.2;letter-spacing:-.035em}.registration-heading p{margin:10px 0 22px;color:#59738e;font-size:14px;line-height:1.6;max-width:620px}
.registration-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.registration-form-grid label{display:grid;gap:8px;font-size:13px;font-weight:600;min-width:0}.registration-form-grid input{width:100%;min-width:0;height:54px;border:1px solid #cddce9;border-radius:9px;padding:0 13px;background:#fff;font-size:14px;font-weight:400}.registration-form-grid input::placeholder{color:#74889c}.registration-form-grid input:focus{outline:none;border-color:#009484;box-shadow:0 0 0 3px #00948420}
.registration-options{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:20px 0}.registration-option{display:flex;align-items:flex-start;gap:12px;padding:18px;border:1px solid #d7e4ea;border-radius:11px;cursor:pointer;background:#fff;min-width:0}.registration-option:hover{border-color:#82bbb2}.registration-option:has(input:checked){border-color:#008578;background:#f0faf7;box-shadow:inset 0 0 0 1px #008578}.registration-option input{accent-color:#008578;flex:none;width:16px;height:16px;margin-top:3px}.registration-option strong{display:block;font-size:15px;line-height:1.4;font-weight:700;overflow-wrap:anywhere}.registration-option small,.registration-option em{display:block;margin-top:6px;color:#58728d;font-size:12px;font-style:normal;line-height:1.5;overflow-wrap:anywhere}.business-option{position:relative;display:grid;grid-template-columns:1fr auto;gap:10px}.business-option-copy{grid-column:1/-1}.business-option-icon{display:grid;place-items:center;width:40px;height:40px;border-radius:12px;background:#e1f6ef;color:#008578}.business-option input{align-self:start}.registration-plan{min-height:150px}.registration-plan>span{min-width:0;flex:1}.registration-option .plan-price{margin-top:13px;font-size:12px}.plan-price b{font-size:20px;color:#101b39;font-weight:750}.plan-price>span{display:block;margin-top:2px}.registration-option em{display:inline-block;margin-top:14px;padding:4px 9px;border-radius:6px;background:#e1f6ee;color:#007a6b;font-size:11px;font-weight:650}
.registration-assurance{display:flex;align-items:center;gap:7px;margin-top:17px;color:#58728d;font-size:12px}.registration-assurance svg{color:#008578;flex-shrink:0}.registration-notice{margin-top:18px;padding:13px 15px;border:1px solid #dceee8;border-radius:9px;background:#f0faf7;color:#356d65;font-size:13px;line-height:1.6;overflow-wrap:anywhere}
.registration-actions{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-top:24px}.registration-signin{color:#58728d;font-size:12px;line-height:1.6}.registration-signin strong{color:#007c70;text-decoration:underline;text-underline-offset:3px;white-space:nowrap}.registration-next{margin-left:auto;border-radius:9px;background:linear-gradient(110deg,#006461,#008c7c);color:#fff;padding:13px 22px;font-size:14px;font-weight:650;min-height:50px;flex-shrink:0}.registration-next:hover{background:#005e57}.registration-next:disabled{opacity:.55;cursor:not-allowed}.registration-back{border:1px solid #cddce9;border-radius:9px;padding:12px 20px;min-height:50px;font-size:14px}.registration-back:hover{background:#edf7f4}.registration-page button:focus-visible,.registration-page a:focus-visible,.registration-option:focus-within{outline:3px solid #008b7d;outline-offset:4px}
.registration-success{text-align:center}.success-mark{display:grid;place-items:center;width:52px;height:52px;margin:0 auto 14px;border-radius:50%;background:#def5ed;color:#008578}.registration-success>p{color:#59738e;font-size:14px;line-height:1.6;margin-top:10px}.registration-success dl{margin:20px auto;text-align:left;border:1px solid #e0eaf2;border-radius:11px;padding:3px 16px;font-size:13px}.registration-success dl div{display:flex;justify-content:space-between;gap:16px;border-bottom:1px solid #e8eef2;padding:10px 0}.registration-success dl div:last-child{border-bottom:0}.registration-success dt{color:#58728d;flex-shrink:0}.registration-success dd{font-weight:600;text-align:right;overflow-wrap:anywhere;min-width:0}.registration-success .registration-next{margin:0}.registration-card :deep([role=alert]){margin-bottom:16px}
@media(max-width:1399px){.registration-main{padding-inline:24px}.registration-card{padding:24px}.registration-heading h1,.registration-success h1{font-size:27px}.registration-option{padding:14px}.registration-option strong{font-size:14px}.registration-form-grid{gap:16px}.registration-heading p{font-size:13px}}
@media(max-width:1199px){.registration-page{grid-template-columns:minmax(0,42fr) minmax(540px,58fr)}.registration-main{padding-inline:20px}}
@media(max-width:1023px){.registration-page{display:flex;flex-direction:column}.registration-main{min-height:0;flex:1;padding:24px}.registration-card,.registration-top{max-width:660px}}
@media(max-width:639px){.registration-main{padding:20px 18px 28px;justify-content:flex-start}.registration-card{padding:24px 20px}.registration-top{margin-bottom:12px}.registration-steps{margin-bottom:24px}.registration-steps li{font-size:10px;gap:6px}.registration-steps li>span{width:29px;height:29px;font-size:12px}.registration-steps li:not(:last-child)::after{top:14px;left:calc(50% + 19px);width:calc(100% - 34px)}.registration-form-grid,.registration-options{grid-template-columns:1fr}.registration-form-grid input{font-size:16px}.registration-heading h1,.registration-success h1{font-size:27px}.registration-heading p{font-size:14px}.registration-actions{flex-wrap:wrap;gap:16px}.registration-signin{flex-basis:100%}.registration-next,.registration-back{font-size:14px}.registration-option{padding:16px}.business-option{grid-template-columns:40px 1fr auto;align-items:center;gap:12px}.business-option-copy{grid-column:2;grid-row:1}.business-option input{grid-column:3;grid-row:1;align-self:center}.registration-plan{min-height:0}.registration-option strong{font-size:15px}.registration-success dl div{flex-wrap:wrap;gap:5px 12px}.registration-success dd{margin-left:auto}.registration-success>p{font-size:13px}}
@media(max-width:359px){.registration-main{padding-inline:12px}.registration-card{padding:22px 16px}.registration-steps li{font-size:9px}.registration-heading h1,.registration-success h1{font-size:25px}}
@media(min-width:1024px) and (max-height:800px){.registration-main{padding-block:18px}.registration-top{margin-bottom:12px}.registration-card{padding:24px}.registration-steps{margin-bottom:22px}.registration-heading p{margin-bottom:18px}.registration-success dl{margin-block:14px}.registration-success dl div{padding-block:7px}.success-mark{width:42px;height:42px;margin-bottom:10px}.registration-success h1{font-size:28px}.registration-success>p{font-size:13px}}
@media(min-width:1024px) and (max-height:680px){.registration-main{padding-block:12px}.registration-top{margin-bottom:9px}.registration-card{padding:20px}.registration-steps{margin-bottom:18px}.registration-steps li>span{width:30px;height:30px}.registration-steps li:not(:last-child)::after{top:15px}.registration-heading h1,.registration-success h1{font-size:26px}.registration-heading p{margin:8px 0 15px;font-size:13px}.registration-form-grid{gap:12px}.registration-form-grid input{height:48px}.registration-actions{margin-top:18px}.registration-options{gap:10px;margin-block:16px}.business-option{gap:8px;padding:12px}.business-option-icon{width:32px;height:32px}.business-option-icon svg{width:22px;height:22px}.registration-option small{margin-top:4px;font-size:11px}.registration-next,.registration-back{min-height:44px;padding:10px 18px}.registration-success dl{margin-block:12px}.registration-success dl div{padding-block:5px}.success-mark{width:36px;height:36px;margin-bottom:8px}.success-mark svg{width:24px;height:24px}.registration-success>p{margin-top:7px}}
</style>
