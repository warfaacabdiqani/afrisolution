import { defineStore } from 'pinia';
import api from '../services/api';
import { useAuthStore } from './auth';

export const useClinicContextStore = defineStore('clinicContext', {
    state: () => ({ data: null, error: null, busy: false }),
    getters: {
        modules: state => state.data?.modules.filter(module => module.allowed) || [],
    },
    actions: {
        can(permission) { return this.data?.permissions.some(p => p === '*' || p === permission) || false; },
        allowed(key) { return this.modules.some(module => module.key === key); },
        headers() { return { 'X-Clinic-Context': String(useAuthStore().user?.active_tenant_id || ''), 'X-Branch-Context': String(this.data?.branch?.id || '') }; },
        async load() {
            this.data = null; this.error = null; this.busy = true;
            try { this.data = (await api.get('/v1/clinic/context')).data.data; }
            catch (error) { this.error = error; }
            finally { this.busy = false; }
        },
        async switchBranch(id) {
            this.error = null; this.busy = true;
            try { this.data = (await api.post('/v1/clinic/branch', { branch_id: id }, { headers: this.headers() })).data.data; }
            catch (error) { this.data = null; this.error = error; throw error; }
            finally { this.busy = false; }
        },
    },
});
