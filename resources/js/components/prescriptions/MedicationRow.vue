<script setup>
import { ref, onUnmounted } from 'vue';
import { prescriptionService as service } from '../../services/prescriptions';
import { useClinicContextStore } from '../../stores/clinicContext';
import FormErrors from '../ui/FormErrors.vue';
const props = defineProps({ item: Object, index: Number, options: Object });
defineEmits(['remove']);
const context = useClinicContextStore(), rows = ref([]), error = ref(null), busy = ref(false), searched = ref(false);
let timer, sequence = 0;
function search() {
    props.item.medication_id = null; clearTimeout(timer);
    const current = ++sequence;
    timer = setTimeout(async () => { try { const result = (await service.medications(props.item.medication_name)).data.data; if (current === sequence) { rows.value = result; searched.value = true; } } catch(e) { error.value = e; } }, 250);
}
function select(medication) { Object.assign(props.item, { medication_id: medication.id, medication_name: medication.name, strength: medication.strength || '', dosage_form: medication.dosage_form || '' }); rows.value = []; searched.value = false; }
async function catalog() {
    busy.value = true; error.value = null;
    try { select((await service.createMedication({ name: props.item.medication_name, strength: props.item.strength, dosage_form: props.item.dosage_form })).data.data); }
    catch(e) { error.value = e; } finally { busy.value = false; }
}
onUnmounted(() => { clearTimeout(timer); sequence++; });
</script>
<template><article class="rx-medication"><div class="rx-row-actions"><strong>Medication {{ index + 1 }}</strong><button type="button" class="btn-secondary" @click="$emit('remove')">Remove</button></div><div class="rx-med-grid"><div><label class="field">Medication *<input v-model="item.medication_name" required maxlength="255" placeholder="Search medication..." autocomplete="off" @input="search"></label><div v-if="rows.length" class="rx-search-results"><button v-for="med in rows" :key="med.id" type="button" @click="select(med)">{{ med.name }} {{ med.strength }}<small>{{ med.generic_name }} {{ med.dosage_form }}</small></button></div><p v-if="searched && !rows.length" class="text-sm">No catalog match. This order will use the entered name.</p></div><label class="field">Strength<input v-model="item.strength" maxlength="100" placeholder="e.g. 500 mg"></label><label class="field">Dosage form<input v-model="item.dosage_form" maxlength="100" placeholder="e.g. Capsule"></label><label class="field">Dose *<input v-model="item.dose" required maxlength="255" placeholder="e.g. 1 capsule"></label><label class="field">Route *<select aria-label="Route *" v-model="item.route" required><option value="">Select route</option><option v-for="(label, value) in options.routes" :key="value" :value="value">{{ label }}</option></select></label><label class="field">Frequency *<select aria-label="Frequency *" v-model="item.frequency" required><option value="">Select frequency</option><option v-for="(label, value) in options.frequencies" :key="value" :value="value">{{ label }}</option></select></label><label v-if="item.frequency === 'custom'" class="field">Custom frequency *<input v-model="item.custom_frequency" required maxlength="255"></label><label class="field">Duration *<input v-model="item.duration" required maxlength="255" placeholder="e.g. 7 days"></label><label class="field">Quantity<input v-model="item.quantity" type="number" min="0.01" max="9999999999" step="0.01"></label></div><label class="field mt-4">Instructions<textarea v-model="item.instructions" maxlength="2000" placeholder="Medication instructions"></textarea></label><button v-if="context.can('prescriptions.medications.manage') && !item.medication_id && item.medication_name.trim()" class="btn-secondary mt-3" type="button" :disabled="busy" @click="catalog">{{ busy ? 'Saving…' : 'Add this medication to clinic catalog' }}</button><FormErrors :error="error" /></article></template>
