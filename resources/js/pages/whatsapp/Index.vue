<script setup>
import WhatsAppSettings from '../../components/settings/WhatsAppSettings.vue';

defineProps({ section: { type: String, required: true } });
</script>

<template>
    <div>
        <header class="patient-page-header">
            <div>
                <p class="patient-breadcrumb">Communications <span>/</span> WhatsApp</p>
                <h1>WhatsApp</h1>
                <p>Manage WhatsApp communication with your customers.</p>
            </div>
            <RouterLink class="btn-secondary" to="/app/settings/integrations">Connection settings</RouterLink>
        </header>
        <nav class="mb-5 flex gap-2" aria-label="Business WhatsApp sections"><RouterLink v-for="item in [['messages','Messages'],['templates','Templates']]" :key="item[0]" :to="`/app/whatsapp/${item[0]}`" class="rounded-lg px-4 py-2 text-sm font-semibold" :class="section === (item[0] === 'messages' ? 'history' : 'templates') ? 'bg-teal-700 text-white' : 'border border-slate-200'">{{ item[1] }}</RouterLink></nav>
        <section class="clinic-panel p-6">
            <WhatsAppSettings :initial-section="section" hide-nav />
        </section>
    </div>
</template>
