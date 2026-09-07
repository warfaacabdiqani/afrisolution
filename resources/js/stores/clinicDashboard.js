import { defineStore } from 'pinia';
import api from '../services/api';
import { useClinicContextStore } from './clinicContext';

export const useClinicDashboardStore = defineStore('clinicDashboard', {
    state: () => ({ data: null, busy: false, error: null, generation: 0 }),
    actions: {
        async load() {
            const generation = ++this.generation;
            this.data = null; this.error = null; this.busy = true;
            try {
                const response = await api.get('/v1/clinic/dashboard', { headers: useClinicContextStore().headers() });
                if (generation === this.generation) this.data = response.data.data;
            } catch (error) { if (generation === this.generation) this.error = error; }
            finally { if (generation === this.generation) this.busy = false; }
        },
    },
});
