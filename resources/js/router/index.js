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
import AuditIndex from '../pages/admin/audit/AuditIndex.vue';
import SettingsIndex from '../pages/admin/settings/SettingsIndex.vue';
import ClinicsView from '../views/ClinicsView.vue';
import DoctorsIndex from '../pages/doctors/Index.vue';
import AppointmentsIndex from '../pages/appointments/Index.vue';
import AppointmentFormPage from '../pages/appointments/FormPage.vue';
import PatientAppointments from '../pages/patients/tabs/Appointments.vue';
import DoctorFormPage from '../pages/doctors/FormPage.vue';
import DoctorShow from '../pages/doctors/Show.vue';
import DoctorOverview from '../pages/doctors/tabs/Overview.vue';
import DoctorSchedule from '../pages/doctors/tabs/Schedule.vue';
import DoctorAppointments from '../pages/doctors/tabs/Appointments.vue';
import DoctorActivity from '../pages/doctors/tabs/Activity.vue';
import PatientsIndex from '../pages/patients/Index.vue';
import PatientFormPage from '../pages/patients/FormPage.vue';
import PatientShow from '../pages/patients/Show.vue';
import PatientOverview from '../pages/patients/tabs/Overview.vue';
import PatientHistory from '../pages/patients/tabs/MedicalHistory.vue';
import PatientDocuments from '../pages/patients/tabs/Documents.vue';
import PatientActivity from '../pages/patients/tabs/Activity.vue';
import PatientFuture from '../pages/patients/tabs/Future.vue';
import ConsultationsIndex from '../pages/consultations/Index.vue';
import BillingIndex from '../pages/billing/Index.vue';
import ReportsIndex from '../pages/reports/Index.vue';
import ReportsGenerate from '../pages/reports/Generate.vue';
import ReportsCategory from '../pages/reports/Category.vue';
import PatientsReportsIndex from '../pages/reports/patients/Index.vue';
import PatientRegistrationsReport from '../pages/reports/patients/Registrations.vue';
import PatientDemographicsReport from '../pages/reports/patients/Demographics.vue';
import AppointmentsReportsIndex from '../pages/reports/appointments/Index.vue';
import AppointmentsStatusesReport from '../pages/reports/appointments/Statuses.vue';
import ClinicLayout from '../layouts/ClinicLayout.vue';
import ClinicDashboard from '../pages/clinic/Dashboard.vue';
import ModuleAccess from '../pages/clinic/ModuleAccess.vue';
import { useClinicContextStore } from '../stores/clinicContext';
import api from '../services/api';

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
                { path: 'audit-log', name: 'admin.audit', component: AuditIndex, meta: { title: 'Audit Log' } },
                { path: 'audit', redirect: { name: 'admin.audit' } },
                { path: 'settings', name: 'admin.settings', component: SettingsIndex, meta: { title: 'System Settings' } },
            ],
        },
        { path: '/app', component: ClinicLayout, meta: { auth: true, clinicLayout: true }, children: [
            { path: 'clinics', name: 'clinics', component: ClinicsView, meta: { clinicSelection: true } },
            { path: '', redirect: '/app/dashboard' },
            { path: 'dashboard', name: 'clinic.dashboard', component: ClinicDashboard, meta: { clinicModule: 'dashboard' } },
            { path: 'access', name: 'clinic.access', component: ModuleAccess, meta: { denied: true } },
            { path: 'support', component: ModuleAccess, meta: { title: 'Help & Support', support: true } },
            { path: 'settings', redirect: '/app/settings/general', meta: { clinicModule: 'settings' } },
            { path: 'settings/:section', component: () => import('../pages/settings/Index.vue'), meta: { clinicModule: 'settings', clinicSettings: true } },
            { path: 'prescriptions', component: () => import('../pages/prescriptions/Index.vue'), meta: { clinicModule: 'prescriptions' } },
            { path: 'prescriptions/create', component: () => import('../pages/prescriptions/FormPage.vue'), meta: { clinicModule: 'prescriptions', permission: 'prescriptions.create' } },
            { path: 'prescriptions/:id/edit', component: () => import('../pages/prescriptions/FormPage.vue'), meta: { clinicModule: 'prescriptions', permission: 'prescriptions.update' } },
            { path: 'prescriptions/:id/print', component: () => import('../pages/prescriptions/Print.vue'), meta: { clinicModule: 'prescriptions', permission: 'prescriptions.print' } },
            { path: 'prescriptions/:id', component: () => import('../pages/prescriptions/Show.vue'), meta: { clinicModule: 'prescriptions' } },
            { path: 'patients', component: PatientsIndex, meta: { clinicModule: 'patients', patientList: true } },
            { path: 'appointments', component: AppointmentsIndex, meta: { clinicModule: 'appointments', appointmentCalendar: true } },
            { path: 'appointments/create', component: AppointmentFormPage, meta: { clinicModule: 'appointments', permission: 'appointments.create' } },
            { path: 'appointments/:id/edit', component: AppointmentFormPage, meta: { clinicModule: 'appointments', permission: 'appointments.update' } },
            { path: 'appointments/:id/reschedule', component: AppointmentFormPage, meta: { clinicModule: 'appointments', permission: 'appointments.reschedule', reschedule: true } },
            { path: 'appointments/:id', component: AppointmentsIndex, meta: { clinicModule: 'appointments', appointmentCalendar: true } },
            { path: 'consultations', component: ConsultationsIndex, meta: { clinicModule: 'consultations', permission: 'consultations.view' } },
            { path: 'doctors', component: DoctorsIndex, meta: { clinicModule: 'doctors', doctorList: true } },
            { path: 'doctors/create', component: DoctorFormPage, meta: { clinicModule: 'doctors', permission: 'doctors.create' } },
            { path: 'doctors/:id/edit', component: DoctorFormPage, meta: { clinicModule: 'doctors', permission: 'doctors.update' } },
            { path: 'doctors/:id', component: DoctorShow, meta: { clinicModule: 'doctors' }, children: [
                { path: '', redirect: to => `/app/doctors/${to.params.id}/overview` },
                { path: 'overview', component: DoctorOverview },
                { path: 'schedule', component: DoctorSchedule, meta: { permission: 'doctors.schedule.view' } },
                { path: 'appointments', component: DoctorAppointments },
                { path: 'activity', component: DoctorActivity },
                { path: 'prescriptions', component: () => import('../pages/patients/tabs/Prescriptions.vue'), props: { doctor: true }, meta: { clinicModule: 'prescriptions', permission: 'prescriptions.view', feature: 'prescriptions' } },
            ] },
            { path: 'patients/create', component: PatientFormPage, meta: { clinicModule: 'patients', permission: 'patients.create' } },
            { path: 'patients/:id/edit', component: PatientFormPage, meta: { clinicModule: 'patients', permission: 'patients.update' } },
            { path: 'patients/:id', component: PatientShow, meta: { clinicModule: 'patients' }, children: [
                { path: '', redirect: to => `/app/patients/${to.params.id}/overview` },
                { path: 'overview', component: PatientOverview },
                { path: 'medical-history', component: PatientHistory, meta: { permission: 'patients.medical_history.view' } },
                { path: 'documents', component: PatientDocuments, meta: { permission: 'patients.documents.view' } },
                { path: 'activity', component: PatientActivity },
                { path: 'appointments', component: PatientAppointments, meta: { permission:'appointments.view',feature:'appointments' } },
                { path: 'consultations', component: ConsultationsIndex, props: { patientId: route => Number(route.params.id) }, meta: { permission: 'consultations.view', feature: 'emr' } },
                { path: 'prescriptions', component: () => import('../pages/patients/tabs/Prescriptions.vue'), meta: { clinicModule: 'prescriptions', permission: 'prescriptions.view', feature: 'prescriptions' } },
                { path: 'billing', component: BillingIndex, props: { patientId: route => Number(route.params.id) }, meta: { permission: 'billing.view', feature: 'billing' } },
                ...[['vitals','Vital Signs','vital_signs'],['laboratory','Laboratory',null]].map(([path,title,feature]) => ({ path, component: PatientFuture, meta: { title, feature } })),
            ] },
            { path: 'billing', component: BillingIndex, meta: { clinicModule: 'billing', permission: 'billing.view' } },
            { path: 'staff', component: () => import('../pages/staff/Index.vue'), meta: { clinicModule: 'staff', permission: 'staff.view' } },
            { path: 'reports', component: ReportsIndex, meta: { clinicModule: 'reports' } },
            { path: 'reports/generate/:type', component: ReportsGenerate, meta: { clinicModule: 'reports' } },
            { path: 'reports/:section', component: ReportsCategory, meta: { clinicModule: 'reports' } },
            { path: 'reports/:section/:subsection', component: ReportsCategory, meta: { clinicModule: 'reports' } },
            { path: 'reports/patients', component: PatientsReportsIndex, meta: { clinicModule: 'reports' } },
            { path: 'reports/patients/registrations', component: PatientRegistrationsReport, meta: { clinicModule: 'reports' } },
            { path: 'reports/patients/demographics', component: PatientDemographicsReport, meta: { clinicModule: 'reports' } },
            { path: 'reports/appointments', component: AppointmentsReportsIndex, meta: { clinicModule: 'reports' } },
            { path: 'reports/appointments/statuses', component: AppointmentsStatusesReport, meta: { clinicModule: 'reports' } },
            { path: 'reports/appointments/no-shows', component: AppointmentsStatusesReport, meta: { clinicModule: 'reports' } },
            ...[
                ['consultations', 'Consultations'],
                ['pharmacy', 'Pharmacy'],
                ['billing/invoices/create', 'New Invoice'],
            ].map(([path, title]) => ({ path, component: ModuleAccess, meta: { title, clinicModule: path.split('/')[0], create: path.endsWith('/create') } })),
        ] },
        { path: '/:pathMatch(.*)*', name: 'not-found', component: NotFoundView },
    ],
    scrollBehavior: () => ({ top: 0 }),
});

router.beforeEach(async (to, from) => {
    const auth = useAuthStore();
    if (!auth.loaded) await auth.restore();
    if (to.meta.auth && !auth.user) return { name: 'login' };
    if (to.meta.platform && !auth.user?.is_platform_admin) return { name: 'clinics' };
    if (to.name === 'clinics' && auth.user?.active_tenant_id && to.query.switch !== '1') return { name: 'clinic.dashboard' };
    if (to.meta.clinicLayout) {
        if (!auth.user?.active_tenant_id) {
            if (!to.meta.clinicSelection) return { name: 'clinics' };
            useClinicContextStore().$reset();
            return;
        }
        const clinic = useClinicContextStore();
        if (!(((to.meta.clinicSettings && from.meta.clinicSettings) || (to.meta.patientList && from.meta.patientList) || (to.meta.doctorList && from.meta.doctorList) || (to.meta.appointmentCalendar && from.meta.appointmentCalendar)) && clinic.data?.clinic.id === auth.user.active_tenant_id)) await clinic.load();
        if (!clinic.data?.operational) return;
        if (to.meta.clinicModule) {
            if (!clinic.allowed(to.meta.clinicModule)) return { name: 'clinic.access' };
            if (to.meta.permission && !clinic.can(to.meta.permission)) return { name: 'clinic.access' };
            if (to.meta.feature && !clinic.data.features[to.meta.feature]) return { name: 'clinic.access' };
            if (!['dashboard', 'patients', 'doctors', 'appointments', 'prescriptions', 'settings'].includes(to.meta.clinicModule)) {
                try { await api.get(`/v1/clinic/modules/${to.meta.clinicModule}`, { headers: clinic.headers(), params: to.meta.create ? { action: 'create' } : {} }); }
                catch (error) { if (error.response?.status === 403) return { name: 'clinic.access' }; throw error; }
            }
        }
    }
    if (to.name === 'login' && auth.user) return { name: auth.user.is_platform_admin ? 'admin.dashboard' : auth.user.active_tenant_id ? 'clinic.dashboard' : 'clinics' };
});

export default router;
