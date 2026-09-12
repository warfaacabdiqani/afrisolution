<script setup>
import { ref, watch, onUnmounted } from 'vue';
import { prescriptionService as service } from '../../services/prescriptions';
import { useClinicContextStore } from '../../stores/clinicContext';
import FormErrors from '../ui/FormErrors.vue';
const props = defineProps({ modelValue: [Number, String] });
const emit = defineEmits(['update:modelValue']);
const context = useClinicContextStore(), search = ref(''), rows = ref([]), selected = ref(null), error = ref(null), busy = ref(false);
let timer, sequence = 0;
async function lookup(id) {
    const current = ++sequence; busy.value = true; error.value = null;
    try { const result = (await service.patients(id ? { id } : { search: search.value })).data.data; if (current !== sequence) return; if (id) selected.value = result[0] || null; else rows.value = result; }
    catch(e) { if (current === sequence) error.value = e; } finally { if (current === sequence) busy.value = false; }
}
function input() { clearTimeout(timer); timer = setTimeout(() => lookup(), 300); }
function select(p) { selected.value = p; emit('update:modelValue', p.id); rows.value = []; search.value = ''; }
watch(() => props.modelValue, id => { if (id && selected.value?.id != id) lookup(id); else if (!id) selected.value = null; }, { immediate: true });
onUnmounted(() => { clearTimeout(timer); sequence++; });
</script>
<template><div><label class="field">Patient *<input v-model="search" type="search" placeholder="Search by patient ID, name or phone" @input="input" @focus="lookup()" autocomplete="off"></label><p v-if="busy" role="status">Searching patients…</p><div v-if="rows.length" class="rx-search-results"><button v-for="p in rows" :key="p.id" type="button" @click="select(p)">{{ p.full_name }} · {{ p.patient_number }} · {{ p.phone || 'No phone' }}</button></div><p v-else-if="search && !busy && !error">No matching patients.</p><div v-if="selected" class="rx-selection"><strong>{{ selected.full_name }}</strong><p>{{ selected.patient_number }} · {{ selected.gender }} · {{ selected.age ?? '—' }} years · {{ selected.phone || 'No phone' }}</p><RouterLink v-if="context.allowed('patients')" :to="`/app/patients/${selected.id}/overview`">View Patient</RouterLink></div><FormErrors :error="error" /></div></template>
