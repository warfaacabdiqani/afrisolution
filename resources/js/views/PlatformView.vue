<script setup>
import { onMounted, reactive, ref } from 'vue';
import api from '../services/api';
import BaseCard from '../components/ui/BaseCard.vue';
import FormErrors from '../components/ui/FormErrors.vue';

const tab = ref('tenants');
const tenants = ref([]);
const plans = ref([]);
const audits = ref([]);
const page = ref(1);
const lastPage = ref(1);
const auditPage = ref(1);
const auditLastPage = ref(1);
const busy = ref(false);
const error = ref(null);
const success = ref('');
const editing = ref(null);
const subscription = ref(null);
const members = ref([]);
const branches = ref([]);
const branchName = ref('');
const memberForm = reactive({ name:'', email:'', password:'', password_confirmation:'', role:'staff' });
const tenantForm = reactive({ name:'', slug:'', timezone:'Africa/Nairobi', owner_name:'', owner_email:'', owner_password:'', owner_password_confirmation:'', plan_id:'' });
const planForm = reactive({ name:'', branch_limit:1, member_limit:5, trial_days:14 });

async function refresh() {
    const [t,p,a] = await Promise.all([
        api.get('/v1/platform/tenants', { params: { page: page.value } }),
        api.get('/v1/platform/plans'),
        api.get('/v1/platform/audits', { params: { page: auditPage.value } }),
    ]);
    tenants.value=t.data.data; lastPage.value=t.data.meta.last_page;
    plans.value=p.data.data;
    audits.value=a.data.data; auditLastPage.value=a.data.meta.last_page;
}
async function run(action, message='') {
    busy.value=true; error.value=null; success.value='';
    try { await action(); success.value=message; }
    catch(e) { error.value=e; }
    finally { busy.value=false; }
}
async function createTenant() {
    await run(async () => {
        try { await api.post('/v1/platform/tenants', tenantForm); }
        finally { tenantForm.owner_password=''; tenantForm.owner_password_confirmation=''; }
        Object.assign(tenantForm,{name:'',slug:'',owner_name:'',owner_email:''});
        page.value=1; await refresh();
    }, 'Clinic created with an owner, main branch, and trial subscription.');
}
async function createPlan() {
    await run(async () => { await api.post('/v1/platform/plans',planForm); planForm.name=''; await refresh(); }, 'Plan created.');
}
async function edit(tenant) {
    await run(async () => {
        const response=await api.get('/v1/platform/tenants/'+tenant.id+'/subscription');
        editing.value={...tenant};
        subscription.value={...response.data.data, trial_ends_at: response.data.data.trial_ends_at ? new Date(response.data.data.trial_ends_at).toISOString().slice(0,16) : ''};
        await loadOrganization();
    });
}
async function saveTenant() {
    await run(async () => {
        await api.patch('/v1/platform/tenants/'+editing.value.id, { name:editing.value.name, timezone:editing.value.timezone, status:editing.value.status });
        await refresh();
    }, 'Clinic settings saved.');
}
async function loadOrganization() {
    const root='/v1/platform/tenants/'+editing.value.id;
    const [m,b]=await Promise.all([api.get(root+'/members'),api.get(root+'/branches')]);
    members.value=m.data.data; branches.value=b.data.data;
}
async function addBranch() {
    await run(async () => {
        await api.post('/v1/platform/tenants/'+editing.value.id+'/branches',{name:branchName.value});
        branchName.value=''; await loadOrganization(); await refresh();
    },'Branch created.');
}
async function addMember() {
    await run(async () => {
        try { await api.post('/v1/platform/tenants/'+editing.value.id+'/members',memberForm); }
        finally { memberForm.password=''; memberForm.password_confirmation=''; }
        memberForm.name=''; memberForm.email=''; await loadOrganization(); await refresh();
    },'Member account created.');
}
async function saveMember(member) {
    await run(async () => {
        await api.patch('/v1/platform/tenants/'+editing.value.id+'/members/'+member.id,{role:member.role,status:member.status});
        await loadOrganization(); await refresh();
    },'Membership saved.');
}
async function saveSubscription() {
    await run(async () => {
        await api.put('/v1/platform/tenants/'+editing.value.id+'/subscription', {
            plan_id:subscription.value.plan_id, status:subscription.value.status,
            trial_ends_at:subscription.value.status==='trial' ? subscription.value.trial_ends_at+':00Z' : null,
        });
        await refresh();
    }, 'Subscription saved.');
}
async function movePage(delta, audit=false) {
    if(audit) auditPage.value+=delta; else page.value+=delta;
    await run(refresh);
}
onMounted(() => run(refresh));
</script>
<template>
    <div class="space-y-6">
        <div><p class="text-sm font-medium text-teal-700">Platform administration</p><h1 class="mt-1 text-3xl font-semibold">SaaS management</h1></div>
        <nav aria-label="Administration sections" class="flex flex-wrap gap-2">
            <button v-for="section in ['tenants','plans','audit']" :key="section" class="btn" :aria-pressed="tab===section" @click="tab=section">{{ section === 'tenants' ? 'Clinics' : section === 'plans' ? 'Plans' : 'Audit log' }}</button>
        </nav>
        <FormErrors :error="error" />
        <p v-if="success" role="status" class="rounded-lg bg-teal-50 p-4 text-teal-900">{{ success }}</p>
        <p v-if="busy" role="status">Working…</p>

        <template v-if="tab==='tenants'">
            <BaseCard>
                <h2 class="text-xl font-semibold">Clinics</h2>
                <p v-if="!tenants.length && !busy" class="mt-4 text-slate-600">No clinics have been created.</p>
                <ul class="mt-4 divide-y divide-slate-100">
                    <li v-for="tenant in tenants" :key="tenant.id" class="flex flex-wrap items-center justify-between gap-3 py-4">
                        <div><p class="font-medium">{{ tenant.name }}</p><p class="text-sm text-slate-500">{{ tenant.slug }} · {{ tenant.status }} · {{ tenant.timezone }}</p></div>
                        <button class="btn-secondary" :disabled="busy" @click="edit(tenant)">Manage</button>
                    </li>
                </ul>
                <div class="mt-4 flex items-center gap-4">
                    <button class="btn-secondary" :disabled="busy || page<=1" @click="movePage(-1)">Previous</button>
                    <span>Page {{ page }} of {{ lastPage }}</span>
                    <button class="btn-secondary" :disabled="busy || page>=lastPage" @click="movePage(1)">Next</button>
                </div>
            </BaseCard>
            <BaseCard v-if="editing">
                <div class="flex justify-between gap-4"><h2 class="text-xl font-semibold">Manage {{ editing.name }}</h2><button class="btn-secondary" @click="editing=null">Close</button></div>
                <form class="mt-5 grid gap-4 sm:grid-cols-2" @submit.prevent="saveTenant">
                    <label class="field">Clinic name<input v-model="editing.name" required maxlength="150"></label>
                    <label class="field">Timezone<input v-model="editing.timezone" required></label>
                    <label class="field">Clinic status<select v-model="editing.status" aria-label="Clinic status"><option value="active">Active</option><option value="suspended">Suspended</option></select></label>
                    <div class="self-end"><button class="btn" :disabled="busy">Save settings</button></div>
                </form>
                <h3 class="mt-8 text-lg font-semibold">Branches</h3>
                <ul class="mt-3"><li v-for="branch in branches" :key="branch.id" class="py-2">{{ branch.name }}</li></ul>
                <form class="mt-4 flex flex-wrap items-end gap-3" @submit.prevent="addBranch">
                    <label class="field">Branch name<input v-model="branchName" required maxlength="150"></label>
                    <button class="btn" :disabled="busy">Add branch</button>
                </form>
                <h3 class="mt-8 text-lg font-semibold">Members</h3>
                <form v-for="member in members" :key="member.id" class="mt-4 flex flex-wrap items-end gap-3 border-b border-slate-100 pb-4" @submit.prevent="saveMember(member)">
                    <div class="grow"><p class="font-medium">{{ member.name }}</p><p class="text-sm text-slate-500">{{ member.email }}</p></div>
                    <label class="field">Role<select v-model="member.role"><option>owner</option><option>admin</option><option>staff</option></select></label>
                    <label class="field">Status<select v-model="member.status"><option>active</option><option>suspended</option></select></label>
                    <button class="btn-secondary" :disabled="busy">Save member</button>
                </form>
                <h4 class="mt-6 font-semibold">Create member account</h4>
                <form class="mt-4 grid gap-4 sm:grid-cols-2" @submit.prevent="addMember">
                    <label class="field">Name<input v-model="memberForm.name" required maxlength="150"></label>
                    <label class="field">Email<input v-model="memberForm.email" type="email" required maxlength="255"></label>
                    <label class="field">Password<input v-model="memberForm.password" type="password" minlength="12" required autocomplete="new-password"></label>
                    <label class="field">Confirm password<input v-model="memberForm.password_confirmation" type="password" required autocomplete="new-password"></label>
                    <label class="field">Role<select v-model="memberForm.role"><option>owner</option><option>admin</option><option>staff</option></select></label>
                    <div class="self-end"><button class="btn" :disabled="busy">Create member</button></div>
                </form>
                <h3 class="mt-8 text-lg font-semibold">Subscription</h3>
                <p class="mt-2 text-sm text-slate-600">Activation records access entitlement only. No payment is collected here.</p>
                <form class="mt-4 grid gap-4 sm:grid-cols-2" @submit.prevent="saveSubscription">
                    <label class="field">Plan<select v-model="subscription.plan_id" aria-label="Subscription plan" required><option v-for="plan in plans" :key="plan.id" :value="plan.id">{{ plan.name }}</option></select></label>
                    <label class="field">Subscription status<select v-model="subscription.status"><option value="trial">Trial</option><option value="active">Active</option><option value="cancelled">Cancelled</option></select></label>
                    <label v-if="subscription.status==='trial'" class="field">Trial ends (UTC)<input v-model="subscription.trial_ends_at" type="datetime-local" required></label>
                    <div class="self-end"><button class="btn" :disabled="busy">Save subscription</button></div>
                </form>
            </BaseCard>
            <BaseCard>
                <h2 class="text-xl font-semibold">Create clinic</h2>
                <p class="mt-2 text-sm text-slate-600">Creates a new owner account and starts the selected plan's trial. Existing user emails cannot be reassigned here.</p>
                <p v-if="!plans.length" class="mt-3 text-amber-800">Create a plan first in the Plans section.</p>
                <form class="mt-5 grid gap-4 sm:grid-cols-2" @submit.prevent="createTenant">
                    <label class="field">Clinic name<input v-model="tenantForm.name" required maxlength="150"></label>
                    <label class="field">Unique clinic code<input v-model="tenantForm.slug" required pattern="[A-Za-z0-9_-]+" maxlength="80"></label>
                    <label class="field">Timezone<input v-model="tenantForm.timezone" required></label>
                    <label class="field">Plan<select v-model="tenantForm.plan_id" aria-label="Plan" required><option disabled value="">Select a plan</option><option v-for="plan in plans" :key="plan.id" :value="plan.id">{{ plan.name }} · {{ plan.trial_days }} trial days</option></select></label>
                    <label class="field">Owner name<input v-model="tenantForm.owner_name" required maxlength="150"></label>
                    <label class="field">Owner email<input v-model="tenantForm.owner_email" type="email" required maxlength="255" autocomplete="off"></label>
                    <label class="field">Owner password<input v-model="tenantForm.owner_password" aria-label="Owner password" type="password" required minlength="12" autocomplete="new-password"><span class="text-xs text-slate-500">12+ characters, mixed case and a number.</span></label>
                    <label class="field">Confirm password<input v-model="tenantForm.owner_password_confirmation" type="password" required autocomplete="new-password"></label>
                    <div><button class="btn" :disabled="busy || !plans.length">Create clinic</button></div>
                </form>
            </BaseCard>
        </template>
        <template v-if="tab==='plans'">
            <BaseCard>
                <h2 class="text-xl font-semibold">Plans</h2>
                <p class="mt-2 text-sm text-slate-600">Plans are immutable. Create a new plan to change limits, then explicitly assign it to a clinic.</p>
                <p v-if="!plans.length && !busy" class="mt-4">No plans yet.</p>
                <ul class="mt-4 divide-y divide-slate-100"><li v-for="plan in plans" :key="plan.id" class="py-3"><strong>{{ plan.name }}</strong><p class="text-sm text-slate-600">{{ plan.branch_limit }} branches · {{ plan.member_limit }} members · {{ plan.trial_days }} trial days</p></li></ul>
            </BaseCard>
            <BaseCard>
                <h2 class="text-xl font-semibold">Create plan</h2>
                <form class="mt-5 grid gap-4 sm:grid-cols-2" @submit.prevent="createPlan">
                    <label class="field">Name<input v-model="planForm.name" required maxlength="100"></label>
                    <label class="field">Branch limit<input v-model.number="planForm.branch_limit" type="number" min="1" max="10000" required></label>
                    <label class="field">Member limit<input v-model.number="planForm.member_limit" type="number" min="1" max="100000" required></label>
                    <label class="field">Trial days<input v-model.number="planForm.trial_days" type="number" min="1" max="365" required></label>
                    <div><button class="btn" :disabled="busy">Create plan</button></div>
                </form>
            </BaseCard>
        </template>
        <BaseCard v-if="tab==='audit'">
            <h2 class="text-xl font-semibold">Platform audit log</h2>
            <p v-if="!audits.length && !busy" class="mt-4">No events recorded.</p>
            <ul class="mt-4 divide-y divide-slate-100"><li v-for="event in audits" :key="event.id" class="py-3"><p class="font-medium">{{ event.action }}</p><p class="text-sm text-slate-600">{{ event.subject_type }} #{{ event.subject_id }} · Actor #{{ event.actor_id }} · {{ event.created_at }} UTC</p></li></ul>
            <div class="mt-4 flex items-center gap-4">
                <button class="btn-secondary" :disabled="busy || auditPage<=1" @click="movePage(-1,true)">Previous</button>
                <span>Page {{ auditPage }} of {{ auditLastPage }}</span>
                <button class="btn-secondary" :disabled="busy || auditPage>=auditLastPage" @click="movePage(1,true)">Next</button>
            </div>
        </BaseCard>
    </div>
</template>
