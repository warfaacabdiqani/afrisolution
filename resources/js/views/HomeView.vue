<script setup>
import { computed, ref } from 'vue';
import { RouterLink } from 'vue-router';
import AppIcon from '../components/ui/AppIcon.vue';
import LandingPreview from '../components/landing/LandingPreview.vue';
import { usePlatformSettingsStore } from '../stores/platformSettings';
const settings = usePlatformSettingsStore();
const menuOpen = ref(false);
const menuToggle = ref(null);
const brand = computed(() => settings.values['branding.display_name'] || 'AFRI SOLUTION');
const supportEmail = computed(() => settings.values['general.support_email']);
function closeMenu(restoreFocus = false) {
    menuOpen.value = false;
    if (restoreFocus) menuToggle.value?.focus();
}
const businesses = [
    { title: 'Healthcare / Clinic', text: 'Patients, appointments, doctors & prescriptions', icon: 'doctor', tone: 'blue' },
    { title: 'Dental Clinic', text: 'Dental workflows and patient care', icon: 'dental', tone: 'mint' },
    { title: 'Beauty Salon', text: 'Clients, stylists, services & bookings', icon: 'salon', tone: 'rose' },
    { title: 'Stadium / Sports', text: 'Customers, facilities & bookings', icon: 'sports', tone: 'amber' },
];
const features = [
    { title: 'Customers & Records', text: 'Keep customer or patient information organized.', icon: 'members', tone: 'blue' },
    { title: 'Appointments & Bookings', text: 'Schedule visits, services and facility bookings.', icon: 'calendar', tone: 'mint' },
    { title: 'Team Management', text: 'Manage staff, clinicians, stylists and responsibilities.', icon: 'subscriptions', tone: 'violet' },
    { title: 'Billing & Payments', text: 'Create invoices, collect payments and issue receipts.', icon: 'payment', tone: 'amber' },
    { title: 'Reports', text: 'Turn operational and financial data into useful reports.', icon: 'reports', tone: 'mint' },
    { title: 'Communication', text: 'Keep customers informed through email and WhatsApp.', icon: 'support', tone: 'blue' },
];
const steps = [
    ['Create your account', 'Enter your name, email and account password.'],
    ['Choose your business type', 'Clinic, dental, beauty salon or sports facility.'],
    ['Select your plan', 'Choose the subscription that fits your operation.'],
    ['Start your free trial', 'Add your business details, start your trial and verify your email to continue.'],
];
</script>

<template>
    <div class="landing" @keydown.esc="closeMenu(true)">
        <header class="landing-header">
            <div class="landing-container header-inner">
                <RouterLink to="/" class="landing-brand" aria-label="AFRI SOLUTION home">
                    <img v-if="settings.logo" :src="settings.logo" alt="" class="brand-logo">
                    <span v-else class="brand-monogram" aria-hidden="true"><span>A</span>S</span>
                    <span><strong>{{ brand }}</strong><small>Business Management</small></span>
                </RouterLink>
                <button ref="menuToggle" class="menu-toggle btn-secondary" :aria-expanded="menuOpen" aria-controls="landing-navigation" :aria-label="menuOpen ? 'Close navigation' : 'Open navigation'" @click="menuOpen = !menuOpen"><AppIcon name="menu" :size="22" /></button>
                <nav id="landing-navigation" class="landing-navigation" :class="{ 'is-open': menuOpen }" aria-label="Main navigation" @click="closeMenu()">
                    <div class="nav-sections"><a href="#business-types">Solutions <AppIcon name="chevronRight" :size="13" class="nav-chevron" /></a><a href="#features">Features</a><a href="#how-it-works">How it works</a></div>
                    <div class="nav-actions"><RouterLink to="/app/login" class="btn-secondary landing-button">Sign In</RouterLink><RouterLink to="/app/register" class="btn landing-button">Start Free Trial <span aria-hidden="true">→</span></RouterLink></div>
                </nav>
            </div>
        </header>
        <section class="landing-hero" aria-labelledby="landing-title">
            <div class="landing-container hero-grid">
                <div class="hero-copy">
                    <p class="landing-eyebrow">All your business operations. One platform.</p>
                    <h1 id="landing-title" tabindex="-1">Run your business from one <span>connected workspace.</span></h1>
                    <p class="landing-intro">Manage customers, staff, bookings, billing, reporting, and communication from one platform built for growing businesses.</p>
                    <div class="landing-actions"><RouterLink to="/app/register" class="btn landing-button">Start Free Trial <span aria-hidden="true">→</span></RouterLink><RouterLink to="/app/login" class="btn-secondary landing-button">Sign In</RouterLink></div>
                    <ul class="hero-assurances"><li v-for="point in ['No credit card required', 'Choose your business type', 'Get started in minutes']" :key="point"><AppIcon name="check" :size="14" />{{ point }}</li></ul>
                </div>
                <LandingPreview :brand="brand" />
            </div>
        </section>
        <div class="landing-container landing-sections">
            <section id="business-types" aria-labelledby="business-title">
                <p class="landing-eyebrow">Built for your business</p><h2 id="business-title">Choose your business type</h2>
                <p class="section-description">Afriso is designed for different industries, with the tools you need to manage your daily operations.</p>
                <div class="business-grid"><article v-for="business in businesses" :key="business.title" class="business-card" :class="business.tone"><span class="landing-icon"><AppIcon :name="business.icon" :size="32" /></span><h3>{{ business.title }}</h3><p>{{ business.text }}</p></article></div>
            </section>
            <section id="features" aria-labelledby="features-title">
                <p class="landing-eyebrow">Everything you need</p><h2 id="features-title">Powerful tools for your daily operations</h2>
                <p class="section-description">From customer management to payments and communication, Afriso gives you the tools to run your business efficiently.</p>
                <div class="feature-grid"><article v-for="feature in features" :key="feature.title" class="feature-card" :class="feature.tone"><span class="landing-icon"><AppIcon :name="feature.icon" :size="30" /></span><div><h3>{{ feature.title }}</h3><p>{{ feature.text }}</p></div></article></div>
            </section>
            <section id="how-it-works" aria-labelledby="steps-title">
                <p class="landing-eyebrow">How it works</p><h2 id="steps-title">Start your business workspace in minutes</h2>
                <p class="section-description">Get from registration to a working workspace in a few simple steps.</p>
                <ol class="landing-steps"><li v-for="([title, description], index) in steps" :key="title"><span class="step-number">0{{ index + 1 }}</span><div><h3>{{ title }}</h3><p>{{ description }}</p></div></li></ol>
            </section>
        </div>
        <section class="landing-cta" aria-labelledby="cta-title">
            <div class="landing-container cta-grid">
                <div><p class="landing-eyebrow">Built for growing businesses</p><h2 id="cta-title">Ready to simplify your business?</h2><p>Start your free trial and bring your customers, team and daily operations into one connected workspace.</p><RouterLink to="/app/register" class="btn landing-button">Start Free Trial <span aria-hidden="true">→</span></RouterLink></div>
                <ul><li v-for="point in ['No credit card required', 'Choose the plan that fits your business', 'One workspace for your daily operations']" :key="point"><AppIcon name="check" :size="18" />{{ point }}</li></ul>
            </div>
        </section>
        <footer class="landing-container landing-footer">
            <div class="footer-brand"><RouterLink to="/" class="landing-brand"><img v-if="settings.logo" :src="settings.logo" alt="" class="brand-logo"><span v-else class="brand-monogram" aria-hidden="true"><span>A</span>S</span><span><strong>{{ brand }}</strong><small>Business Management</small></span></RouterLink><p>© {{ new Date().getFullYear() }} Afriso. All rights reserved.</p></div>
            <nav aria-label="Product"><h2>Product</h2><a href="#features">Features</a><a href="#business-types">Business Types</a><a href="#how-it-works">How it works</a></nav>
            <nav aria-label="Get started"><h2>Get started</h2><RouterLink to="/app/register">Start Free Trial</RouterLink><RouterLink to="/app/login">Sign In</RouterLink></nav>
            <nav v-if="supportEmail" aria-label="Support"><h2>Support</h2><a :href="`mailto:${supportEmail}`">Contact support</a></nav>
            <p class="footer-note"><span aria-hidden="true">♥</span> Built for growing businesses</p>
        </footer>
    </div>
</template>

<style scoped>
.landing{color:#102344;background:#fff;font-size:15px;line-height:1.6}
.landing h3{color:#102344}#landing-title{scroll-margin-top:100px}
.landing-container{width:calc(100% - 80px);max-width:1240px;margin-inline:auto}
.landing-header{position:sticky;top:0;z-index:30;background:#fffffffa;border-bottom:1px solid #e8f0f3;box-shadow:0 2px 12px #123b4c04}
.header-inner{display:flex;align-items:center;gap:48px;min-height:78px}
.landing-brand{display:flex;align-items:center;gap:16px;flex-shrink:0;line-height:1.3;max-width:100%}
.landing-brand strong{display:block;font-size:19px;font-weight:750;overflow-wrap:anywhere}.landing-brand small{display:block;font-size:12px;margin-top:3px}
.brand-monogram{display:inline-flex;font-size:33px;letter-spacing:-5px;font-weight:700;padding-right:5px;color:#008977}.brand-monogram>span{color:#ed9915}.brand-logo{width:46px;height:46px;object-fit:contain}
.landing-navigation{display:flex;align-items:center;justify-content:space-between;flex:1;gap:24px;font-size:14px}.nav-sections,.nav-actions{display:flex;align-items:center;gap:30px}.nav-actions{gap:14px}.nav-sections a{display:flex;align-items:center;gap:8px;padding-block:12px}.nav-chevron{transform:rotate(90deg)}
.landing-button{display:inline-flex;align-items:center;justify-content:center;gap:12px;min-height:48px;padding:11px 25px;font-size:14px;font-weight:650;border-radius:10px;white-space:nowrap}
.landing .btn{background:linear-gradient(120deg,#00665e,#007e6d);box-shadow:0 3px 8px #00685d12}.landing .btn:hover{background:#00584f}.landing .btn-secondary{border-color:#d3e1eb;background:#ffffff70;color:#102344}.landing .btn-secondary:hover{background:#edf6f5}
.landing a:focus-visible,.landing button:focus-visible{outline:3px solid #008979;outline-offset:4px}.landing a:not(.landing-button):hover{color:#007c6c}.menu-toggle{display:none}
.landing-hero{position:relative;isolation:isolate;overflow:hidden;background:radial-gradient(ellipse at 78% 42%,#e3f3f1 0,transparent 65%),linear-gradient(115deg,#f7fbff,#f5fbfc);border-bottom:1px solid #f0f6f8}
.landing-hero::before{content:'';position:absolute;z-index:-1;width:660px;height:660px;right:-90px;top:-460px;border:70px solid #00897906;border-radius:50%}
.hero-grid{display:grid;grid-template-columns:minmax(0,.95fr) minmax(0,1.15fr);align-items:center;gap:44px;padding-block:60px 64px}
.landing-eyebrow{font-size:11px;font-weight:750;letter-spacing:.15em;text-transform:uppercase;color:#006f62;margin-bottom:12px}
.landing h1{font-size:clamp(36px,3.6vw,52px);font-weight:750;line-height:1.12;letter-spacing:-.035em;max-width:540px}.landing h1 span{color:#008573}
.landing-intro{color:#4c6282;font-size:16px;line-height:1.7;margin:20px 0 24px;max-width:510px}.landing-actions{display:flex;gap:18px;flex-wrap:wrap}.landing-actions .landing-button{min-height:52px;padding-inline:30px}
.hero-assurances{display:flex;flex-wrap:wrap;gap:10px 18px;margin-top:24px;font-size:11px}.hero-assurances li{display:flex;align-items:center;gap:6px}.hero-assurances svg{background:#cceee7;color:#00776a;border-radius:50%;flex-shrink:0}
.landing-sections{display:grid;gap:46px;padding-block:34px 48px}.landing section[id]{scroll-margin-top:100px}.landing h2{font-size:30px;font-weight:750;line-height:1.25;letter-spacing:-.025em}.section-description{margin:7px 0 24px;color:#4c6282;font-size:16px}
.business-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:20px}.business-card{border:1px solid var(--border);border-radius:15px;background:linear-gradient(125deg,var(--wash),#fff);padding:25px 26px 30px;min-height:215px}
.landing-icon{display:inline-grid;place-items:center;flex-shrink:0;width:62px;height:62px;border-radius:19px;background:var(--icon-bg);color:var(--accent)}
.blue{--wash:#f0f7ff;--border:#e9f2fd;--icon-bg:#e2f0ff;--accent:#006aeb}.mint{--wash:#effaf6;--border:#e6f4ee;--icon-bg:#d9fcf0;--accent:#008c6e}.rose{--wash:#fff3f7;--border:#fcecf2;--icon-bg:#ffe3ec;--accent:#d62b56}.amber{--wash:#fff8ed;--border:#fcf1e2;--icon-bg:#fff0d4;--accent:#ac6500}.violet{--icon-bg:#f2eaff;--accent:#803de5}
.landing h3{font-size:16px;font-weight:700;line-height:1.4}.business-card h3{margin:17px 0 7px;font-size:18px}.business-card p,.feature-card p,.landing-steps p{color:#4c6282;line-height:1.6;font-size:14px}
.feature-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px 20px}.feature-card{display:flex;align-items:center;gap:20px;padding:20px;border:1px solid #e5edf3;border-radius:13px;background:#fff;box-shadow:0 4px 14px #244c6407}.feature-card p{margin-top:4px}.feature-card .landing-icon{width:64px;height:64px}
.landing-steps{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:28px;margin-top:30px}.landing-steps li{display:flex;gap:18px;position:relative}.step-number{display:grid;place-items:center;width:48px;height:48px;flex-shrink:0;border-radius:50%;background:linear-gradient(120deg,#00786b,#009d82);color:#fff;font-size:18px;font-weight:650}.landing-steps h3{font-size:14px;margin-bottom:7px}.landing-steps li:not(:last-child)::after{content:'';position:absolute;width:18px;right:-22px;top:23px;border-top:2px dashed #aed2d7}
.landing-cta{position:relative;isolation:isolate;overflow:hidden;background:linear-gradient(105deg,#e7f8ef,#f0faf6);padding-block:34px}.landing-cta::after{content:'';position:absolute;z-index:-1;right:-165px;top:-305px;width:480px;height:480px;border-radius:50%;border:48px solid #a6e3ce70;box-shadow:0 0 0 50px #a6e3ce30}
.cta-grid{display:grid;grid-template-columns:1.25fr .85fr;gap:80px;align-items:center;padding-inline:20px}.landing-cta p:not(.landing-eyebrow){max-width:620px;color:#4c6282;font-size:16px;margin:8px 0 18px}.landing-cta ul{display:grid;gap:14px;font-size:14px;color:#4c6282}.landing-cta li{display:flex;align-items:center;gap:18px}.landing-cta li svg{color:#007f6c;flex-shrink:0}
.landing-footer{display:flex;justify-content:space-between;flex-wrap:wrap;gap:30px;padding-block:34px 40px}.footer-brand>p{font-size:12px;color:#4c6282;margin-top:30px}.landing-footer nav{display:flex;flex-direction:column;gap:5px;font-size:13px;color:#4c6282}.landing-footer nav h2{font-size:14px;letter-spacing:0;margin-bottom:8px;color:#102344}.footer-note{align-self:center;color:#4c6282;font-size:12px}.footer-note span{font-size:21px;color:#008b76;vertical-align:middle;margin-right:6px}
@media(min-width:1280px){.hero-grid{min-height:525px}}
@media(max-width:1100px){.header-inner{gap:30px}.nav-sections{gap:20px}.nav-actions{gap:10px}.nav-actions .landing-button{padding-inline:18px}.hero-grid{gap:28px}.hero-assurances{gap:10px}.business-card{padding:22px 20px}.business-card h3{font-size:16px}.feature-card{gap:14px;padding:17px}.feature-card .landing-icon{width:54px;height:54px}.landing-steps{gap:22px}.landing-steps li{gap:12px}.landing-steps li::after{display:none}}
@media(max-width:1023px){.landing-container{width:calc(100% - 48px)}.header-inner{gap:24px}.landing-brand{gap:10px}.landing-brand strong{font-size:16px}.landing-navigation{gap:16px}.nav-sections{gap:16px}.nav-actions .landing-button{padding-inline:13px;font-size:12px}.hero-grid{grid-template-columns:1fr;gap:38px;padding-block:40px}.hero-copy{max-width:660px}.landing h1{max-width:650px;font-size:46px}.business-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.business-card{min-height:205px}.feature-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.landing-steps{grid-template-columns:repeat(2,minmax(0,1fr));gap:28px}.cta-grid{gap:30px;padding-inline:0}.landing h2{font-size:27px}}
@media(max-width:767px){.header-inner{min-height:72px;justify-content:space-between;position:relative}.menu-toggle{display:grid;place-items:center;width:44px;height:44px;flex-shrink:0}.landing-navigation{display:none;position:absolute;top:100%;left:-24px;right:-24px;padding:20px 24px;background:white;border-bottom:1px solid #dce8ec;box-shadow:0 12px 20px #173b5310}.landing-navigation.is-open{display:flex;align-items:stretch;flex-direction:column}.nav-sections{align-items:stretch;flex-direction:column;gap:0}.nav-sections a{padding:12px}.nav-actions .landing-button{flex:1;font-size:14px}.cta-grid{grid-template-columns:1fr;gap:24px}.landing-footer{gap:28px 40px}.footer-brand{flex-basis:100%}.footer-brand>p{margin-top:18px}.footer-note{flex-basis:100%}}
@media(max-width:639px){.landing-container{width:calc(100% - 40px)}.landing-navigation{left:-20px;right:-20px}.hero-grid{padding-block:34px 38px;gap:28px}.landing h1{font-size:clamp(34px,8.4vw,46px)}.landing-eyebrow{font-size:10px;letter-spacing:.12em}.landing-intro,.section-description{font-size:15px}.landing-actions{gap:12px}.landing-actions .landing-button{padding-inline:22px}.hero-assurances{gap:10px 14px}.landing-sections{gap:34px;padding-block:30px 36px}.landing h2{font-size:25px}.business-grid{gap:12px}.business-card{padding:18px 15px;min-height:220px}.business-card h3{font-size:16px}.business-card p{font-size:13px}.landing-icon{width:54px;height:54px;border-radius:16px}.feature-grid{grid-template-columns:1fr;gap:12px}.feature-card{padding:18px}.landing-steps{grid-template-columns:1fr;gap:24px;margin-top:24px}.landing-steps li{gap:18px}.landing-steps li:not(:last-child)::after{display:block;left:23px;right:auto;top:54px;bottom:-18px;width:0;border-top:0;border-left:2px dashed #c5e2dd}.landing-steps h3{font-size:15px}.landing-cta{padding-block:30px}.landing-footer{padding-block:28px}.landing-brand strong{font-size:17px}}
@media(max-width:359px){.business-grid{grid-template-columns:1fr}.business-card{min-height:0}.landing-brand{gap:10px}.landing-brand strong{font-size:15px}.landing-actions .landing-button{padding-inline:19px}}
</style>
