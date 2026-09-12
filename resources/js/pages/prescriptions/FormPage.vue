<script setup>
import { prescriptionStatus as status } from '../../config/prescriptions';
import { ref, reactive, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useClinicContextStore } from '../../stores/clinicContext';
import { usePrescriptionStore } from '../../stores/prescriptions';
import { prescriptionService as service } from '../../services/prescriptions';
import PatientSelector from '../../components/prescriptions/PatientSelector.vue';
import MedicationRow from '../../components/prescriptions/MedicationRow.vue';
import FormErrors from '../../components/ui/FormErrors.vue';
import '../../../css/prescriptions.css';
const route = useRoute(), router = useRouter(), context = useClinicContextStore(), store = usePrescriptionStore();
const editing = Boolean(route.params.id), busy = ref(false), loading = ref(true), error = ref(null), options = ref(null), appointments = ref([]), readonly = ref(false);
let key = 0;
const emptyItem = () => ({ _key: ++key, medication_id: null, medication_name: '', strength: '', dosage_form: '', dose: '', route: '', frequency: '', custom_frequency: '', duration: '', quantity: '', instructions: '' });
const form = reactive({ branch_id: Number(route.query.branch_id) || context.data.branch.id, patient_id: route.query.patient_id || '', doctor_id: route.query.doctor_id || '', appointment_id: route.query.appointment_id || '', prescription_date: context.data.today, diagnosis: '', notes: '', internal_notes: '', items: [emptyItem()] });
async function loadAppointments() {
    if (!form.patient_id || !form.doctor_id) { appointments.value = []; return; }
    try { appointments.value = (await service.appointments({ branch_id: form.branch_id, patient_id: form.patient_id, doctor_id: form.doctor_id })).data.data; }
    catch(e) { error.value = e; }
}
async function loadOptions() { options.value = (await service.options(form.branch_id)).data.data; if (!form.doctor_id) form.doctor_id = options.value.doctors.find(d => d.is_self)?.id || ''; }
watch(() => form.branch_id, async () => { if (loading.value) return; form.doctor_id = ''; form.appointment_id = ''; try { await loadOptions(); } catch(e) { error.value = e; } });
watch(() => [form.patient_id, form.doctor_id], () => { if (!loading.value) { form.appointment_id = ''; loadAppointments(); } });
async function save(prescriptionState, print = false) {
    if (busy.value) return;
    busy.value = true; error.value = null;
    const fields = ['medication_id','medication_name','strength','dosage_form','dose','route','frequency','custom_frequency','duration','quantity','instructions'];
    const data = { ...form, status: prescriptionState, appointment_id: form.appointment_id || null, items: form.items.map(item => Object.fromEntries(fields.map(field => [field, item[field] === '' ? null : item[field]]))) };
    try { const p = (await service.save(editing ? route.params.id : null, data)).data.data; store.notice = editing ? 'Prescription updated successfully.' : 'Prescription created successfully.'; await router.push(`/app/prescriptions/${p.id}${print ? '/print' : ''}`); }
    catch(e) { error.value = e; window.scrollTo({ top: 0, behavior: 'smooth' }); } finally { busy.value = false; }
}
onMounted(async () => {
    try {
        if (editing || route.query.duplicate) {
            const p = (await service.get(route.params.id || route.query.duplicate)).data.data;
            readonly.value = editing && !p.editable;
            for (const field of ['branch_id','patient_id','doctor_id','appointment_id','prescription_date','diagnosis','notes','internal_notes']) form[field] = p[field] || '';
            form.items = p.items.map(item => ({ ...emptyItem(), ...item }));
            if (!editing) { form.prescription_date = context.data.today; form.appointment_id = ''; form.internal_notes = ''; }
        }
        await loadOptions(); await loadAppointments();
    } catch(e) { error.value = e; } finally { loading.value = false; }
});
</script>
<template><div class="patient-page-header"><div><p class="patient-breadcrumb"><RouterLink to="/app/prescriptions">← Prescriptions</RouterLink></p><h1>{{ editing ? 'Edit Prescription' : 'New Prescription' }}</h1><p>Create a medication order for a patient.</p></div></div><FormErrors :error="error" /><p v-if="loading" class="clinic-panel p-6" role="status">Loading prescription form…</p><p v-else-if="readonly" class="clinic-panel p-6">This prescription is read-only after dispensing or closure. <RouterLink :to="`/app/prescriptions/${route.params.id}`">View prescription</RouterLink></p>
    <form v-else-if="options" class="rx-form" @submit.prevent="save(status.active)"><fieldset :disabled="busy"><section class="clinic-panel rx-section"><h2>Patient &amp; Prescriber</h2><div class="rx-grid"><PatientSelector v-model="form.patient_id" /><div class="space-y-4"><label class="field">Branch *<select aria-label="Branch *" v-model="form.branch_id" required><option v-for="b in context.data.branches" :key="b.id" :value="b.id">{{ b.name }}</option></select></label><label class="field">Prescriber *<select aria-label="Prescriber *" v-model="form.doctor_id" required><option value="">Select active clinician</option><option v-for="d in options.doctors" :key="d.id" :value="d.id">{{ d.full_name }} · {{ d.specialty }}</option></select></label><label class="field">Appointment (optional)<select v-model="form.appointment_id"><option value="">No linked appointment</option><option v-for="a in appointments" :key="a.id" :value="a.id">{{ a.appointment_number }} · {{ a.starts_at.slice(0,10) }}</option></select></label></div></div></section>
    <section class="clinic-panel rx-section mt-5"><h2>Prescription Information</h2><div class="rx-grid"><label class="field">Prescription Date *<input v-model="form.prescription_date" type="date" required></label><label class="field">Diagnosis / Clinical Indication<input v-model="form.diagnosis" maxlength="4000"></label><label class="field">Notes<textarea v-model="form.notes" maxlength="4000"></textarea></label><label class="field">Internal Notes<textarea v-model="form.internal_notes" maxlength="4000"></textarea><small>Visible to authorized clinic staff; excluded from printed prescriptions.</small></label></div></section>
    <section class="clinic-panel rx-section mt-5"><h2>Medication Orders</h2><p v-if="!form.items.length">No medications have been added to this prescription.</p><MedicationRow v-for="(item, index) in form.items" :key="item._key" :item="item" :index="index" :options="options" @remove="form.items.splice(index, 1)" /><button class="btn-secondary" type="button" :disabled="form.items.length >= 50" @click="form.items.push(emptyItem())">＋ Add Medication</button></section>
    <div class="rx-form-actions"><RouterLink class="btn-secondary" to="/app/prescriptions">Cancel</RouterLink><button type="button" class="btn-secondary" :disabled="busy" @click="save(status.draft)">Save Draft</button><button class="btn" :disabled="busy">{{ busy ? 'Saving…' : 'Save Prescription' }}</button><button v-if="context.can('prescriptions.print')" type="button" class="btn-secondary" :disabled="busy" @click="save(status.active, true)">Save &amp; Print</button></div></fieldset></form>
</template>
