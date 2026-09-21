<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import AppIcon from '../ui/AppIcon.vue';
import { usePlatformSettingsStore } from '../../stores/platformSettings';
const props = defineProps({ registration: { type: Boolean, default: false } });
const settings = usePlatformSettingsStore();
const logo = computed(() => settings.values['branding.login_logo'] || settings.logo);
const loginBenefits = [
    ['members', 'Manage your customers', 'Keep customer records organized'],
    ['calendar', 'Appointments & bookings', 'Schedule services and manage availability'],
    ['payment', 'Billing & payments', 'Create invoices and collect payments'],
    ['subscriptions', 'Your team', 'Manage staff and responsibilities'],
    ['reports', 'Reports & insights', 'Make better decisions with real data'],
];
const businessTypes = [['activity', 'Clinic'], ['dental', 'Dental'], ['salon', 'Beauty'], ['sports', 'Stadium']];
const benefits = computed(() => props.registration ? [
    ['members', 'Manage your customers', 'Keep customer information organized'],
    ['calendar', 'Appointments & bookings', 'Manage schedules and services'],
    ['payment', 'Billing & payments', 'Create invoices and collect payments'],
    ['subscriptions', 'Your team', 'Manage staff and responsibilities'],
] : loginBenefits);
</script>
<template>
        <section class="login-story" :class="{ 'registration-story': registration }" aria-label="About AFRI SOLUTION">
            <RouterLink to="/" class="login-brand" aria-label="AFRI SOLUTION home">
                <img v-if="logo" :src="logo" alt="AFRISO" class="login-official-logo">
                <span><strong>AFRI SOLUTION</strong><small>Business Management</small></span>
            </RouterLink>
            <div class="login-story-content">
                <div class="login-message" :class="{ 'registration-message': registration }">
                    <p class="login-eyebrow">One platform. Many possibilities.</p>
                    <h2>{{ registration ? 'Start your business' : 'Run your business' }}<br> {{ registration ? 'with one' : 'from one' }} <span>connected workspace.</span></h2>
                    <p v-if="registration" class="login-description">Bring your customers, team, bookings, billing and daily operations together in one business platform.</p><p v-else class="login-description">Manage customers, staff, bookings, billing, reporting and communication &mdash; all in one platform for growing businesses.</p>
                    <ul class="login-benefits" :class="{ 'registration-benefits': registration }"><li v-for="[icon, title, description] in benefits" :key="title"><span class="benefit-icon"><AppIcon :name="icon" :size="24" /></span><div><strong>{{ title }}</strong><p>{{ description }}</p></div></li></ul>
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
</template>
<style scoped>

.login-story{position:relative;isolation:isolate;overflow:hidden;display:flex;flex-direction:column;min-height:100svh;padding:32px 0 28px clamp(28px,4vw,76px);background:radial-gradient(ellipse at 87% 18%,#cee6dd 0,transparent 47%),linear-gradient(110deg,#f4fbfb 34%,#e5f1ed 100%)}
.login-story::after{content:'';position:absolute;z-index:-1;right:-8%;bottom:0;width:70%;height:22%;background:linear-gradient(170deg,#d6c7af30,#d6c7af80);border-radius:50% 0 0 0;filter:blur(8px)}
.login-brand{display:inline-flex;align-items:center;gap:20px;align-self:flex-start;position:relative;z-index:2;max-width:100%;line-height:1.2}.login-official-logo{width:clamp(100px,8vw,155px);max-height:64px;object-fit:contain}.login-brand:has(img)>span{border-left:1px solid #cbdadf;padding-left:20px}.login-brand strong{display:block;font-size:clamp(22px,1.7vw,31px);font-weight:750;letter-spacing:-.02em}.login-brand small{display:block;color:#526d87;font-size:14px;margin-top:5px}
.login-story-content{display:flex;align-items:center;flex:1;position:relative;min-height:0;padding-block:32px 0}.login-message{position:relative;z-index:2;width:37%;max-width:410px}.login-eyebrow{color:#008477;font-size:10px;letter-spacing:.18em;text-transform:uppercase;font-weight:700;margin-bottom:16px}.login-message h2{font-size:clamp(28px,2.3vw,43px);line-height:1.18;font-weight:750;letter-spacing:-.035em}.login-message h2 span{color:#008578}.login-description{font-size:clamp(14px,.98vw,18px);line-height:1.55;color:#58728d;margin-top:16px}.login-benefits{display:grid;gap:14px;margin-top:22px}.login-benefits li{display:flex;gap:15px;align-items:center}.benefit-icon{display:grid;place-items:center;width:48px;height:48px;background:#def5ef;color:#008777;border-radius:50%;flex-shrink:0}.login-benefits strong{font-size:14px;font-weight:700;line-height:1.35;display:block}.login-benefits p{color:#58728d;font-size:12px;line-height:1.5;margin-top:4px}.login-tagline{display:flex;align-items:center;gap:12px;color:#5c7690;font-size:11px;line-height:1.5;font-style:italic;margin-top:26px}.login-tagline>span{height:2px;width:44px;background:#009787;flex-shrink:0}.login-business-types{display:flex;justify-content:space-between;max-width:330px;gap:12px;margin-top:28px}.login-business-types li{display:flex;flex-direction:column;align-items:center;gap:9px;color:#008b7e;font-size:12px}.login-business-types li>span{color:#56718b}
.login-product{position:absolute;width:59%;right:3%;top:50%;transform:translateY(-44%);margin:0;perspective:1600px}.laptop-screen{position:relative;background:#171d21;border:2px solid #747d7e;border-radius:21px 21px 9px 9px;padding:17px 10px 12px;box-shadow:0 25px 45px #133a3930,inset 0 0 0 3px #303a3a;transform:rotateY(-5deg) rotateZ(1deg);transform-origin:bottom center}.laptop-camera{position:absolute;top:6px;left:50%;height:4px;width:4px;border-radius:50%;background:#49626b;box-shadow:0 0 0 2px #101719}.laptop-screen img{display:block;width:100%;height:auto;object-fit:contain;border-radius:4px;background:#f6f9fc}.laptop-base{position:relative;width:112%;height:25px;margin-left:-6%;background:linear-gradient(#c7cecf,#8c989b 45%,#c9cece 48%,#566065);border-radius:2px 2px 50% 50% / 2px 2px 85% 85%;box-shadow:0 15px 16px #334c4928;transform:rotateZ(1deg)}.laptop-base>span{display:block;margin:auto;width:18%;height:8px;background:linear-gradient(#778589,#d8dddd);border-radius:0 0 9px 9px}.login-product figcaption{color:#52716e;text-align:center;font-size:10px;margin-top:20px}.login-product figcaption>span{margin-inline:7px}
@media(min-width:1700px) and (min-height:850px){.login-benefits{gap:12px;margin-top:22px}.login-benefits strong{font-size:16px}.login-benefits p{font-size:14px}.login-eyebrow{font-size:11px}.benefit-icon{width:50px;height:50px}.login-tagline{font-size:12px;margin-top:28px}.login-business-types{margin-top:32px}}
@media(max-width:1399px){.login-story{padding-left:30px}.login-message{width:39%}.login-product{width:57%;right:1.5%}.login-brand{gap:15px}.login-brand:has(img)>span{padding-left:15px}.login-benefits{gap:12px}.login-benefits li{gap:10px}.benefit-icon{width:40px;height:40px}.benefit-icon svg{width:21px;height:21px}.login-benefits strong{font-size:13px}.login-benefits p{font-size:11px}.login-business-types{margin-top:22px}.login-tagline{gap:9px;font-size:10px}.login-tagline>span{width:25px}}
@media(max-width:1199px){.login-story{padding:32px}.login-message{width:100%;max-width:420px}.login-story-content{padding-top:34px}.login-product{display:none}.login-message h2{max-width:380px;font-size:36px}.login-description{max-width:350px;font-size:15px}.login-benefits p{font-size:12px}.login-benefits strong{font-size:14px}.login-brand strong{font-size:22px}.login-official-logo{width:90px}.login-brand{gap:12px}.login-brand:has(img)>span{padding-left:12px}.login-brand small{font-size:12px}}
@media(max-width:1023px){.login-story{min-height:0;padding:28px 32px;background:#f0f9f7}.login-story-content{display:none}.login-story::after{display:none}.login-brand strong{font-size:23px}}
@media(max-width:480px){.login-story{padding:23px 22px}.login-brand strong{font-size:21px}.login-official-logo{width:85px;max-height:48px}.login-brand{gap:12px}.login-brand:has(img)>span{padding-left:12px}.login-brand small{font-size:12px}}
@media(max-width:359px){.login-story{padding-inline:18px}.login-brand strong{font-size:18px}.login-official-logo{width:64px}.login-brand small{font-size:11px}}
@media(min-width:1024px) and (max-height:900px){.login-story{padding-top:24px;padding-bottom:22px}.login-story-content{padding-top:24px}.login-message h2{font-size:clamp(28px,2.2vw,36px)}.login-eyebrow{margin-bottom:12px}.login-description{font-size:14px;margin-top:12px}.login-benefits{gap:10px;margin-top:16px}.benefit-icon{width:38px;height:38px}.benefit-icon svg{width:21px;height:21px}.login-benefits strong{font-size:13px}.login-benefits p{font-size:11px;margin-top:2px}.login-tagline{margin-top:18px;font-size:10px}.login-business-types{margin-top:20px}.login-business-types li{gap:6px;font-size:11px}.login-business-types svg{width:24px;height:24px}}
@media(min-width:1024px) and (max-height:720px){.login-story{padding-top:18px;padding-bottom:18px}.login-brand strong{font-size:22px}.login-brand small{font-size:12px}.login-official-logo{max-height:44px}.login-story-content{padding-top:20px}.login-message h2{font-size:28px}.login-eyebrow{font-size:9px;margin-bottom:8px}.login-description{font-size:12px;margin-top:10px}.login-benefits{gap:8px;margin-top:12px}.benefit-icon{width:32px;height:32px}.benefit-icon svg{width:19px;height:19px}.login-benefits strong{font-size:12px}.login-benefits p{font-size:10px}.login-tagline{margin-top:12px;font-size:9px}.login-business-types{margin-top:14px}.login-business-types svg{width:22px;height:22px}.login-product{max-width:650px}}
/* Size the decorative device from available height as well as width. */
@media(min-width:1200px){.login-product{max-width:calc(119svh - 286px)}}

.login-brand:focus-visible{outline:3px solid #008b7d;outline-offset:4px}
@media(min-width:1200px){.registration-story{padding-left:clamp(28px,3vw,58px)}.registration-story .login-message{width:43%;max-width:380px}.registration-story .login-message h2{font-size:clamp(28px,2vw,38px)}.registration-story .login-product{width:51%;right:2%}.registration-story .login-description{font-size:14px}.registration-story .login-benefits li{gap:10px}.registration-story .login-benefits strong{font-size:13px}.registration-story .login-benefits p{font-size:11px}}
@media(min-width:1024px) and (max-height:720px){.registration-story .login-message h2{font-size:27px}.registration-story .login-description{font-size:12px}.registration-story .login-benefits strong{font-size:12px}.registration-story .login-benefits p{font-size:10px}}
</style>
