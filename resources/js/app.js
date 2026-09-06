import './bootstrap';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import api from './services/api';
import { useAuthStore } from './stores/auth';

const app = createApp(App);
const pinia = createPinia();
app.use(pinia);
api.interceptors.request.use(config => {
    const selected = useAuthStore(pinia).user?.active_tenant_id;
    if (config.url?.startsWith('/v1/clinic/') && selected) config.headers['X-Clinic-Context'] = String(selected);
    return config;
});
api.interceptors.response.use(response => response, error => {
    if (error.response?.status === 401 && useAuthStore(pinia).user) {
        useAuthStore(pinia).reset();
        router.replace('/app/login');
    }
    return Promise.reject(error);
});
app.use(router).mount('#app');
