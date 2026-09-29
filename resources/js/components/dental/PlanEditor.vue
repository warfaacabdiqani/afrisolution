<script setup>
import { computed, onUnmounted, reactive, ref } from 'vue';
import { dentalService } from '../../services/dental';
import FormErrors from '../ui/FormErrors.vue';
const props = defineProps({ plan: { type: Object, default: null }, options: { type: Object, required: true }, selectedTooth: { type: String, default: '' }, saving: Boolean });
const emit = defineEmits(['save', 'cancel']);
const form = reactive({ title: props.plan?.title || '', notes: props.plan?.notes || '', items: (props.plan?.items || []).map(i => ({ procedure_id: i.procedure_id, procedure_name: i.procedure_name, tooth: i.tooth || '', surfaces: i.surfaces || [], visit_number: i.visit_number, quantity: i.quantity, unit_price: i.unit_price, notes: i.notes || '' })) });
const line = reactive({ procedure_id: '', tooth: props.selectedTooth, surfaces: [], visit_number: 1, quantity: 1, unit_price: '', notes: '' });
const search = ref(''), procedures = ref([]), error = ref(null), loading = ref(false), page = ref(1), lastPage = ref(1);
let generation = 0;
async function find(next = 1) {
    const token = ++generation; loading.value = true; error.value = null;
    try { const { data } = await dentalService.procedures({ active: 1, search: search.value, page: next }); if (token !== generation) return; procedures.value = data.data.data; page.value = data.data.current_page; lastPage.value = data.data.last_page; }
    catch (e) { if (token === generation) error.value = e; } finally { if (token === generation) loading.value = false; }
}
function selectProcedure() { const p = procedures.value.find(p => p.id === Number(line.procedure_id)); if (p) line.unit_price = p.price; }
function add() {
    const p = procedures.value.find(p => p.id === Number(line.procedure_id));
    if (!p || !Number.isInteger(Number(line.visit_number)) || Number(line.visit_number) < 1 || Number(line.visit_number) > 100 || !Number.isInteger(Number(line.quantity)) || Number(line.quantity) < 1 || Number(line.quantity) > 100 || !/^\d+(\.\d{1,2})?$/.test(String(line.unit_price))) return;
    form.items.push({ ...line, procedure_id: p.id, procedure_name: p.name, surfaces: [...line.surfaces], visit_number: Number(line.visit_number), quantity: Number(line.quantity) });
}
const estimate = computed(() => form.items.reduce((sum, i) => sum + Number(i.unit_price) * i.quantity, 0));
function save() {
    emit('save', { title: form.title, notes: form.notes, ...(props.plan ? { version: props.plan.version } : {}), items: form.items.map(({ procedure_name, ...item }) => ({ ...item, tooth: item.tooth || null })) });
}
find(); onUnmounted(() => { ++generation; });
</script>

<template>
    <section class="clinic-panel plan-editor">
        <h3>{{ plan ? 'Edit draft plan' : 'New treatment plan' }}</h3>
        <FormErrors :error="error" />
        <form @submit.prevent="save">
            <div class="form-grid"><label class="field">Plan title<input v-model.trim="form.title" required maxlength="150"></label><label class="field">Plan notes<textarea v-model="form.notes" maxlength="4000"></textarea></label></div>
            <fieldset class="line-editor"><legend>Add a treatment</legend>
                <div class="procedure-search"><label class="field">Find procedure<input v-model="search" type="search" placeholder="Search code or name" @keydown.enter.prevent="find()"></label><button type="button" class="btn-secondary" :disabled="loading" @click="find()">Find</button></div>
                <div class="form-grid"><label class="field">Procedure<select v-model="line.procedure_id" @change="selectProcedure"><option value="">Select a procedure</option><option v-for="p in procedures" :key="p.id" :value="p.id">{{ p.code }} — {{ p.name }}</option></select></label><label class="field">Treatment tooth<select v-model="line.tooth"><option value="">Whole mouth / no specific tooth</option><option v-for="tooth in options.teeth" :key="tooth" :value="tooth">{{ tooth }}</option></select></label><label class="field">Visit number<input v-model="line.visit_number" type="number" min="1" max="100"></label><label class="field">Quantity<input v-model="line.quantity" type="number" min="1" max="100"></label><label class="field">Agreed unit price<input v-model="line.unit_price" type="number" min="0" max="999999.99" step="0.01"><small>{{ plan?.currency || options.currency }}</small></label><label class="field">Treatment notes<input v-model="line.notes" maxlength="2000"></label></div>
                <fieldset v-if="line.tooth" class="surface-picker"><legend>Treatment surfaces (optional)</legend><label v-for="(label, key) in options.surfaces" :key="key"><input v-model="line.surfaces" type="checkbox" :value="key">{{ label }}</label></fieldset>
                <div v-if="lastPage > 1" class="editor-actions"><button type="button" class="btn-secondary" :disabled="page === 1" @click="find(page - 1)">Previous procedures</button><span>{{ page }} / {{ lastPage }}</span><button type="button" class="btn-secondary" :disabled="page === lastPage" @click="find(page + 1)">More procedures</button></div>
                <p v-if="!loading && !procedures.length" class="hint">No active procedures found. Add one in Dental Procedures first.</p>
                <button type="button" class="btn-secondary" :disabled="!line.procedure_id || line.unit_price === '' || saving" @click="add">Add to Plan</button>
            </fieldset>
            <ol class="draft-items"><li v-for="(item, index) in form.items" :key="index"><div><strong>Visit {{ item.visit_number }} · {{ item.procedure_name }}</strong><p>{{ item.tooth ? `Tooth ${item.tooth}` : 'Whole mouth' }} {{ item.surfaces.join(', ') }} · {{ item.quantity }} × {{ Number(item.unit_price).toFixed(2) }}</p></div><button type="button" class="btn-secondary" :aria-label="`Remove treatment ${index + 1}`" :disabled="saving" @click="form.items.splice(index, 1)">Remove</button></li></ol>
            <p class="estimate">Estimated subtotal: {{ plan?.currency || options.currency }} {{ estimate.toFixed(2) }} <small>Tax is calculated when the draft is saved.</small></p>
            <div class="editor-actions"><button class="btn" :disabled="!form.items.length || saving">Save Draft</button><button type="button" class="btn-secondary" :disabled="saving" @click="emit('cancel')">Cancel</button></div>
        </form>
    </section>
</template>

<style scoped>
.plan-editor{padding:20px;margin:20px 0}.plan-editor h3{font-size:18px;font-weight:700;margin-bottom:16px}.line-editor{border:1px solid #dce5e5;border-radius:12px;padding:18px;margin-top:20px}.line-editor legend{font-weight:600;padding:0 5px}.procedure-search{display:flex;align-items:end;gap:10px;margin-bottom:15px}.procedure-search .field{flex:1}.surface-picker{display:flex;gap:12px;flex-wrap:wrap;margin:15px 0;font-size:13px}.surface-picker legend{font-weight:600;margin-bottom:8px}.surface-picker label{display:flex;gap:6px;align-items:center}.draft-items{margin-top:16px}.draft-items li{display:flex;justify-content:space-between;align-items:center;gap:12px;border-bottom:1px solid #e2e8f0;padding:13px 0}.draft-items p,.estimate small{font-size:13px;color:#64748b}.estimate{margin-top:18px;font-weight:600}.estimate small{display:block;font-weight:400}.editor-actions{display:flex;align-items:center;gap:10px;margin-top:16px;flex-wrap:wrap}@media(max-width:600px){.plan-editor{padding:14px}.line-editor{padding:12px}.draft-items li{align-items:start}}
</style>
