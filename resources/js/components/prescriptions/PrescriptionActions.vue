<script setup>
import { prescriptionStatus as status } from '../../config/prescriptions';
import { ref, watch, nextTick } from 'vue';
import { useClinicContextStore } from '../../stores/clinicContext';
import { prescriptionService } from '../../services/prescriptions';
import FormErrors from '../ui/FormErrors.vue';
const props = defineProps({ prescription: Object });
const emit = defineEmits(['changed']);
const context = useClinicContextStore(), cancel = ref(false), reason = ref(''), busy = ref(false), error = ref(null);
const reasonInput = ref(null);
watch(cancel, async open => { if (open) { await nextTick(); reasonInput.value?.focus(); } });
function trap(event) { const elements = [...event.currentTarget.querySelectorAll('button:not(:disabled),textarea')]; const first = elements[0], last = elements.at(-1); if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); } else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); } }
async function action(name) {
    if (busy.value) return;
    busy.value = true; error.value = null;
    try { await prescriptionService.action(props.prescription.id, name, { reason: reason.value }); cancel.value = false; emit('changed', name === 'cancel' ? 'Prescription cancelled.' : 'Prescription sent to Pharmacy.'); }
    catch (e) { error.value = e; } finally { busy.value = false; }
}
</script>
<template>
    <details class="rx-actions"><summary aria-label="Prescription actions">⋮</summary><div class="rx-menu">
        <RouterLink v-if="prescription.editable && context.can('prescriptions.update')" :to="`/app/prescriptions/${prescription.id}/edit`">Edit</RouterLink>
        <RouterLink v-if="context.can('prescriptions.print')" :to="`/app/prescriptions/${prescription.id}/print`">Print Prescription</RouterLink>
        <RouterLink v-if="context.can('prescriptions.create')" :to="`/app/prescriptions/create?duplicate=${prescription.id}`">Duplicate</RouterLink>
        <RouterLink v-if="context.allowed('patients')" :to="`/app/patients/${prescription.patient_id}/overview`">View Patient</RouterLink>
        <RouterLink v-if="prescription.appointment_id && context.allowed('appointments')" :to="`/app/appointments/${prescription.appointment_id}`">View Appointment</RouterLink>
        <button v-if="prescription.status === status.active && context.can('prescriptions.update') && context.allowed('pharmacy')" :disabled="busy" @click="action('send-to-pharmacy')">Send to Pharmacy</button>
        <button v-if="context.can('prescriptions.cancel') && [status.draft,status.active,status.pending,status.partiallyDispensed].includes(prescription.status)" @click="cancel = true; error = null">Cancel Prescription</button>
    </div></details>
    <FormErrors v-if="!cancel" :error="error" />
    <Teleport to="body"><div v-if="cancel" class="rx-modal-backdrop" @keydown.esc="!busy && (cancel = false)"><section class="clinic-panel rx-modal" role="dialog" aria-modal="true" @keydown.tab="trap" :aria-labelledby="`cancel-title-${prescription.id}`"><h2 :id="`cancel-title-${prescription.id}`">Cancel {{ prescription.prescription_number }}?</h2><p>The clinical record and dispensing history will be retained.</p><form @submit.prevent="action('cancel')"><label class="field">Cancellation Reason *<textarea ref="reasonInput" v-model="reason" required maxlength="2000" autofocus></textarea></label><FormErrors :error="error" /><div class="rx-form-actions"><button type="button" class="btn-secondary" :disabled="busy" @click="cancel = false">Keep Prescription</button><button class="btn" :disabled="busy || !reason.trim()">{{ busy ? 'Cancelling…' : 'Cancel Prescription' }}</button></div></form></section></div></Teleport>
</template>
