<script setup>
import { ref } from 'vue';
import { useClinicContextStore } from '../../stores/clinicContext';
import { patientService } from '../../services/patients';
import FormErrors from '../ui/FormErrors.vue';
const props = defineProps({ patient: Object });
const emit = defineEmits(['changed']);
const context = useClinicContextStore();
const confirm = ref(false), busy = ref(false), error = ref(null);
async function save() {
    busy.value = true; error.value = null;
    try { await patientService.status(props.patient.id, props.patient.status === 'archived' ? 'restore' : 'archive'); confirm.value = false; emit('changed'); }
    catch (e) { error.value = e; }
    finally { busy.value = false; }
}
</script>
<template>
    <details class="patient-actions"><summary aria-label="More patient actions">⋮</summary><div><RouterLink :to="`/app/patients/${patient.id}`">View profile</RouterLink><RouterLink v-if="context.can('patients.update') && patient.status !== 'archived'" :to="`/app/patients/${patient.id}/edit`">Edit patient</RouterLink><button v-if="context.can(patient.status === 'archived' ? 'patients.restore' : 'patients.archive')" @click="confirm = true">{{ patient.status === 'archived' ? 'Restore patient' : 'Archive patient' }}</button></div></details>
    <Teleport to="body"><div v-if="confirm" class="modal-backdrop" @keydown.esc="!busy && (confirm = false)"><section class="modal-card" role="dialog" aria-modal="true" aria-labelledby="patient-confirm"><h2 id="patient-confirm" class="text-xl font-bold">{{ patient.status === 'archived' ? 'Restore' : 'Archive' }} {{ patient.full_name }}?</h2><p class="my-4 text-slate-500">{{ patient.status === 'archived' ? 'The patient will appear in active patient lists again.' : 'The patient will no longer appear in active patient lists, but their medical record will be retained.' }}</p><FormErrors :error="error" /><div class="mt-6 flex justify-end gap-3"><button class="btn-secondary" :disabled="busy" @click="confirm = false">Cancel</button><button class="btn" :disabled="busy" @click="save">{{ patient.status === 'archived' ? 'Restore Patient' : 'Archive Patient' }}</button></div></section></div></Teleport>
</template>
