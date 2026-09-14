import { computed } from 'vue';
import { useClinicContextStore } from '../stores/clinicContext';
export function useBusinessContext() {
    const context = useClinicContextStore();
    return {
        label: (key, fallback = key) => context.labels[key] || fallback,
        settingsLabel: computed(() => context.businessProfile?.settings_label || 'Business Settings'),
        businessType: computed(() => context.businessType),
        moduleEnabled: key => context.hasBusinessModule(key),
    };
}
