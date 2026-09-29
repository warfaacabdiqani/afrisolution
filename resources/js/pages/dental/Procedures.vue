<script setup>
import { computed, onUnmounted, reactive, ref, watch } from 'vue';
import { dentalService } from '../../services/dental';
import { useClinicContextStore } from '../../stores/clinicContext';
import FormErrors from '../../components/ui/FormErrors.vue';
const context = useClinicContextStore();
const scope = computed(() => `${context.data?.clinic.id || ''}:${context.data?.branch?.id || ''}`);
const rows = ref(null), currency = ref(''), error = ref(null), busy = ref(false), saving = ref(false), editing = ref(false), search = ref(''), status = ref('');
const form = reactive({ id: null, code: '', name: '', description: '', price: '', active: true });
let generation = 0;
function edit(row = {}) { Object.assign(form, { id: null, code: '', name: '', description: '', price: '', active: true }, row); editing.value = true; error.value = null; }
async function load(page = 1) {
    const token = ++generation; busy.value = true; error.value = null;
    try {
        const [list, options] = await Promise.all([dentalService.procedures({ search: search.value || undefined, active: status.value === '' ? undefined : status.value, page }), dentalService.options()]);
        if (token !== generation) return;
        rows.value = list.data.data; currency.value = options.data.data.currency;
    } catch (e) { if (token === generation) error.value = e; }
    finally { if (token === generation) busy.value = false; }
}
async function save() {
    const key = scope.value; saving.value = true; error.value = null;
    try {
        await dentalService.saveProcedure(form.id, { code: form.code, name: form.name, description: form.description, price: form.price, active: form.active });
        if (key !== scope.value) return;
        editing.value = false; await load();
    } catch (e) { if (key === scope.value) error.value = e; }
    finally { if (key === scope.value) saving.value = false; }
}
watch(scope, () => { ++generation; rows.value = null; editing.value = false; saving.value = false; search.value = ''; status.value = ''; if (context.allowed('dental')) load(); }, { immediate: true });
onUnmounted(() => { ++generation; });
</script>

<template>
    <div>
        <header class="patient-page-header"><div><p class="patient-breadcrumb">Dental / Procedures</p><h1>Dental procedures</h1><p>Manage procedure codes and default prices for treatment plans.</p></div><button v-if="context.can('dental.procedures.manage')" class="btn" @click="edit()">Add Procedure</button></header>
        <FormErrors :error="error" />
        <form v-if="editing" class="clinic-panel dental-form" @submit.prevent="save">
            <h2>{{ form.id ? 'Edit procedure' : 'New procedure' }}</h2>
            <div class="form-grid"><label class="field">Procedure code<input v-model.trim="form.code" required maxlength="40" pattern="[A-Za-z0-9_-]+"></label><label class="field">Procedure name<input v-model.trim="form.name" required maxlength="150"></label><label class="field">Default price<input v-model="form.price" required type="number" min="0" max="9999999.99" step="0.01"><small>{{ currency }} · Tax is applied from the treatment plan.</small></label><label class="field">Description<textarea v-model="form.description" maxlength="4000"></textarea></label></div>
            <label class="toggle-row"><input v-model="form.active" type="checkbox">Active — available for new plans</label>
            <p class="hint">Changing the catalog does not change existing treatment plans. Archive a procedure by turning off Active.</p>
            <div class="dental-actions"><button class="btn" :disabled="saving">Save Procedure</button><button type="button" class="btn-secondary" :disabled="saving" @click="editing = false">Cancel</button></div>
        </form>
        <form class="clinic-panel dental-filters" @submit.prevent="load()"><label class="field">Search procedures<input v-model="search" type="search" placeholder="Code or name"></label><label class="field">Status<select v-model="status"><option value="">All procedures</option><option value="1">Active</option><option value="0">Archived</option></select></label><button class="btn-secondary">Search</button></form>
        <section class="clinic-panel">
            <p v-if="busy" class="p-6" role="status">Loading procedures…</p>
            <template v-else-if="rows"><div class="dental-table"><table><thead><tr><th>Code</th><th>Procedure</th><th>Default price</th><th>Status</th><th>Actions</th></tr></thead><tbody><tr v-for="row in rows.data" :key="row.id"><td>{{ row.code }}</td><td>{{ row.name }}<small v-if="row.description">{{ row.description }}</small></td><td>{{ currency }} {{ Number(row.price).toFixed(2) }}</td><td>{{ row.active ? 'Active' : 'Archived' }}</td><td><button v-if="context.can('dental.procedures.manage')" class="btn-secondary" @click="edit(row)">Edit</button></td></tr><tr v-if="!rows.data.length"><td colspan="5">No procedures found. Add a procedure to start building treatment plans.</td></tr></tbody></table></div><div class="patient-pagination"><button class="btn-secondary" :disabled="rows.current_page <= 1" @click="load(rows.current_page - 1)">Previous</button><span>{{ rows.current_page }} / {{ rows.last_page }}</span><button class="btn-secondary" :disabled="rows.current_page >= rows.last_page" @click="load(rows.current_page + 1)">Next</button></div></template>
        </section>
    </div>
</template>

<style scoped>
.dental-form,.dental-filters{padding:20px;margin-bottom:20px}.dental-form h2{font-weight:700;margin-bottom:15px}.dental-actions{display:flex;gap:10px;margin-top:18px}.dental-filters{display:flex;gap:12px;align-items:end;flex-wrap:wrap}.dental-filters .field{flex:1;min-width:160px}.dental-table{overflow-x:auto}table{width:100%;text-align:left;font-size:14px}th,td{padding:16px;border-bottom:1px solid #e2e8f0}td small{display:block;color:#64748b;max-width:350px;white-space:pre-wrap}
</style>
