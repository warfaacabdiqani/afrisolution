<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import FormErrors from '../../components/ui/FormErrors.vue';
import AppIcon from '../../components/ui/AppIcon.vue';
import { useClinicContextStore } from '../../stores/clinicContext';
import { useStaffStore } from '../../stores/staff';
import { staffService } from '../../services/staff';

const context = useClinicContextStore();
function toggleSalonPermission(permission, checked) {
    const current = staffForm.permissions.length ? staffForm.permissions : (store.options?.role_permissions?.find(item => item.role === staffForm.role)?.permissions || []);
    staffForm.permissions = checked ? [...new Set([...current, permission])] : current.filter(value => value !== permission);
}
const store = useStaffStore();
const route = useRoute();
const router = useRouter();

const defaults = {
    search: '',
    role: '',
    status: '',
    branch_id: String(context.data?.branch?.id || ''),
    page: '1',
    per_page: '25',
};

const filters = reactive({ ...defaults, ...route.query });
const success = ref('');
const error = ref(null);
const saving = ref(false);
const editingId = ref(null);
const currentTab = ref('list');

const staffForm = reactive({
    first_name: '',
    middle_name: '',
    last_name: '',
    email: '',
    phone: '',
    job_title: '',
    role: 'staff',
    status: 'active',
    all_branches: true,
    branch_ids: [],
    permissions: [],
    password: '',
    doctor_id: '',
});

function resetForm() {
    editingId.value = null;
    Object.assign(staffForm, {
        first_name: '',
        middle_name: '',
        last_name: '',
        email: '',
        phone: '',
        job_title: '',
        role: 'staff',
        status: 'active',
        all_branches: true,
        branch_ids: [],
        permissions: [],
        password: '',
        doctor_id: '',
    });
}

function populateForm(member) {
    editingId.value = member.id;
    Object.assign(staffForm, {
        first_name: member.first_name || '',
        middle_name: member.middle_name || '',
        last_name: member.last_name || '',
        email: member.email || '',
        phone: member.phone || '',
        job_title: member.job_title || '',
        role: member.role || 'staff',
        status: member.status || 'active',
        all_branches: Boolean(member.all_branches),
        branch_ids: Array.isArray(member.branch_ids) ? [...member.branch_ids] : [],
        permissions: Array.isArray(member.permissions) ? [...member.permissions] : [],
        password: '',
        doctor_id: '',
    });
    currentTab.value = 'form';
}

function paramsFromFilters() {
    return Object.fromEntries(
        Object.entries(filters).filter(([, value]) => value !== '' && value !== null && value !== undefined),
    );
}

async function load() {
    await store.list(paramsFromFilters());
}

function apply(page = 1) {
    filters.page = String(page);
    router.replace({ query: paramsFromFilters() });
}

function search() {
    filters.page = '1';
    apply();
}

function resetFilters() {
    Object.assign(filters, defaults);
    apply();
}

async function initialize() {
    error.value = null;
    success.value = '';
    await store.loadOptions();
    if (store.options?.branches?.length) {
        staffForm.branch_ids = store.options.branches.map(branch => branch.id);
    }
    await load();
}

async function submit() {
    saving.value = true;
    error.value = null;
    success.value = '';

    try {
        const payload = { ...staffForm, branch_ids: staffForm.all_branches ? [] : staffForm.branch_ids };
        await staffService.save(editingId.value, payload);
        success.value = editingId.value ? 'Staff member updated successfully.' : 'Staff member added successfully.';
        resetForm();
        currentTab.value = 'list';
        await load();
    } catch (e) {
        error.value = e;
    } finally {
        saving.value = false;
    }
}

async function toggleStatus(id, action) {
    error.value = null;
    success.value = '';
    try {
        await staffService.toggleStatus(id, action);
        success.value = action === 'activate' ? 'Staff member activated.' : 'Staff member deactivated.';
        await load();
    } catch (e) {
        error.value = e;
    }
}

async function resetPassword(id) {
    error.value = null;
    success.value = '';
    try {
        await staffService.resetPassword(id);
        success.value = 'Password reset request sent.';
    } catch (e) {
        error.value = e;
    }
}

watch(
    () => route.query,
    (query) => {
        Object.assign(filters, defaults, query);
        load();
    },
    { immediate: true },
);

onMounted(initialize);
</script>

<template>
    <div class="space-y-6">
        <div class="patient-page-header">
            <div>
                <p class="patient-breadcrumb">
                    <RouterLink to="/app/dashboard">Dashboard</RouterLink>
                    <span>/</span>
                    Users / Staff
                </p>
                <h1>Users / Staff</h1>
                <p>Manage your clinic’s user accounts, role access, and branch assignments.</p>
            </div>
            <button class="btn" type="button" @click="resetForm(); currentTab = 'form';">
                + Add Staff Member
            </button>
        </div>

        <div class="clinic-kpis" v-if="store.stats">
            <section class="clinic-panel clinic-kpi">
                <span class="clinic-kpi-icon members"><AppIcon name="members" :size="28" /></span>
                <div>
                    <h2>Total Staff</h2>
                    <strong>{{ store.stats.total }}</strong>
                    <p>Clinic members</p>
                </div>
            </section>
            <section class="clinic-panel clinic-kpi">
                <span class="clinic-kpi-icon blue"><AppIcon name="doctor" :size="28" /></span>
                <div>
                    <h2>Active</h2>
                    <strong>{{ store.stats.active }}</strong>
                    <p>Currently enabled</p>
                </div>
            </section>
            <section class="clinic-panel clinic-kpi">
                <span class="clinic-kpi-icon patient"><AppIcon name="calendar" :size="28" /></span>
                <div>
                    <h2>Inactive</h2>
                    <strong>{{ store.stats.inactive }}</strong>
                    <p>Awaiting access</p>
                </div>
            </section>
            <section class="clinic-panel clinic-kpi">
                <span class="clinic-kpi-icon patient-rose"><AppIcon name="roles" :size="28" /></span>
                <div>
                    <h2>Seats</h2>
                    <strong>{{ store.stats.available_seats }}</strong>
                    <p>Available in plan</p>
                </div>
            </section>
        </div>

        <div v-if="success" class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">
            {{ success }}
        </div>
        <FormErrors :error="error || store.error" />

        <section v-if="currentTab === 'form'" class="clinic-panel p-6">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold">{{ editingId ? 'Edit staff member' : 'Add new staff member' }}</h2>
                    <p class="text-sm text-slate-500">Create or update a clinic staff account and assign branch access.</p>
                </div>
                <button class="btn-secondary" type="button" @click="resetForm()">Cancel</button>
            </div>

            <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submit">
                <label class="field">
                    <span>First name</span>
                    <input v-model="staffForm.first_name" type="text" required />
                </label>
                <label class="field">
                    <span>Middle name</span>
                    <input v-model="staffForm.middle_name" type="text" />
                </label>
                <label class="field">
                    <span>Last name</span>
                    <input v-model="staffForm.last_name" type="text" required />
                </label>
                <label class="field">
                    <span>Job title</span>
                    <input v-model="staffForm.job_title" type="text" />
                </label>
                <label class="field md:col-span-2">
                    <span>Email</span>
                    <input v-model="staffForm.email" type="email" required />
                </label>
                <label class="field">
                    <span>Phone</span>
                    <input v-model="staffForm.phone" type="tel" />
                </label>
                <label class="field">
                    <span>Role</span>
                    <select v-model="staffForm.role">
                        <option v-for="role in store.options?.roles || []" :key="role.value" :value="role.value">
                            {{ role.label }}
                        </option>
                    </select>
                </label>
                <label class="field">
                    <span>Status</span>
                    <select v-model="staffForm.status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </label>
                <label class="field md:col-span-2">
                    <span>Password</span>
                    <input v-model="staffForm.password" type="text" :placeholder="editingId ? 'Leave blank to keep existing password' : 'Optional, autogenerated if blank'" />
                </label>

                <div class="md:col-span-2 space-y-3">
                    <label class="flex items-center gap-2">
                        <input v-model="staffForm.all_branches" type="checkbox" />
                        Allow access to all branches
                    </label>

                    <label v-if="!staffForm.all_branches" class="field">
                        <span>Assigned branches</span>
                        <select v-model="staffForm.branch_ids" multiple size="6">
                            <option v-for="branch in store.options?.branches || []" :key="branch.id" :value="branch.id">
                                {{ branch.name }}
                            </option>
                        </select>
                    </label>
                </div>

                <fieldset v-if="store.options?.billing_permissions?.length" class="md:col-span-2"><legend class="font-semibold mb-3">Billing Permissions</legend><label v-for="permission in store.options.billing_permissions" :key="permission" class="inline-flex items-center gap-2 mr-4 mb-2"><input :checked="staffForm.permissions.includes(permission)" @change="toggleSalonPermission(permission, $event.target.checked)" type="checkbox" :disabled="staffForm.permissions.includes('*')">{{ permission }}</label><p class="text-sm text-slate-500">Custom selections override role defaults. Existing permissions are preserved.</p></fieldset>
                <fieldset v-if="store.options?.whatsapp_permissions?.length" class="md:col-span-2"><legend class="font-semibold mb-3">WhatsApp Permissions</legend><label v-for="permission in store.options.whatsapp_permissions" :key="permission" class="inline-flex items-center gap-2 mr-4 mb-2"><input :checked="staffForm.permissions.includes(permission)" @change="toggleSalonPermission(permission, $event.target.checked)" type="checkbox" :disabled="staffForm.permissions.includes('*')">{{ permission }}</label></fieldset>
                <fieldset v-if="store.options?.salon_permissions?.length" class="md:col-span-2"><legend class="font-semibold mb-3">Salon Permissions</legend><p v-if="staffForm.permissions.includes('*')" class="text-sm mb-3">This account has full access through its existing permissions.</p><label v-for="permission in store.options.salon_permissions" :key="permission" class="inline-flex items-center gap-2 mr-4 mb-2"><input :checked="staffForm.permissions.includes(permission)" @change="toggleSalonPermission(permission, $event.target.checked)" type="checkbox" :value="permission" :disabled="staffForm.permissions.includes('*')">{{ permission.replaceAll('_',' ').replaceAll('.',' ? ') }}</label><p class="text-sm text-slate-500">Custom selections override role defaults. Existing selected permissions are preserved.</p></fieldset>
                <div class="md:col-span-2 flex justify-end gap-3 pt-2">
                    <button class="btn-secondary" type="button" @click="resetForm()">Discard</button>
                    <button class="btn" type="submit" :disabled="saving">
                        {{ saving ? 'Saving...' : editingId ? 'Update Staff Member' : 'Create Staff Member' }}
                    </button>
                </div>

            </form>
        </section>

        <section class="clinic-panel p-4">
            <form class="clinic-panel patient-filters" @submit.prevent="search">
                <input v-model="filters.search" class="filter-input" type="search" placeholder="Search by name, email, or role..." />
                <select v-model="filters.role" class="compact-select" @change="search">
                    <option value="">All roles</option>
                    <option v-for="role in store.options?.roles || []" :key="role.value" :value="role.value">
                        {{ role.label }}
                    </option>
                </select>
                <select v-model="filters.status" class="compact-select" @change="search">
                    <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="suspended">Suspended</option>
                </select>
                <select v-model="filters.branch_id" class="compact-select" @change="search">
                    <option value="">All branches</option>
                    <option v-for="branch in store.options?.branches || []" :key="branch.id" :value="branch.id">
                        {{ branch.name }}
                    </option>
                </select>
                <button class="btn-secondary" type="button" @click="resetFilters">Reset</button>
            </form>
        </section>

        <section class="clinic-panel patient-list-panel">
            <div v-if="store.busy" class="p-6 text-sm text-slate-500">Loading staff members...</div>
            <div v-else-if="!store.rows.length" class="clinic-empty">
                <AppIcon name="members" :size="36" />
                <strong>No staff members yet.</strong>
                <button class="btn" type="button" @click="currentTab = 'form'">Add First Staff Member</button>
            </div>
            <div v-else class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Staff</th>
                            <th>Role</th>
                            <th>Branches</th>
                            <th>Status</th>
                            <th>Last login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="member in store.rows" :key="member.id">
                            <td>
                                <div class="font-semibold">{{ member.name }}</div>
                                <div class="text-sm text-slate-500">{{ member.email }}</div>
                                <div class="text-xs text-slate-500">{{ member.staff_number || 'No staff number' }}</div>
                            </td>
                            <td>{{ member.role }}</td>
                            <td>
                                <div v-if="member.all_branches">All branches</div>
                                <div v-else class="text-sm text-slate-600">{{ member.branches?.join(', ') || 'No branches assigned' }}</div>
                            </td>
                            <td>
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium" :class="member.status === 'active' ? 'bg-emerald-100 text-emerald-700' : member.status === 'inactive' ? 'bg-slate-100 text-slate-700' : 'bg-amber-100 text-amber-700'">
                                    {{ member.status }}
                                </span>
                            </td>
                            <td>{{ member.last_login_at ? new Date(member.last_login_at).toLocaleString() : 'Never' }}</td>
                            <td>
                                <div class="flex flex-wrap gap-2">
                                    <button class="btn-secondary" type="button" @click="populateForm(member)">Edit</button>
                                    <button v-if="member.status !== 'active'" class="btn-secondary" type="button" @click="toggleStatus(member.id, 'activate')">Activate</button>
                                    <button v-else class="btn-secondary" type="button" @click="toggleStatus(member.id, 'deactivate')">Deactivate</button>
                                    <button class="btn-secondary" type="button" @click="resetPassword(member.id)">Reset password</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="store.meta && !store.busy" class="patient-pagination">
                <span>Showing {{ store.meta.from || 0 }} to {{ store.meta.to || 0 }} of {{ store.meta.total }} staff</span>
                <label>
                    Per page
                    <select v-model="filters.per_page" class="compact-select" @change="apply()">
                        <option>25</option>
                        <option>50</option>
                        <option>100</option>
                    </select>
                </label>
                <button class="btn-secondary" :disabled="store.meta.current_page === 1" @click="apply(store.meta.current_page - 1)">Previous</button>
                <span>{{ store.meta.current_page }} / {{ store.meta.last_page }}</span>
                <button class="btn-secondary" :disabled="store.meta.current_page === store.meta.last_page" @click="apply(store.meta.current_page + 1)">Next</button>
            </div>
        </section>
    </div>
</template>
