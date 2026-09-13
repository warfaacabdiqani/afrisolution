import { defineStore } from 'pinia';
import api from '../services/api';
import { useAuthStore } from './auth';

export const useClinicContextStore = defineStore('clinicContext', {
    state: () => ({ data: null, error: null, busy: false }),
    getters: {
        modules: state => state.data?.modules.filter(module => module.allowed) || [],
        businessType: state => state.data?.business_type || null,
        businessTypeSlug: state => state.data?.business_type?.slug || null,
        navigationProfileKey: state => state.data?.navigation_profile_key || null,
        dashboardProfileKey: state => state.data?.dashboard_profile_key || null,
        labels: state => state.data?.labels || {},
        businessModules: state => state.data?.business_modules || {},
        businessProfile: state => state.data?.business_profile || null,
        label: state => (key, fallback = '') => state.labels[key] || fallback,
        hasBusinessModule: state => (key) => {
            const aliases = {
                patients: 'patients',
                appointments: 'bookings',
                doctors: 'staff',
                consultations: 'clinical',
                prescriptions: 'prescriptions',
                pharmacy: 'pharmacy',
                billing: 'billing',
                reports: 'reports',
                staff: 'staff',
                support: 'support',
                settings: 'settings',
                dashboard: 'dashboard',
            };
            const normalized = aliases[key] || key;
            if (!(state.data?.business_modules && Object.prototype.hasOwnProperty.call(state.data.business_modules, normalized))) {
                return true;
            }
            return !!state.data.business_modules[normalized];
        },
    },
    actions: {
        can(permission) { return this.data?.permissions.some(p => p === '*' || p === permission) || false; },
        allowed(key) { return this.modules.some(module => module.key === key); },
        headers() { return { 'X-Clinic-Context': String(useAuthStore().user?.active_tenant_id || ''), 'X-Branch-Context': String(this.data?.branch?.id || '') }; },
        async heartbeat() { await api.get('/v1/clinic/context'); },
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
