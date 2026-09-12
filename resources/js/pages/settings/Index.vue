<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter, onBeforeRouteLeave, onBeforeRouteUpdate } from 'vue-router';
import { useClinicContextStore } from '../../stores/clinicContext';
import { useClinicSettingsStore } from '../../stores/clinicSettings';
import { clinicSettingsService as service } from '../../services/clinicSettings';
import SettingsDialog from '../../components/settings/SettingsDialog.vue';
import SettingFields from '../../components/settings/SettingFields.vue';
import BrandingSettings from '../../components/settings/BrandingSettings.vue';
import BranchSettings from '../../components/settings/BranchSettings.vue';
import SubscriptionSettings from '../../components/settings/SubscriptionSettings.vue';
import DocumentPreviews from '../../components/settings/DocumentPreviews.vue';
import FormErrors from '../../components/ui/FormErrors.vue';
import '../../../css/clinic-settings.css';
const route = useRoute(), router = useRouter(), context = useClinicContextStore(), store = useClinicSettingsStore();
if(store.scope !== `${context.data.clinic.id}:${context.data.branch.id}`) { store.data = null; store.notice = ''; }
const section = computed(() => route.params.section || 'general'), definition = computed(() => store.data?.sections[section.value]);
const ready = ref(false);
const form = ref({}), original = ref('{}'), childDirty = ref(false), saving = ref(false), error = ref(null), confirm = ref(false), previewRevision = ref(0);
const dirty = computed(() => childDirty.value || JSON.stringify(form.value) !== original.value);
let resolveNavigation;
const copy = v => JSON.parse(JSON.stringify(v));
function initialize() { form.value = copy(definition.value?.values || {}); original.value = JSON.stringify(form.value); childDirty.value = false; }
async function load() { await store.load(`${context.data.clinic.id}:${context.data.branch.id}`); initialize(); ready.value = !store.error; }
async function saved() { childDirty.value = false; store.notice = 'Clinic settings updated successfully.'; if(section.value === 'branches') { await context.load(); return; } await load(); previewRevision.value++; }
async function save() { if (saving.value || !dirty.value) return; saving.value = true; error.value = null; const data = copy(form.value); for(const [key, field] of Object.entries(definition.value.fields)) if(field.locked && ['code','status'].includes(key)) delete data[key]; try { const result = await service.save(section.value, data); form.value = copy(result.data.data); original.value = JSON.stringify(form.value); if(section.value === 'general') { context.data.clinic.name = form.value.name; context.data.clinic.timezone = form.value.timezone; } await saved(); } catch(e) { error.value = e; } finally { saving.value = false; } }
function guard() { if(!dirty.value) return true; confirm.value = true; return new Promise(resolve => { resolveNavigation = resolve; }); }
function decide(leave) { confirm.value = false; if(leave) { childDirty.value = false; original.value = JSON.stringify(form.value); } resolveNavigation?.(leave); resolveNavigation = null; }
function beforeUnload(event) { if(dirty.value) { event.preventDefault(); event.returnValue = ''; } }
onBeforeRouteLeave(guard); onBeforeRouteUpdate(guard);
watch(section, initialize);
onMounted(() => { store.beforeExit = guard; load(); window.addEventListener('beforeunload', beforeUnload); });
onUnmounted(() => { if(store.beforeExit === guard) store.beforeExit = null; window.removeEventListener('beforeunload', beforeUnload); resolveNavigation?.(false); });
</script>
<template><div class="patient-page-header"><div><p class="patient-breadcrumb"><RouterLink to="/app/dashboard">Dashboard</RouterLink><span>/</span>Clinic Settings</p><h1>Clinic Settings</h1><p>Manage your clinic profile, preferences and operational settings.</p></div></div><p class="cs-notice">These settings apply only to <strong>{{ context.data.clinic.name }}</strong>.</p><p v-if="store.notice" class="cs-success" role="status">{{ store.notice }}<button aria-label="Dismiss notification" @click="store.notice = ''">×</button></p><FormErrors :error="store.error || error" />
<div v-if="!ready && !store.error" class="clinic-panel p-6" role="status">Loading clinic settings…</div><button v-else-if="store.error && !ready" class="btn" @click="load">Try again</button>
<template v-if="store.data && ready"><nav class="cs-tabs" aria-label="Clinic settings categories"><RouterLink v-for="(entry, key) in store.data.sections" :key="key" :to="`/app/settings/${key}`" :class="{ active: key === section }">{{ entry.label }}</RouterLink></nav><label class="field cs-category">Settings category<select :value="section" aria-label="Settings category" @change="router.push(`/app/settings/${$event.target.value}`)"><option v-for="(entry, key) in store.data.sections" :key="key" :value="key">{{ entry.label }}</option></select></label>
<div class="cs-layout"><section class="clinic-panel cs-main"><template v-if="definition"><div class="cs-section-heading"><div><h2>{{ definition.label }}</h2><p v-if="!definition.can_update && section !== 'subscription'" class="cs-hint">You have read-only access to this section.</p></div></div><p v-if="definition.notice" class="cs-notice">{{ definition.notice }}</p>
<BrandingSettings v-if="section === 'branding'" :values="definition.values" :can-update="definition.can_update" @dirty="childDirty = $event" @saved="saved" />
<BranchSettings v-else-if="section === 'branches'" :summary="store.data.summary" :timezones="store.data.timezones" :can-update="definition.can_update" @dirty="childDirty = $event" @saved="saved" />
<SubscriptionSettings v-else-if="section === 'subscription'" :summary="store.data.summary" />
<form v-else @submit.prevent="save"><SettingFields :fields="definition.fields" :model="form" :timezones="store.data.timezones" :branches="store.data.branches" :disabled="!definition.can_update || saving" />
<div v-if="section === 'patients'" class="cs-number-preview">Patient number preview <strong>{{ form.number_prefix }}{{ '1'.padStart(Math.min(Number(form.number_length) || 6,12),'0') }}</strong><small>Only future patient numbers use these preferences.</small></div>
<div v-if="section === 'billing'" class="cs-number-preview">Invoice: <strong>{{ form.invoice_prefix }}{{ '1'.padStart(Math.min(Number(form.number_length) || 6,12),'0') }}</strong> Receipt: <strong>{{ form.receipt_prefix }}{{ '1'.padStart(Math.min(Number(form.number_length) || 6,12),'0') }}</strong><small>Existing document numbers are never changed. Configure footers in Documents.</small></div>
<p v-if="section === 'clinical'" class="cs-hint mt-4">Prescription header, footer and signature options are in Documents.</p>
<div v-if="definition.can_update" class="cs-save"><button type="button" class="btn-secondary" :disabled="!dirty || saving" @click="initialize(); error = null">Discard Changes</button><button class="btn" :disabled="!dirty || saving">{{ saving ? 'Saving…' : 'Save Changes' }}</button></div></form>
<DocumentPreviews v-if="section === 'documents'" :features="store.data.summary.features" :revision="previewRevision" />
</template><div v-else><h2>Settings unavailable</h2><p>This section is not included in your current plan.</p><RouterLink to="/app/settings/general">Back to General</RouterLink></div></section>
<aside class="clinic-panel cs-summary"><h2>Clinic Information</h2><h3>{{ store.data.summary.clinic.name }}</h3><dl><dt>Clinic Code</dt><dd>{{ store.data.summary.clinic.code }}</dd><dt>Status</dt><dd class="capitalize">{{ store.data.summary.clinic.status }}</dd><dt>Main Branch</dt><dd>{{ store.data.summary.main_branch }}</dd><dt>Current Plan</dt><dd>{{ store.data.summary.plan?.name }}</dd><dt>Subscription</dt><dd class="capitalize">{{ store.data.summary.subscription?.status }}</dd><dt>Total Branches</dt><dd>{{ store.data.summary.usage.branches }}</dd><dt>Total Members</dt><dd>{{ store.data.summary.usage.tenant_memberships }}</dd><dt>Created</dt><dd>{{ new Date(store.data.summary.clinic.created_at).toLocaleDateString() }}</dd></dl><RouterLink class="btn-secondary" to="/app/settings/subscription">View Subscription</RouterLink></aside></div></template>
<SettingsDialog v-if="confirm" title-id="unsaved-settings" @cancel="decide(false)"><h2 id="unsaved-settings">Discard unsaved changes?</h2><p>Your changes have not been saved.</p><div class="cs-save"><button class="btn-secondary" @click="decide(false)">Keep Editing</button><button class="btn" @click="decide(true)">Discard and Leave</button></div></SettingsDialog></template>
