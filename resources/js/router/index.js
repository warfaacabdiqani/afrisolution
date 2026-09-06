import { createRouter, createWebHistory } from 'vue-router';
import HomeView from '../views/HomeView.vue';
import NotFoundView from '../views/NotFoundView.vue';
import { useAuthStore } from '../stores/auth';
import LoginView from '../views/LoginView.vue';
import PlatformView from '../views/PlatformView.vue';
import ClinicsView from '../views/ClinicsView.vue';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', name: 'home', component: HomeView },
        { path: '/app/login', name: 'login', component: LoginView },
        { path: '/app/admin', name: 'admin', component: PlatformView, meta: { auth: true, platform: true } },
        { path: '/app/clinics', name: 'clinics', component: ClinicsView, meta: { auth: true } },
        { path: '/app', redirect: { name: 'home' } },
        { path: '/:pathMatch(.*)*', name: 'not-found', component: NotFoundView },
    ],
    scrollBehavior: () => ({ top: 0 }),
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();
    if (!auth.loaded) await auth.restore();
    if (to.meta.auth && !auth.user) return { name: 'login' };
    if (to.meta.platform && !auth.user?.is_platform_admin) return { name: 'clinics' };
    if (to.name === 'login' && auth.user) return { name: auth.user.is_platform_admin ? 'admin' : 'clinics' };
});

export default router;
