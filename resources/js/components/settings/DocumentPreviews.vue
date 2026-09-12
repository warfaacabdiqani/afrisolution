<script setup>
import { ref, onMounted, watch } from 'vue';
import { clinicSettingsService as service } from '../../services/clinicSettings';
import FormErrors from '../ui/FormErrors.vue';
const props = defineProps({ features: Object, revision: Number });
const kind = ref(props.features.prescriptions ? 'prescription' : 'invoice'), html = ref(''), error = ref(null);
async function load() { html.value = ''; error.value = null; try { html.value = (await service.preview(kind.value)).data; } catch(e) { error.value = e; } }
watch(() => [kind.value, props.revision], load); onMounted(load);
</script>
<template><section v-if="features.prescriptions || features.billing" class="cs-preview"><div class="cs-section-heading"><h3>Print Preview</h3><select v-model="kind" aria-label="Document preview type" class="compact-select"><option v-if="features.prescriptions" value="prescription">Prescription</option><option v-if="features.billing" value="invoice">Invoice</option><option v-if="features.billing" value="receipt">Receipt</option></select></div><p class="cs-hint">Preview uses saved clinic settings. Save changes to update it.</p><FormErrors :error="error" /><iframe v-if="html" :srcdoc="html" title="Clinic document preview"></iframe></section></template>
