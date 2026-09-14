import { defineStore } from 'pinia';
import api from '../services/api';
import { useAuthStore } from './auth';

let requestSequence = 0;
export const useClinicContextStore = defineStore('clinicContext', {
    state: () => ({ data: null, error: null, busy: false, loadToken: null }),
    getters: {
        modules: state => state.data?.modules.filter(module => module.allowed) || [],
        businessType: state => state.data?.business_type || null,
        businessTypeSlug: state => state.data?.business_type?.slug || null,
        navigationProfileKey: state => state.data?.navigation_profile_key || null,
        dashboardProfileKey: state => state.data?.dashboard_profile_key || null,
        labels: state => state.data?.labels || {},
        businessModules: state => state.data?.business_modules || {},
        businessProfile: state => state.data?.business_profile || null,
        label: state => (key, fallback = '') => state.data?.labels?.[key] || fallback,
        hasBusinessModule: state => (key) => {
            const entry = state.data?.modules?.find(module => module.key === key);
            if (entry) return entry.business_allowed === true;
            return state.data?.business_modules?.[key] === true;
        },
    },
    actions: {
        can(permission) { return this.data?.permissions.some(p => p === '*' || p === permission) || false; },
        allowed(key) { return this.modules.some(module => module.key === key); },
        headers() { return { 'X-Clinic-Context': String(useAuthStore().user?.active_tenant_id || ''), 'X-Branch-Context': String(this.data?.branch?.id || '') }; },
        async heartbeat() { await api.get('/v1/clinic/context'); },
        clear() { this.data = null; this.error = null; this.loadToken = null; },
        async load() {
            const token = ++requestSequence; this.loadToken = token;
            const tenant = useAuthStore().user?.active_tenant_id;
            this.data = null; this.error = null; this.busy = true;
            try { const data = (await api.get('/v1/clinic/context')).data.data; if (this.loadToken === token && tenant === useAuthStore().user?.active_tenant_id && data.clinic.id === tenant) this.data = data; }
            catch (error) { if (this.loadToken === token) this.error = error; }
            finally { if (this.loadToken === token) this.busy = false; }
        },
        async switchBranch(id) {
            this.error = null; this.busy = true;
            try { this.data = (await api.post('/v1/clinic/branch', { branch_id: id }, { headers: this.headers() })).data.data; }
            catch (error) { this.data = null; this.error = error; throw error; }
            finally { this.busy = false; }
        },
    },
});
