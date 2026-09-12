<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { clinicSettingsService as service } from '../../services/clinicSettings';
import FormErrors from '../ui/FormErrors.vue';
const props = defineProps({ values: Object, canUpdate: Boolean });
const emit = defineEmits(['saved','dirty']);
const assets = { logo: 'Clinic Logo', small_logo: 'Small Logo / Icon', receipt_logo: 'Receipt Logo', prescription_logo: 'Prescription Logo', invoice_logo: 'Invoice Logo', stamp: 'Clinic Stamp' };
const files = ref({}), previews = ref({}), originals = ref({}), busy = ref(false), error = ref(null);
const urls = new Set(); let disposed = false;
function url(blob) { const value = URL.createObjectURL(blob); urls.add(value); return value; }
function select(key, event) { const file = event.target.files[0]; if (!file) return; if (!['image/png','image/jpeg','image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024) { error.value = { response: { data: { message: 'Choose a PNG, JPG or WEBP image up to 2 MB.' } } }; return; } error.value = null; files.value[key] = file; previews.value[key] = url(file); emit('dirty', true); }
function discard() { files.value = {}; previews.value = { ...originals.value }; emit('dirty', false); }
async function save() { if (busy.value) return; busy.value = true; error.value = null; try { for (const [key, file] of Object.entries(files.value)) { await service.upload(key, file); originals.value[key] = previews.value[key]; delete files.value[key]; } emit('dirty', false); emit('saved'); } catch(e) { error.value = e; } finally { busy.value = false; } }
onMounted(async () => { for (const key of Object.keys(props.values)) { try { const result = await service.asset(key); if (!disposed) originals.value[key] = previews.value[key] = url(result.data); } catch(e) { if(!disposed) error.value = e; } } });
onUnmounted(() => { disposed = true; urls.forEach(value => URL.revokeObjectURL(value)); });
</script>
<template><p class="cs-hint mb-5">PNG, JPG or WEBP, up to 2 MB and 4000 × 4000 pixels. Images are stored privately for this clinic. SVG uploads are not accepted.</p><FormErrors :error="error" /><div class="cs-brand-grid"><article v-for="(label, key) in assets" :key="key" class="cs-brand-card"><h3>{{ label }}</h3><img v-if="previews[key]" :src="previews[key]" :alt="label"><div v-else class="cs-image-empty">No image uploaded</div><label v-if="canUpdate" class="field">Choose {{ label }}<input type="file" accept="image/png,image/jpeg,image/webp" :disabled="busy" @change="select(key, $event)"></label></article></div><div v-if="canUpdate" class="cs-save"><button class="btn-secondary" :disabled="busy || !Object.keys(files).length" @click="discard">Discard Changes</button><button class="btn" :disabled="busy || !Object.keys(files).length" @click="save">{{ busy ? 'Uploading…' : 'Save Changes' }}</button></div></template>
