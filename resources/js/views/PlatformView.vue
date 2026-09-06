<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import api from '../services/api';
import { useAuthStore } from '../stores/auth';
import { useRouter } from 'vue-router';
import FormErrors from '../components/ui/FormErrors.vue';

const auth = useAuthStore();
const router = useRouter();
const section = ref('dashboard');
const mobileMenu = ref(false);
const dashboard = ref({ stats: {}, expiring_trials: [], recent_activity: [] });
const tenants = ref([]);
const plans = ref([]);
const audits = ref([]);
const busy = ref(false);
const error = ref(null);
const success = ref('');
const editing = ref(null);
const subscription = ref(null);
const members = ref([]);
const branches = ref([]);
const branchName = ref('');
const tenantForm = reactive({ name:'', slug:'', timezone:'Africa/Nairobi', owner_name:'', owner_email:'', owner_password:'', owner_password_confirmation:'', plan_id:'' });
const planForm = reactive({ name:'', branch_limit:1, member_limit:5, trial_days:14 });
const memberForm = reactive({ name:'', email:'', password:'', password_confirmation:'', role:'staff' });

const sections = [
    ['dashboard','Dashboard','grid'], ['tenants','Clinics / Tenants','building'],
    ['plans','Subscription Plans','card'], ['subscriptions','Subscriptions','refresh'],
    ['users','Users','users'], ['audit','Audit Log','clipboard'], ['settings','System Settings','settings'],
];
const statCards = computed(() => [
    ['Total Clinics', dashboard.value.stats.total_clinics ?? 0, 'building', 'mint'],
    ['Active Clinics', dashboard.value.stats.active_clinics ?? 0, 'users', 'blue'],
    ['Trial Clinics', dashboard.value.stats.trial_clinics ?? 0, 'clock', 'amber'],
    ['Suspended Clinics', dashboard.value.stats.suspended_clinics ?? 0, 'pause', 'rose'],
    ['Active Members', dashboard.value.stats.total_members ?? 0, 'users', 'violet'],
]);
const title = computed(() => sections.find(item => item[0] === section.value)?.[1] || 'Dashboard');

async function refresh() {
    const [d,t,p,a] = await Promise.all([
        api.get('/v1/platform/dashboard'),
        api.get('/v1/platform/tenants'),
        api.get('/v1/platform/plans'),
        api.get('/v1/platform/audits'),
    ]);
    dashboard.value=d.data.data; tenants.value=t.data.data; plans.value=p.data.data; audits.value=a.data.data;
}
async function run(action, message='') {
    busy.value=true; error.value=null; success.value='';
    try { await action(); success.value=message; } catch(e) { error.value=e; } finally { busy.value=false; }
}
function navigate(name) { section.value=name; mobileMenu.value=false; error.value=null; success.value=''; editing.value=null; }
async function logout() { await auth.logout(); await router.replace('/app/login'); }
async function createPlan() {
    await run(async()=>{ await api.post('/v1/platform/plans',planForm); planForm.name=''; await refresh(); },'Plan created.');
}
async function createTenant() {
    await run(async()=>{
        try { await api.post('/v1/platform/tenants',tenantForm); }
        finally { tenantForm.owner_password=''; tenantForm.owner_password_confirmation=''; }
        Object.assign(tenantForm,{name:'',slug:'',owner_name:'',owner_email:''}); await refresh();
    },'Clinic created with an owner, main branch, and trial.');
}
async function manage(tenant) {
    await run(async()=>{
        editing.value={...tenant};
        const root='/v1/platform/tenants/'+tenant.id;
        const [s,m,b]=await Promise.all([api.get(root+'/subscription'),api.get(root+'/members'),api.get(root+'/branches')]);
        subscription.value={...s.data.data,trial_ends_at:s.data.data.trial_ends_at ? new Date(s.data.data.trial_ends_at).toISOString().slice(0,16):''};
        members.value=m.data.data; branches.value=b.data.data;
    });
}
async function reloadOrganization() {
    const root='/v1/platform/tenants/'+editing.value.id;
    const [m,b]=await Promise.all([api.get(root+'/members'),api.get(root+'/branches')]);
    members.value=m.data.data; branches.value=b.data.data;
}
async function saveTenant() {
    await run(async()=>{ await api.patch('/v1/platform/tenants/'+editing.value.id,{name:editing.value.name,timezone:editing.value.timezone,status:editing.value.status}); await refresh(); },'Clinic saved.');
}
async function saveSubscription() {
    await run(async()=>{ await api.put('/v1/platform/tenants/'+editing.value.id+'/subscription',{plan_id:subscription.value.plan_id,status:subscription.value.status,trial_ends_at:subscription.value.status==='trial' ? subscription.value.trial_ends_at+':00Z':null}); await refresh(); },'Subscription saved.');
}
async function addBranch() {
    await run(async()=>{ await api.post('/v1/platform/tenants/'+editing.value.id+'/branches',{name:branchName.value}); branchName.value=''; await reloadOrganization(); await refresh(); },'Branch created.');
}
async function addMember() {
    await run(async()=>{ try { await api.post('/v1/platform/tenants/'+editing.value.id+'/members',memberForm); } finally { memberForm.password='';memberForm.password_confirmation=''; } memberForm.name='';memberForm.email='';await reloadOrganization();await refresh(); },'Member created.');
}
async function saveMember(member) {
    await run(async()=>{await api.patch('/v1/platform/tenants/'+editing.value.id+'/members/'+member.id,{role:member.role,status:member.status});await reloadOrganization();await refresh();},'Membership saved.');
}
function date(value) { return new Intl.DateTimeFormat(undefined,{dateStyle:'medium'}).format(new Date(value)); }
function dateTime(value) { return new Intl.DateTimeFormat(undefined,{dateStyle:'medium',timeStyle:'short'}).format(new Date(value)); }
function daysLeft(value) { return Math.max(0,Math.ceil((new Date(value)-new Date())/86400000)); }
onMounted(()=>run(refresh));
</script>

<template>
<div class="admin-shell">
    <div v-if="mobileMenu" class="fixed inset-0 z-30 bg-slate-950/40 lg:hidden" @click="mobileMenu=false"></div>
    <aside :class="['admin-sidebar', mobileMenu ? 'translate-x-0' : '-translate-x-full lg:translate-x-0']">
        <div class="flex h-20 items-center gap-3 border-b border-white/10 px-6">
            <div class="grid size-11 place-items-center rounded-2xl bg-white text-2xl text-teal-800">♥</div>
            <div><p class="text-xl font-bold">Afri Clinic</p><p class="text-xs text-teal-100">Healthcare SaaS</p></div>
        </div>
        <nav class="flex-1 space-y-1 px-3 py-6" aria-label="Platform administration">
            <button v-for="[key,label,icon] in sections" :key="key" :class="['admin-nav',section===key&&'admin-nav-active']" @click="navigate(key)">
                <span class="admin-nav-icon">{{ {grid:'▦',building:'▥',card:'▤',refresh:'↻',users:'♙',clipboard:'▧',settings:'⚙'}[icon] }}</span>{{ label }}
            </button>
        </nav>
        <div class="border-t border-white/10 p-4">
            <div class="flex items-center gap-3 rounded-xl bg-white/10 p-3">
                <div class="grid size-10 shrink-0 place-items-center rounded-full bg-white font-bold text-teal-800">{{ auth.user?.name?.charAt(0) }}</div>
                <div class="min-w-0"><p class="truncate text-sm font-semibold">{{ auth.user?.name }}</p><p class="text-xs text-teal-100">Platform Administrator</p></div>
            </div>
            <button class="mt-3 w-full rounded-xl px-4 py-2 text-left text-sm text-teal-50 hover:bg-white/10" @click="logout">⇥ Sign out</button>
        </div>
    </aside>

    <div class="min-w-0 lg:pl-64">
        <header class="admin-topbar">
            <button class="grid size-10 place-items-center rounded-lg border border-slate-200 lg:hidden" aria-label="Open menu" @click="mobileMenu=true">☰</button>
            <div class="relative hidden max-w-xl flex-1 sm:block">
                <span class="absolute left-4 top-2.5 text-slate-400">⌕</span>
                <input class="w-full rounded-xl border-0 bg-slate-100 py-2.5 pl-10 pr-4 text-sm outline-none ring-teal-200 focus:ring-2" placeholder="Search is added with the first searchable module" disabled>
            </div>
            <div class="ml-auto flex items-center gap-3">
                <div class="grid size-10 place-items-center rounded-full bg-teal-800 font-semibold text-white">{{ auth.user?.name?.charAt(0) }}</div>
                <div class="hidden sm:block"><p class="text-sm font-semibold">{{ auth.user?.name }}</p><p class="text-xs text-slate-500">Platform Admin</p></div>
            </div>
        </header>

        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-[1500px]">
                <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
                    <div><p class="text-sm font-semibold text-teal-700">Platform administration</p><h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-950">{{ section==='dashboard' ? 'Welcome back, '+auth.user?.name+'!' : title }}</h1><p class="mt-1 text-sm text-slate-500">{{ section==='dashboard' ? 'Here is what is happening across your platform.' : 'Manage '+title.toLowerCase()+' from one secure workspace.' }}</p></div>
                    <p class="text-sm font-medium text-slate-600">{{ date(new Date()) }}</p>
                </div>
                <FormErrors :error="error"/>
                <p v-if="success" role="status" class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ success }}</p>
                <p v-if="busy" role="status" class="mb-5 text-sm text-slate-500">Loading…</p>

                <template v-if="section==='dashboard'">
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                        <article v-for="[label,value,icon,tone] in statCards" :key="label" :class="['stat-card','stat-'+tone]">
                            <div class="stat-icon">{{ {building:'▥',users:'♙',clock:'◷',pause:'Ⅱ'}[icon] }}</div>
                            <div><p class="text-sm text-slate-600">{{ label }}</p><p class="mt-1 text-3xl font-bold text-slate-950">{{ value }}</p></div>
                        </article>
                    </div>
                    <div v-if="dashboard.expiring_trials.length" class="mt-5 flex flex-wrap items-center gap-4 rounded-2xl border border-blue-200 bg-blue-50 p-5">
                        <span class="text-2xl">◷</span><div class="grow"><p class="font-semibold">{{ dashboard.expiring_trials.length }} trial clinic(s) expire within 14 days</p><p class="text-sm text-slate-600">Review subscription status before access ends.</p></div>
                        <button class="btn" @click="navigate('subscriptions')">View trials →</button>
                    </div>
                    <div class="mt-6 grid gap-6 xl:grid-cols-5">
                        <section class="admin-card xl:col-span-2"><div class="card-heading"><h2>Recent activity</h2><button @click="navigate('audit')">View all</button></div>
                            <div v-if="dashboard.recent_activity.length" class="divide-y divide-slate-100">
                                <div v-for="item in dashboard.recent_activity" :key="item.id" class="flex gap-3 py-4"><span class="activity-icon">↻</span><div class="min-w-0 grow"><p class="font-semibold text-slate-800">{{ item.action }}</p><p class="text-sm text-slate-500">{{ item.subject_type }} #{{ item.subject_id }} · {{ item.actor_name || 'System' }}</p></div><time class="text-xs text-slate-400">{{ dateTime(item.created_at) }}</time></div>
                            </div><p v-else class="empty-state">No platform activity yet.</p>
                        </section>
                        <section class="admin-card xl:col-span-3"><div class="card-heading"><h2>Trials expiring soon</h2><button @click="navigate('subscriptions')">View all</button></div>
                            <div class="table-wrap"><table><thead><tr><th>Clinic</th><th>Plan</th><th>Trial ends</th><th>Days left</th></tr></thead><tbody>
                                <tr v-for="trial in dashboard.expiring_trials" :key="trial.id"><td class="font-semibold">{{ trial.name }}</td><td>{{ trial.plan_name }}</td><td>{{ date(trial.trial_ends_at) }}</td><td><span class="badge badge-amber">{{ daysLeft(trial.trial_ends_at) }} days</span></td></tr>
                            </tbody></table><p v-if="!dashboard.expiring_trials.length" class="empty-state">No trials expire within the next 14 days.</p></div>
                        </section>
                    </div>
                </template>

                <template v-else-if="section==='tenants'">
                    <div class="grid gap-6 xl:grid-cols-5">
                        <section class="admin-card xl:col-span-3"><div class="card-heading"><h2>Clinics</h2><span>{{ tenants.length }} shown</span></div>
                            <div class="table-wrap"><table><thead><tr><th>Clinic</th><th>Status</th><th>Timezone</th><th></th></tr></thead><tbody><tr v-for="tenant in tenants" :key="tenant.id"><td><p class="font-semibold">{{ tenant.name }}</p><p class="text-xs text-slate-500">{{ tenant.slug }}</p></td><td><span :class="['badge',tenant.status==='active'?'badge-green':'badge-rose']">{{ tenant.status }}</span></td><td>{{ tenant.timezone }}</td><td><button class="btn-secondary" @click="manage(tenant)">Manage</button></td></tr></tbody></table><p v-if="!tenants.length&&!busy" class="empty-state">No clinics yet.</p></div>
                        </section>
                        <section class="admin-card xl:col-span-2"><h2 class="text-lg font-bold">Create clinic</h2><p class="mt-1 text-sm text-slate-500">Creates the owner, main branch, and trial together.</p>
                            <form class="mt-5 grid gap-4" @submit.prevent="createTenant">
                                <label class="field">Clinic name<input v-model="tenantForm.name" required></label><label class="field">Unique clinic code<input v-model="tenantForm.slug" required pattern="[A-Za-z0-9_-]+"></label>
                                <label class="field">Timezone<input v-model="tenantForm.timezone" required></label><label class="field">Plan<select v-model="tenantForm.plan_id" required><option disabled value="">Select a plan</option><option v-for="plan in plans" :key="plan.id" :value="plan.id">{{ plan.name }} · {{ plan.trial_days }} days</option></select></label>
                                <label class="field">Owner name<input v-model="tenantForm.owner_name" required></label><label class="field">Owner email<input v-model="tenantForm.owner_email" type="email" required></label>
                                <label class="field">Owner password<input v-model="tenantForm.owner_password" type="password" minlength="12" required autocomplete="new-password"></label><label class="field">Confirm password<input v-model="tenantForm.owner_password_confirmation" type="password" required autocomplete="new-password"></label>
                                <button class="btn" :disabled="busy||!plans.length">Create clinic</button>
                            </form>
                        </section>
                    </div>
                    <section v-if="editing" class="admin-card mt-6"><div class="card-heading"><h2>Manage {{ editing.name }}</h2><button class="btn-secondary" @click="editing=null">Close</button></div>
                        <div class="grid gap-8 xl:grid-cols-2">
                            <div><form class="grid gap-4 sm:grid-cols-2" @submit.prevent="saveTenant"><label class="field">Clinic name<input v-model="editing.name"></label><label class="field">Timezone<input v-model="editing.timezone"></label><label class="field">Status<select v-model="editing.status"><option>active</option><option>suspended</option></select></label><button class="btn self-end">Save clinic</button></form>
                                <h3 class="section-title">Branches</h3><ul class="divide-y"><li v-for="branch in branches" :key="branch.id" class="py-3">{{ branch.name }}</li></ul><form class="mt-3 flex items-end gap-3" @submit.prevent="addBranch"><label class="field grow">Branch name<input v-model="branchName" required></label><button class="btn">Add branch</button></form>
                            </div>
                            <div><h3 class="font-bold">Subscription</h3><form class="mt-4 grid gap-4 sm:grid-cols-2" @submit.prevent="saveSubscription"><label class="field">Plan<select v-model="subscription.plan_id"><option v-for="plan in plans" :key="plan.id" :value="plan.id">{{ plan.name }}</option></select></label><label class="field">Status<select v-model="subscription.status"><option>trial</option><option>active</option><option>cancelled</option></select></label><label v-if="subscription.status==='trial'" class="field">Trial ends<input v-model="subscription.trial_ends_at" type="datetime-local"></label><button class="btn self-end">Save subscription</button></form>
                                <h3 class="section-title">Members</h3><form v-for="member in members" :key="member.id" class="grid gap-3 border-b py-3 sm:grid-cols-4" @submit.prevent="saveMember(member)"><div class="sm:col-span-2"><p class="font-semibold">{{ member.name }}</p><p class="text-xs text-slate-500">{{ member.email }}</p></div><select v-model="member.role" class="compact-select"><option>owner</option><option>admin</option><option>staff</option></select><button class="btn-secondary">Save</button></form>
                                <form class="mt-5 grid gap-3 sm:grid-cols-2" @submit.prevent="addMember"><label class="field">Name<input v-model="memberForm.name" required></label><label class="field">Email<input v-model="memberForm.email" type="email" required></label><label class="field">Password<input v-model="memberForm.password" type="password" minlength="12" required></label><label class="field">Confirm password<input v-model="memberForm.password_confirmation" type="password" required></label><label class="field">Role<select v-model="memberForm.role"><option>owner</option><option>admin</option><option>staff</option></select></label><button class="btn self-end">Add member</button></form>
                            </div>
                        </div>
                    </section>
                </template>

                <template v-else-if="section==='plans'">
                    <div class="grid gap-6 lg:grid-cols-3"><article v-for="plan in plans" :key="plan.id" class="admin-card"><div class="grid size-12 place-items-center rounded-xl bg-teal-50 text-xl text-teal-700">▤</div><h2 class="mt-5 text-xl font-bold">{{ plan.name }}</h2><dl class="mt-5 space-y-3 text-sm"><div class="flex justify-between"><dt>Branches</dt><dd class="font-semibold">{{ plan.branch_limit }}</dd></div><div class="flex justify-between"><dt>Members</dt><dd class="font-semibold">{{ plan.member_limit }}</dd></div><div class="flex justify-between"><dt>Trial</dt><dd class="font-semibold">{{ plan.trial_days }} days</dd></div></dl></article></div>
                    <section class="admin-card mt-6 max-w-3xl"><h2 class="text-lg font-bold">Create plan</h2><p class="mt-1 text-sm text-slate-500">Plan definitions remain immutable after creation.</p><form class="mt-5 grid gap-4 sm:grid-cols-2" @submit.prevent="createPlan"><label class="field">Name<input v-model="planForm.name" required></label><label class="field">Branch limit<input v-model.number="planForm.branch_limit" type="number" min="1"></label><label class="field">Member limit<input v-model.number="planForm.member_limit" type="number" min="1"></label><label class="field">Trial days<input v-model.number="planForm.trial_days" type="number" min="1"></label><button class="btn">Create plan</button></form></section>
                </template>

                <section v-else-if="section==='subscriptions'" class="admin-card"><div class="card-heading"><h2>Subscription overview</h2><span>{{ dashboard.stats.active_subscriptions || 0 }} active</span></div><p class="text-sm text-slate-500">Open Clinics / Tenants and choose Manage to change a clinic subscription.</p><div class="mt-5 table-wrap"><table><thead><tr><th>Clinic</th><th>Status</th><th>Timezone</th><th></th></tr></thead><tbody><tr v-for="tenant in tenants" :key="tenant.id"><td class="font-semibold">{{ tenant.name }}</td><td><span :class="['badge',tenant.status==='active'?'badge-green':'badge-rose']">{{ tenant.status }}</span></td><td>{{ tenant.timezone }}</td><td><button class="btn-secondary" @click="navigate('tenants');manage(tenant)">Manage</button></td></tr></tbody></table></div></section>

                <section v-else-if="section==='users'" class="admin-card"><h2 class="text-lg font-bold">Tenant users</h2><p class="mt-2 text-sm text-slate-500">User identities are managed inside each clinic to preserve tenant context. Choose a clinic, then Manage.</p><button class="btn mt-5" @click="navigate('tenants')">Open clinics</button></section>

                <section v-else-if="section==='audit'" class="admin-card"><div class="card-heading"><h2>Platform audit log</h2><span>{{ audits.length }} recent events</span></div><div class="divide-y divide-slate-100"><div v-for="item in audits" :key="item.id" class="flex flex-wrap gap-3 py-4"><span class="activity-icon">↻</span><div class="grow"><p class="font-semibold">{{ item.action }}</p><p class="text-sm text-slate-500">{{ item.subject_type }} #{{ item.subject_id }} · Actor #{{ item.actor_id }}</p></div><time class="text-xs text-slate-400">{{ dateTime(item.created_at) }}</time></div></div><p v-if="!audits.length&&!busy" class="empty-state">No activity recorded.</p></section>

                <section v-else class="admin-card"><h2 class="text-lg font-bold">System settings</h2><p class="mt-2 text-sm text-slate-500">No editable platform settings have been implemented yet. Configuration remains server-managed.</p></section>
            </div>
        </main>
    </div>
</div>
</template>
