import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import api from '../services/api';

export const usePlatformSettingsStore = defineStore('platformSettings', () => {
    const values = ref({});
    const loaded = ref(false);
    const name = computed(() => values.value['branding.display_name'] || values.value['general.platform_name'] || 'Afri Clinic');
    const footer = computed(() => values.value['branding.footer_text'] || 'Healthcare SaaS');
    const logo = computed(() => values.value['branding.logo'] || null);
    const smallLogo = computed(() => values.value['branding.small_logo'] || null);
    async function load() { if (loaded.value) return; try { values.value = (await api.get('/v1/public/settings')).data.data; } finally { loaded.value = true; } }
    return { values, loaded, name, footer, logo, smallLogo, load };
});
