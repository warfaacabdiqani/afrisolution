<script setup>
import { computed, nextTick, onUnmounted, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { dentalService } from '../../services/dental';
import { useClinicContextStore } from '../../stores/clinicContext';
import { usePatientStore } from '../../stores/patients';
import FormErrors from '../../components/ui/FormErrors.vue';
import ToothChart from '../../components/dental/ToothChart.vue';
import PlanEditor from '../../components/dental/PlanEditor.vue';

const route = useRoute(), context = useClinicContextStore(), patients = usePatientStore();
const patientId = computed(() => Number(route.params.id));
const scope = computed(() => `${context.data?.clinic.id || ''}:${context.data?.branch?.id || ''}:${patientId.value}`);
const options = ref(null), chart = ref({ findings: [], treatments: [] }), plans = ref([]), selected = ref(''), historyAll = ref(false);
const error = ref(null), loading = ref(false), saving = ref(false), notice = ref(''), editor = ref(false), editingPlan = ref(null), action = ref(null);
const finding = reactive({ condition: 'sound', surfaces: [], notes: '' });
const actionForm = reactive({ reason: '', notes: '', appointment_id: '' });
const dialog = ref(null), appointments = ref([]), appointmentsLoading = ref(false);
let actionGeneration = 0, returnFocus = null;
const writable = computed(() => patients.patient?.id === patientId.value && patients.patient?.status !== 'archived');
const findings = computed(() => chart.value.findings.filter(f => historyAll.value || f.tooth === selected.value));
const treatments = computed(() => chart.value.treatments.filter(t => historyAll.value || t.tooth === selected.value));
const money = value => Number(value || 0).toFixed(2);
const date = value => value ? new Date(value).toLocaleString() : '';
const visits = plan => [...new Set(plan.items.map(i => i.visit_number))].sort((a, b) => a - b);
let generation = 0;
async function load() {
    const token = ++generation; loading.value = true; error.value = null;
    try {
        const [o, c, p] = await Promise.all([dentalService.options(), dentalService.chart(patientId.value), dentalService.plans(patientId.value)]);
        if (token !== generation) return;
        options.value = o.data.data; chart.value = c.data.data; plans.value = p.data.data;
    } catch (e) { if (token === generation) error.value = e; }
    finally { if (token === generation) loading.value = false; }
}
async function mutate(callback, message, after = () => {}) {
    if (saving.value) return;
    const key = scope.value; const token = generation;
    saving.value = true; error.value = null; notice.value = '';
    try {
        const result = await callback();
        if (key !== scope.value || token !== generation) return;
        after(result); action.value = null; notice.value = message; await load();
    } catch (e) { if (key === scope.value && token === generation) error.value = e; }
    finally { if (key === scope.value) saving.value = false; }
}
function recordFinding() {
    mutate(() => dentalService.finding(patientId.value, { tooth: selected.value, ...finding }), 'Finding recorded.', () => { finding.notes = ''; finding.surfaces = []; });
}
function newPlan(plan = null) { editingPlan.value = plan; editor.value = true; error.value = null; }
function savePlan(data) {
    mutate(() => dentalService.savePlan(patientId.value, editingPlan.value?.id, data), 'Treatment plan saved.', () => { editor.value = false; editingPlan.value = null; });
}
async function openAction(type, plan = null, item = null) {
    returnFocus = document.activeElement;
    action.value = { type, plan, item }; Object.assign(actionForm, { reason: '', notes: '', appointment_id: '' }); error.value = null;
    appointments.value = []; const token = ++actionGeneration;
    await nextTick(); dialog.value?.querySelector('textarea, input, button')?.focus();
    if (type === 'complete') {
        appointmentsLoading.value = true;
        try { const { data } = await dentalService.appointments(plan.id); if (token === actionGeneration) appointments.value = data.data; }
        catch (e) { if (token === actionGeneration) error.value = e; }
        finally { if (token === actionGeneration) appointmentsLoading.value = false; }
    }
}
function trapFocus(event) {
    if (event.key !== 'Tab') return;
    const controls = [...dialog.value.querySelectorAll('button:not(:disabled), input, textarea, select, a[href]')];
    const first = controls[0], last = controls.at(-1);
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
}
watch(action, async value => { if (!value) { ++actionGeneration; appointmentsLoading.value = false; await nextTick(); returnFocus?.focus(); } });
function confirmAction() {
    const a = action.value;
    if (a.type === 'void') mutate(() => dentalService.voidFinding(patientId.value, a.item.id, actionForm.reason), 'Finding marked as entered in error. Original retained.');
    if (a.type === 'cancel') mutate(() => dentalService.status(a.plan, 'cancelled', actionForm.reason), 'Remaining planned treatments cancelled.');
    if (a.type === 'complete') mutate(() => dentalService.complete(a.plan.id, a.item.id, { notes: actionForm.notes, appointment_id: actionForm.appointment_id ? Number(actionForm.appointment_id) : null }), 'Treatment completed and added to tooth history.');
}
watch(selected, () => { finding.surfaces = []; finding.notes = ''; });
watch(scope, () => {
    ++generation; options.value = null; chart.value = { findings: [], treatments: [] }; plans.value = [];
    editor.value = false; editingPlan.value = null; action.value = null; selected.value = ''; historyAll.value = false;
    saving.value = false; notice.value = ''; Object.assign(finding, { condition: 'sound', surfaces: [], notes: '' });
    if (context.allowed('dental')) load();
}, { immediate: true });
onUnmounted(() => { ++generation; ++actionGeneration; });
</script>

<template>
    <div class="dental-workspace">
        <div class="dental-heading"><div><h2>Dental chart</h2><p>Universal numbering · Findings and treatment history for this patient.</p></div><RouterLink class="btn-secondary" to="/app/dental/procedures">Procedure Catalog</RouterLink></div>
        <p v-if="notice" class="clinic-trial" role="status">{{ notice }}</p>
        <p v-if="!writable" class="dental-notice">This patient is read-only. Existing chart records and plans remain available.</p>
        <FormErrors :error="error" />
        <p v-if="loading" class="p-4" role="status">Loading dental records…</p>
        <template v-if="options">
            <section class="clinic-panel chart-panel"><ToothChart v-model="selected" :findings="chart.findings" :conditions="options.conditions" /></section>
            <div class="dental-columns">
                <section class="clinic-panel finding-panel">
                    <h3>{{ selected ? `Tooth ${selected}` : 'Select a tooth' }}</h3>
                    <p v-if="!selected" class="hint">Select an adult or primary tooth on the chart to record a finding.</p>
                    <form v-else-if="writable && context.can('dental.chart')" @submit.prevent="recordFinding">
                        <label class="field">Finding<select v-model="finding.condition" aria-label="Finding"><option v-for="(label, value) in options.conditions" :key="value" :value="value">{{ label }}</option></select></label>
                        <fieldset class="surface-picker"><legend>Surfaces (optional)</legend><label v-for="(label, key) in options.surfaces" :key="key"><input v-model="finding.surfaces" type="checkbox" :value="key">{{ label }}</label></fieldset>
                        <label class="field">Finding notes<textarea v-model="finding.notes" maxlength="4000" rows="3"></textarea></label>
                        <button class="btn mt-4" :disabled="saving || loading">Record Finding</button>
                    </form>
                    <p v-else class="hint">You can view this tooth’s recorded history below.</p>
                </section>
                <section class="clinic-panel history-panel">
                    <div class="history-heading"><h3>{{ historyAll ? 'All tooth history' : selected ? `Tooth ${selected} history` : 'Tooth history' }}</h3><label><input v-model="historyAll" type="checkbox"> Show all teeth</label></div>
                    <p v-if="!findings.length && !treatments.length" class="hint">No recorded history{{ selected && !historyAll ? ` for tooth ${selected}` : '' }}. Uncharted teeth have not been marked as sound.</p>
                    <article v-for="f in findings" :key="`f${f.id}`" class="history-entry" :class="{ voided: f.voided_at }">
                        <strong>Tooth {{ f.tooth }} · {{ options.conditions[f.condition] }} <span v-if="f.surfaces.length">({{ f.surfaces.join(', ') }})</span></strong>
                        <small>{{ date(f.created_at) }} · {{ f.author?.name || 'Recorded clinician' }}</small><p v-if="f.notes">{{ f.notes }}</p>
                        <p v-if="f.voided_at" class="void-note">Entered in error: {{ f.void_reason }}</p>
                        <button v-else-if="writable && context.can('dental.chart')" class="history-action" :disabled="saving" @click="openAction('void', null, f)">Mark entered in error</button>
                    </article>
                    <article v-for="t in treatments" :key="`t${t.id}`" class="history-entry treatment"><strong>{{ t.tooth ? `Tooth ${t.tooth}` : 'Whole mouth' }} · {{ t.procedure_name }}</strong><small>Completed {{ date(t.completed_at) }} · {{ t.completed_by?.name || 'Clinician' }} · Visit {{ t.visit_number }}</small><p v-if="t.completion_notes">{{ t.completion_notes }}</p></article>
                </section>
            </div>

            <div class="dental-heading plans-heading"><div><h2>Treatment plans</h2><p>Organize procedures across visits, agree prices, and track completed care.</p></div><button v-if="writable && context.can('dental.plans.manage')" class="btn" :disabled="saving" @click="newPlan()">New Treatment Plan</button></div>
            <PlanEditor v-if="editor" :key="editingPlan?.id || 'new'" :plan="editingPlan" :options="options" :selected-tooth="selected" :saving="saving" @save="savePlan" @cancel="editor = false" />
            <p v-if="!plans.length" class="clinic-panel dental-empty">No treatment plans yet. Create a plan with procedures grouped by visit.</p>
            <article v-for="plan in plans" :key="plan.id" class="clinic-panel treatment-plan">
                <header class="plan-header"><div><h3>{{ plan.title }}</h3><p>Plan #{{ plan.id }} · {{ context.data?.branches?.find(b => b.id === plan.branch_id)?.name || 'Authorized branch' }}</p></div><span class="plan-status" :class="plan.status">{{ plan.status === 'completed' ? 'Plan completed' : plan.status }}</span></header>
                <p v-if="plan.notes" class="plan-notes">{{ plan.notes }}</p>
                <p v-if="plan.cancellation_reason" class="dental-notice">Cancelled: {{ plan.cancellation_reason }}</p>
                <section v-for="visit in visits(plan)" :key="visit" class="plan-visit"><h4>Visit {{ visit }}</h4>
                    <div v-for="item in plan.items.filter(i => i.visit_number === visit)" :key="item.id" class="treatment-row">
                        <div><strong>{{ item.procedure_name }}</strong><p>{{ item.tooth ? `Tooth ${item.tooth}` : 'Whole mouth / general procedure' }}{{ item.surfaces.length ? ` · ${item.surfaces.join(', ')}` : '' }} · {{ item.quantity }} × {{ plan.currency }} {{ money(item.unit_price) }}</p><p v-if="item.notes">{{ item.notes }}</p><small class="item-status">{{ item.status }}<span v-if="item.completed_at"> · {{ date(item.completed_at) }}</span></small></div>
                        <div class="treatment-actions"><strong>{{ plan.currency }} {{ money(item.amount) }}</strong><button v-if="writable && plan.status === 'accepted' && item.status === 'planned' && context.can('dental.treatments.complete')" class="btn-secondary" :aria-label="`Complete treatment ${item.id}`" :disabled="saving" @click="openAction('complete', plan, item)">Complete</button>
                            <RouterLink v-if="item.invoice_id && context.allowed('billing')" class="btn-secondary" :to="`/app/billing/invoices/${item.invoice_id}`">View Invoice</RouterLink>
                            <button v-else-if="item.status === 'completed' && context.allowed('billing') && context.can('billing.create')" class="btn-secondary" :disabled="saving" @click="mutate(() => dentalService.invoice(plan.id, item.id), 'Invoice created. Open it to record payment or print.')">Create Invoice</button>
                        </div>
                    </div>
                </section>
                <footer class="plan-footer"><div class="plan-totals"><span>Subtotal {{ plan.currency }} {{ money(plan.subtotal) }}</span><span>Tax {{ plan.currency }} {{ money(plan.tax) }}</span><strong>Plan estimate {{ plan.currency }} {{ money(plan.total) }}</strong><small>Only completed treatments can be invoiced.</small></div><div v-if="writable && context.can('dental.plans.manage')" class="plan-actions"><template v-if="plan.status === 'draft'"><button class="btn-secondary" :disabled="saving" @click="newPlan(plan)">Edit Draft</button><button class="btn" :disabled="saving" @click="mutate(() => dentalService.status(plan, 'accepted'), 'Plan accepted. Treatments are ready to complete.')">Accept Plan</button></template><button v-if="['draft', 'accepted'].includes(plan.status)" class="btn-secondary" :disabled="saving" @click="openAction('cancel', plan)">Cancel Plan</button></div></footer>
            </article>
        </template>
        <div v-if="action" class="dental-dialog-backdrop" @keydown.esc="!saving && (action = null)">
            <section ref="dialog" class="dental-dialog clinic-panel" role="dialog" aria-modal="true" aria-labelledby="dental-action-title" tabindex="-1" @keydown="trapFocus">
                <h2 id="dental-action-title">{{ action.type === 'complete' ? 'Complete treatment' : action.type === 'void' ? 'Correct a finding' : 'Cancel treatment plan' }}</h2>
                <FormErrors :error="error" />
                <form @submit.prevent="confirmAction">
                    <template v-if="action.type === 'complete'"><p>{{ action.item.procedure_name }} · {{ action.item.tooth ? `Tooth ${action.item.tooth}` : 'Whole mouth' }}</p><label class="field">Completion notes<textarea v-model="actionForm.notes" maxlength="4000" rows="4"></textarea></label><label class="field">Linked appointment (optional)<select v-model="actionForm.appointment_id" aria-label="Linked appointment" :disabled="appointmentsLoading"><option value="">No linked appointment</option><option v-for="appointment in appointments" :key="appointment.id" :value="appointment.id">{{ appointment.appointment_number }} · {{ appointment.starts_at }} · {{ appointment.status.replaceAll('_', ' ') }}</option></select><small>In-consultation and completed appointments for this patient and plan branch.</small></label></template>
                    <template v-else><p>{{ action.type === 'void' ? 'The original finding stays in the history with your correction reason.' : 'Completed treatments remain in the history and can still be invoiced.' }}</p><label class="field">Reason<textarea v-model.trim="actionForm.reason" required maxlength="2000" rows="4"></textarea></label></template>
                    <div class="plan-actions"><button class="btn" :disabled="saving">{{ action.type === 'complete' ? 'Confirm Completion' : 'Confirm' }}</button><button type="button" class="btn-secondary" :disabled="saving" @click="action = null">Back</button></div>
                </form>
            </section>
        </div>
    </div>
</template>

<style scoped>
.dental-workspace{min-width:0}.dental-heading{display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;margin:22px 0 16px}.dental-heading h2{font-size:21px;font-weight:700}.dental-heading p,.plan-header p{color:#64748b;font-size:13px;margin-top:4px}.chart-panel{padding:20px;min-width:0}.dental-columns{display:grid;grid-template-columns:minmax(240px,1fr) minmax(0,1.6fr);gap:20px;margin-top:20px}.finding-panel,.history-panel{padding:20px;min-width:0}.finding-panel h3,.history-panel h3,.treatment-plan h3{font-size:17px;font-weight:700;margin-bottom:13px}.surface-picker{display:flex;flex-wrap:wrap;gap:12px;margin:15px 0;font-size:13px}.surface-picker legend{margin-bottom:7px;font-weight:600}.surface-picker label{display:flex;align-items:center;gap:6px}.history-heading{display:flex;justify-content:space-between;align-items:start;gap:8px;flex-wrap:wrap}.history-heading label{font-size:12px}.history-panel{max-height:460px;overflow:auto}.history-entry{border-top:1px solid #e2e8f0;padding:12px 0;font-size:13px}.history-entry small{display:block;color:#64748b;margin-top:4px}.history-entry p{white-space:pre-wrap;margin-top:7px;overflow-wrap:anywhere}.history-entry.voided{opacity:.7}.void-note{color:#9a3412}.history-action{font-size:12px;color:#0f766e;margin-top:8px;text-decoration:underline}.treatment{border-left:3px solid #0f766e;padding-left:10px}.plans-heading{margin-top:30px}.treatment-plan{padding:20px;margin-top:18px}.plan-header{display:flex;justify-content:space-between;align-items:start;gap:12px}.plan-header h3{margin-bottom:0}.plan-status{font-size:12px;font-weight:600;text-transform:capitalize;background:#f1f5f9;padding:5px 10px;border-radius:20px}.plan-status.accepted{background:#dbeafe;color:#1e40af}.plan-status.completed{background:#dcfce7;color:#166534}.plan-status.cancelled{background:#fee2e2;color:#991b1b}.plan-notes{white-space:pre-wrap;margin:13px 0;font-size:14px}.plan-visit{margin-top:18px}.plan-visit h4{font-weight:700;background:#f5f8f8;padding:10px;border-radius:7px;font-size:14px}.treatment-row{display:flex;justify-content:space-between;gap:16px;padding:14px 4px;border-bottom:1px solid #e2e8f0;font-size:14px}.treatment-row p{font-size:13px;color:#64748b;margin-top:5px;white-space:pre-wrap;overflow-wrap:anywhere}.item-status{text-transform:capitalize;display:block;margin-top:6px;color:#0f766e}.treatment-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;justify-content:end}.plan-footer{display:flex;justify-content:space-between;align-items:end;gap:15px;margin-top:20px}.plan-totals{display:flex;flex-direction:column;gap:4px;font-size:13px}.plan-totals strong{font-size:16px}.plan-totals small{color:#64748b}.plan-actions{display:flex;gap:9px;flex-wrap:wrap}.dental-empty{padding:24px;color:#64748b}.dental-notice{padding:12px;background:#fff7ed;color:#9a3412;font-size:13px;border-radius:8px;margin:12px 0}.dental-dialog-backdrop{position:fixed;inset:0;background:#0f172a80;z-index:100;display:flex;align-items:center;justify-content:center;padding:18px}.dental-dialog{padding:25px;width:100%;max-width:530px;max-height:90vh;overflow:auto}.dental-dialog h2{font-size:20px;font-weight:700;margin-bottom:14px}.dental-dialog .field,.dental-dialog .plan-actions{margin-top:16px}.dental-dialog p{font-size:14px;color:#64748b}@media(max-width:850px){.dental-columns{grid-template-columns:1fr}.treatment-row,.plan-footer{flex-direction:column}.treatment-actions{justify-content:start}}@media(max-width:500px){.chart-panel,.finding-panel,.history-panel,.treatment-plan{padding:13px}}
</style>
