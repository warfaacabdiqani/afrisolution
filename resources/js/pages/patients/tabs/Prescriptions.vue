<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { prescriptionService } from '../../../services/prescriptions';
import PrescriptionTable from '../../../components/prescriptions/PrescriptionTable.vue';
import FormErrors from '../../../components/ui/FormErrors.vue';
import '../../../../css/prescriptions.css';
const props = defineProps({ doctor: Boolean });
const route = useRoute(), rows = ref([]), meta = ref(null), error = ref(null), busy = ref(false);
async function load(page = 1) { busy.value = true; try { const r = await prescriptionService.list({ [props.doctor ? 'doctor_id' : 'patient_id']: route.params.id, page, per_page: 20 }); rows.value = r.data.data; meta.value = r.data.meta; } catch(e) { error.value = e; } finally { busy.value = false; } }
onMounted(() => load());
</script>
<template><section class="clinic-panel rx-section"><h2>Prescriptions</h2><FormErrors :error="error" /><p v-if="busy" role="status">Loading prescriptions…</p><PrescriptionTable v-else-if="rows.length" :rows="rows" @changed="load()" /><p v-else>No prescriptions have been created yet.</p><div v-if="meta?.last_page > 1" class="patient-pagination"><button class="btn-secondary" :disabled="meta.current_page <= 1" @click="load(meta.current_page - 1)">Previous</button><span>{{ meta.current_page }} / {{ meta.last_page }}</span><button class="btn-secondary" :disabled="meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)">Next</button></div></section></template>
