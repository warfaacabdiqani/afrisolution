import { createRouter, createWebHistory } from 'vue-router';
import HomeView from '../views/HomeView.vue';
import NotFoundView from '../views/NotFoundView.vue';
import { useAuthStore } from '../stores/auth';
import LoginView from '../views/LoginView.vue';
import AdminLayout from '../layouts/AdminLayout.vue';
import DashboardPage from '../pages/admin/DashboardPage.vue';
import AdminSimplePage from '../pages/admin/AdminSimplePage.vue';
import ClinicsIndex from '../pages/admin/clinics/ClinicsIndex.vue';
import ClinicCreate from '../pages/admin/clinics/ClinicCreate.vue';
import ClinicShow from '../pages/admin/clinics/ClinicShow.vue';
import OverviewTab from '../pages/admin/clinics/tabs/OverviewTab.vue';
import SubscriptionTab from '../pages/admin/clinics/tabs/SubscriptionTab.vue';
import BranchesTab from '../pages/admin/clinics/tabs/BranchesTab.vue';
import MembersTab from '../pages/admin/clinics/tabs/MembersTab.vue';
import UsageTab from '../pages/admin/clinics/tabs/UsageTab.vue';
import ActivityTab from '../pages/admin/clinics/tabs/ActivityTab.vue';
import SettingsTab from '../pages/admin/clinics/tabs/SettingsTab.vue';
import PlansIndex from '../pages/admin/plans/PlansIndex.vue';
import PlanCreate from '../pages/admin/plans/PlanCreate.vue';
import PlanEdit from '../pages/admin/plans/PlanEdit.vue';
import PlanShow from '../pages/admin/plans/PlanShow.vue';
import PlanOverviewTab from '../pages/admin/plans/tabs/OverviewTab.vue';
import PlanLimitsTab from '../pages/admin/plans/tabs/LimitsTab.vue';
import PlanFeaturesTab from '../pages/admin/plans/tabs/FeaturesTab.vue';
import PlanSubscriptionsTab from '../pages/admin/plans/tabs/SubscriptionsTab.vue';
import PlanActivityTab from '../pages/admin/plans/tabs/ActivityTab.vue';
import SubscriptionsIndex from '../pages/admin/subscriptions/SubscriptionsIndex.vue';
import UsersIndex from '../pages/admin/users/UsersIndex.vue';
import UserFormPage from '../pages/admin/users/UserFormPage.vue';
import RolesIndex from '../pages/admin/users/RolesIndex.vue';
import ClinicsView from '../views/ClinicsView.vue';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', name: 'home', component: HomeView },
        { path: '/app/login', name: 'login', component: LoginView },
        {
            path: '/app/admin',
            component: AdminLayout,
            meta: { auth: true, platform: true, adminLayout: true },
            children: [
                { path: '', name: 'admin.dashboard', component: DashboardPage, meta: { title: 'Dashboard' } },
                { path: 'clinics', name: 'admin.clinics', component: ClinicsIndex, meta: { title: 'Clinics / Tenants' } },
                { path: 'clinics/create', name: 'admin.clinics.create', component: ClinicCreate, meta: { title: 'Add New Clinic' } },
                {
                    path: 'clinics/:id',
                    component: ClinicShow,
                    children: [
                        { path: '', name: 'admin.clinics.show', component: OverviewTab },
                        { path: 'subscription', name: 'admin.clinics.subscription', component: SubscriptionTab },
                        { path: 'branches', name: 'admin.clinics.branches', component: BranchesTab },
                        { path: 'members', name: 'admin.clinics.members', component: MembersTab },
                        { path: 'usage', name: 'admin.clinics.usage', component: UsageTab },
                        { path: 'activity', name: 'admin.clinics.activity', component: ActivityTab },
                        { path: 'settings', name: 'admin.clinics.settings', component: SettingsTab },
                    ],
                },
                { path: 'plans', name: 'admin.plans', component: PlansIndex, meta: { title: 'Subscription Plans' } },
                { path: 'plans/create', name: 'admin.plans.create', component: PlanCreate, meta: { title: 'Create Subscription Plan' } },
                { path: 'plans/:id/edit', name: 'admin.plans.edit', component: PlanEdit, meta: { title: 'Edit Subscription Plan' } },
                { path: 'plans/:id', component: PlanShow, children: [
                    { path: '', name: 'admin.plans.show', component: PlanOverviewTab },
                    { path: 'limits', name: 'admin.plans.limits', component: PlanLimitsTab },
                    { path: 'features', name: 'admin.plans.features', component: PlanFeaturesTab },
                    { path: 'subscriptions', name: 'admin.plans.subscriptions', component: PlanSubscriptionsTab },
                    { path: 'activity', name: 'admin.plans.activity', component: PlanActivityTab },
                ]},
                { path: 'subscriptions', name: 'admin.subscriptions', component: SubscriptionsIndex, meta: { title: 'Subscriptions' } },
                { path: 'users', name: 'admin.users', component: UsersIndex, meta: { title: 'System Users' } },
                { path: 'users/create', name: 'admin.users.create', component: UserFormPage, meta: { title: 'Add Administrator' } },
                { path: 'users/:id/edit', name: 'admin.users.edit', component: UserFormPage, meta: { title: 'Edit Administrator' } },
                { path: 'roles', name: 'admin.roles', component: RolesIndex, meta: { title: 'Roles & Permissions' } },
                { path: 'audit', name: 'admin.audit', component: AdminSimplePage, meta: { title: 'Audit Log' } },
                { path: 'settings', name: 'admin.settings', component: AdminSimplePage, meta: { title: 'System Settings' } },
            ],
        },
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
    if (to.name === 'login' && auth.user) return { name: auth.user.is_platform_admin ? 'admin.dashboard' : 'clinics' };
});

export default router;
