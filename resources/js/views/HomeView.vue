<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import AppIcon from '../components/ui/AppIcon.vue';
import { usePlatformSettingsStore } from '../stores/platformSettings';
import { useAuthStore } from '../stores/auth';
import { planFeatureGroups } from '../config/planFeatures';
const settings = usePlatformSettingsStore();
const auth = useAuthStore();
const workspace = computed(() => auth.user ? '/app/clinics' : '/app/login');
const organization = computed(() => settings.values['general.organization_name']);
const email = computed(() => settings.values['general.support_email']);
const phone = computed(() => settings.values['general.support_phone']);
const categories = [
    ['activity', 'Patient care', 'Organize patient records, appointments and clinical workflows.'],
    ['storage', 'Pharmacy', 'Keep medicines, purchasing and stock information together.'],
    ['revenue', 'Finance', 'Support your clinic’s billing and payment workflows.'],
    ['dashboard', 'Reporting', 'Understand clinic activity with reports and data exports.'],
    ['members', 'Communication', 'Stay connected through your plan’s notification features.'],
    ['settings', 'Advanced tools', 'Extend your workspace as your clinic grows.'],
];
</script>

<template>
    <div class="landing">
        <section class="landing-hero" aria-labelledby="landing-title">
            <div>
                <p class="landing-eyebrow">{{ settings.footer }}</p>
                <h1 id="landing-title">Your clinic.<br>Your team.<br><span>One connected workspace.</span></h1>
                <p class="landing-intro">Welcome to {{ settings.name }}. Bring patient records, clinician schedules and appointments together, so your team can focus on care.</p>
                <div class="landing-actions"><RouterLink :to="workspace" class="btn">{{ auth.user ? 'Open your workspace' : 'Sign in to your workspace' }} <AppIcon name="chevronRight" :size="18" /></RouterLink><a href="#services">Explore services ↓</a></div>
            </div>
            <aside class="landing-overview" aria-label="Clinic workspace overview">
                <div class="landing-overview-title"><img v-if="settings.logo" :src="settings.logo" :alt="settings.name" class="landing-logo"><span v-else class="landing-symbol"><AppIcon name="activity" :size="28" /></span><div><strong>{{ settings.name }}</strong><p>Your clinic workspace</p></div></div>
                <div v-for="item in [['patient','Patient records','Keep patient information organized.'],['doctor','Your clinical team','Manage clinicians and working hours.'],['calendar','Appointments','Plan visits and follow their progress.']]" :key="item[0]" class="landing-workflow"><AppIcon :name="item[0]" :size="24" /><div><strong>{{ item[1] }}</strong><p>{{ item[2] }}</p></div><AppIcon name="check" :size="18" /></div>
                <div class="landing-overview-footer"><AppIcon name="branch" :size="18" /> Built around your clinic’s daily work</div>
            </aside>
        </section>
        <section id="services" class="landing-services" aria-labelledby="services-title">
            <p class="landing-eyebrow">Our services</p><h2 id="services-title">Tools for every part of your clinic</h2>
            <p class="landing-description">Explore our plan feature catalog. Access depends on your subscription and module availability.</p>
            <div class="landing-service-grid"><article v-for="([group, features], index) in planFeatureGroups" :key="group" class="landing-service"><span class="landing-service-icon"><AppIcon :name="categories[index][0]" :size="25" /></span><h3>{{ categories[index][1] }}</h3><p>{{ categories[index][2] }}</p><ul><li v-for="[key, label] in features" :key="key"><AppIcon name="check" :size="15" />{{ label }}</li></ul></article></div>
        </section>
        <section class="landing-contact" aria-labelledby="about-title">
            <div><p class="landing-eyebrow">About the app</p><h2 id="about-title">{{ settings.name }}</h2><p v-if="organization">{{ organization }}</p><p>{{ settings.footer }}</p></div>
            <div v-if="email || phone" class="landing-support"><h3>Get in touch</h3><a v-if="email" :href="`mailto:${email}`">{{ email }}</a><a v-if="phone" :href="`tel:${phone.replace(/[^+\d]/g, '')}`">{{ phone }}</a></div>
            <RouterLink :to="workspace" class="btn">{{ auth.user ? 'Continue to workspace' : 'Sign in' }} <AppIcon name="chevronRight" :size="18" /></RouterLink>
        </section>
        <footer class="landing-footer"><span>© {{ new Date().getFullYear() }} {{ organization || settings.name }}</span><span>{{ settings.footer }}</span></footer>
    </div>
</template>

<style scoped>
.landing{color:#102344}.landing-hero{display:grid;grid-template-columns:1.2fr 1fr;align-items:center;gap:44px;padding:36px 0 60px}.landing-eyebrow{text-transform:uppercase;letter-spacing:.15em;font-size:11px;font-weight:700;color:#008575;margin-bottom:14px}.landing h1{font-size:clamp(34px,4vw,52px);line-height:1.13;letter-spacing:-.04em;font-weight:750}.landing h1 span{color:#007b70}.landing-intro{color:#60748e;line-height:1.8;margin:24px 0;font-size:16px;max-width:510px}.landing-actions{display:flex;gap:22px;align-items:center;flex-wrap:wrap}.landing .btn{display:inline-flex;align-items:center;justify-content:center;gap:12px}.landing-actions>a:not(.btn){font-weight:600;font-size:14px;color:#006c65}.landing-overview{background:white;border:1px solid #dce8ec;border-radius:22px;padding:26px;box-shadow:0 22px 65px #005c5810;transform:rotate(-1deg)}.landing-overview-title{display:flex;align-items:center;gap:14px;padding-bottom:24px;border-bottom:1px solid #edf2f5}.landing-overview-title strong{font-size:21px;overflow-wrap:anywhere}.landing-overview p{font-size:12px;color:#647b94;margin-top:5px;line-height:1.5}.landing-symbol{background:#00645e;color:white;padding:12px;border-radius:16px}.landing-logo{width:58px;height:58px;object-fit:contain}.landing-workflow{display:flex;align-items:center;gap:14px;padding:22px 0;border-bottom:1px solid #edf2f5}.landing-workflow>svg{color:#008b7a;flex-shrink:0}.landing-workflow div{flex:1}.landing-workflow strong{font-size:14px}.landing-overview-footer{display:flex;gap:9px;align-items:center;color:#527d78;font-size:12px;padding-top:20px}.landing-services{scroll-margin-top:25px;padding:35px 0 48px;border-top:1px solid #dce8ec}.landing h2{font-size:30px;letter-spacing:-.025em;font-weight:700;line-height:1.25}.landing-description{color:#60748e;margin:14px 0 28px;line-height:1.7}.landing-service-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.landing-service{background:white;border:1px solid #dce8ec;border-radius:16px;padding:25px}.landing-service-icon{display:inline-flex;padding:12px;background:#e9f8f3;border-radius:12px;color:#008575;margin-bottom:20px}.landing-service:nth-child(2n) .landing-service-icon{background:#edf4ff;color:#2376c6}.landing h3{font-weight:650;font-size:18px}.landing-service p{color:#60748e;font-size:13px;line-height:1.7;margin-top:10px;min-height:65px}.landing-service ul{display:grid;gap:11px;margin-top:20px}.landing-service li{display:flex;gap:9px;align-items:center;font-size:12px;color:#3c5470}.landing-service li svg{color:#00a382;flex-shrink:0}.landing-contact{background:#e8f5f2;border:1px solid #d3e9e3;padding:30px;border-radius:18px;display:flex;gap:24px;align-items:center;justify-content:space-between}.landing-contact p:not(.landing-eyebrow){color:#557771;font-size:13px;margin-top:8px}.landing-support{display:grid;gap:10px;font-size:13px;overflow-wrap:anywhere;min-width:0}.landing-support h3{font-size:14px}.landing-support a{color:#006c65}.landing-footer{display:flex;justify-content:space-between;gap:18px;color:#6c7e90;font-size:12px;padding:28px 0 0}.landing a:focus-visible{outline:3px solid #009c89;outline-offset:5px}
@media(max-width:800px){.landing-hero{grid-template-columns:1fr;gap:30px;padding-top:10px}.landing-overview{transform:none}.landing-service-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.landing-contact{flex-wrap:wrap}.landing h2{font-size:26px}}
@media(max-width:480px){.landing-service-grid{grid-template-columns:1fr}.landing-contact{padding:22px;align-items:flex-start;flex-direction:column}.landing-footer{flex-direction:column}.landing-overview{padding:20px}.landing-service p{min-height:0}}
</style>
