import { defineStore } from 'pinia';
import { useClinicContextStore } from './clinicContext';
import { useClinicSettingsStore } from './clinicSettings';
import { useClinicDashboardStore } from './clinicDashboard';
import { ref } from 'vue';
import api from '../services/api';

export const useAuthStore = defineStore('auth', () => {
    const user = ref(null);
    const loaded = ref(false);
    function reset() { user.value = null; loaded.value = true; }
    async function restore() {
        try { user.value = (await api.get('/v1/session')).data.data; }
        catch (error) {
            if (error.response?.status !== 401) throw error;
            user.value = null;
        }
        loaded.value = true;
    }
    async function login(credentials) {
        await api.get('/sanctum/csrf-cookie', { baseURL: '/' });
        user.value = (await api.post('/login', credentials, { baseURL: '/' })).data.data;
        loaded.value = true;
    }
    async function logout() {
        await api.post('/logout', {}, { baseURL: '/' });
        user.value = null;
        loaded.value = true;
    }
    async function selectClinic(id) {
        useClinicDashboardStore().clear();
        useClinicContextStore().clear();
        const settings = useClinicSettingsStore(); settings.version++; settings.data = null; settings.scope = ''; settings.notice = '';
        user.value = (await api.post('/v1/session/clinic', { clinic_id: id })).data.data;
        await useClinicContextStore().load();
    }
    return { user, loaded, restore, login, logout, selectClinic, reset };
});
