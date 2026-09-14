import { defineStore } from 'pinia';
import api from '../services/api';
import { useClinicContextStore } from './clinicContext';

export const useClinicDashboardStore = defineStore('clinicDashboard', {
    state: () => ({ data: null, busy: false, error: null, generation: 0 }),
    getters: {
        profile: state => state.data?.profile || null,
        widgets: state => state.data?.widgets || [],
        sections: state => state.data?.sections || {},
        quickActions: state => state.data?.quick_actions || [],
        business: state => state.data?.business || null,
    },
    actions: {
        clear() { this.generation++; this.data = null; this.error = null; this.busy = false; },
        async load() {
            const generation = ++this.generation;
            const tenant = useClinicContextStore().data?.clinic.id;
            this.data = null; this.error = null; this.busy = true;
            try {
                const response = await api.get('/v1/clinic/dashboard', { headers: useClinicContextStore().headers() });
                if (generation === this.generation && tenant === useClinicContextStore().data?.clinic.id && response.data.data.business.id === tenant) this.data = response.data.data;
            } catch (error) { if (generation === this.generation) this.error = error; }
            finally { if (generation === this.generation) this.busy = false; }
        },
    },
});
