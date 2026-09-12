<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { prescriptionService } from '../../services/prescriptions';
import FormErrors from '../../components/ui/FormErrors.vue';
import '../../../css/prescriptions.css';
const route = useRoute(), html = ref(''), frame = ref(null), error = ref(null), ready = ref(false);
onMounted(async () => { try { html.value = (await prescriptionService.print(route.params.id)).data; } catch(e) { error.value = e; } });
function print() { frame.value?.contentWindow.focus(); frame.value?.contentWindow.print(); }
</script>
<template><div class="patient-page-header"><div><p class="patient-breadcrumb"><RouterLink :to="`/app/prescriptions/${route.params.id}`">← Prescription</RouterLink></p><h1>Print Prescription</h1></div><button class="btn" :disabled="!ready" @click="print">Print</button></div><FormErrors :error="error" /><iframe v-if="html" ref="frame" :srcdoc="html" class="rx-print-frame" title="Patient-facing prescription preview" @load="ready = true"></iframe></template>
