<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import api from '../../../services/api';
import FormErrors from '../../../components/ui/FormErrors.vue';
import { useAuthStore } from '../../../stores/auth';

const route = useRoute();
const auth = useAuthStore();
const tab = computed(() => ['messages', 'templates', 'settings'].includes(route.query.tab) ? route.query.tab : 'messages');
const can = permission => auth.user?.platform_permissions?.includes(permission);
const connection = ref(null), templates = ref([]), history = ref(null);
const error = ref(null), notice = ref(''), busy = ref(false), composing = ref(false);
const filters = reactive({ recipient: '', status: '', date_from: '', date_to: '' });
const form = reactive({ business_account_id: '', phone_number_id: '', display_phone_number: '', access_token: '' });
const sendForm = reactive({ recipient: '', template_id: null, parameters: { header: [], body: [] } });
const selected = computed(() => templates.value.find(item => item.id === sendForm.template_id));
const configured = computed(() => connection.value?.status === 'configured');
const purposes = ['verification', 'welcome', 'security', 'trial', 'subscription', 'announcement', 'maintenance', 'general'];
const date = value => value ? new Date(value).toLocaleString() : '—';

async function loadConnection() {
    try {
        connection.value = (await api.get('/v1/platform/whatsapp/connection')).data.data;
        for (const key of ['business_account_id', 'phone_number_id', 'display_phone_number']) form[key] = connection.value[key] || '';
        form.access_token = '';
    } catch (e) { error.value = e; }
}
async function loadTemplates() {
    try { templates.value = (await api.get('/v1/platform/whatsapp/templates')).data.data; } catch (e) { error.value = e; }
}
async function loadMessages(page = 1) {
    try { history.value = (await api.get('/v1/platform/whatsapp/messages', { params: { ...filters, page } })).data.data; } catch (e) { error.value = e; }
}
async function loadTab() {
    error.value = null;
    await loadConnection();
    if (tab.value === 'templates') await loadTemplates();
    if (tab.value === 'messages') { await loadTemplates(); await loadMessages(); }
}
watch(tab, loadTab); onMounted(loadTab);
async function saveConnection() {
    if (busy.value) return; busy.value = true; error.value = null; notice.value = '';
    try { await api.put('/v1/platform/whatsapp/connection', { ...form }); await loadConnection(); notice.value = 'Platform WhatsApp connection saved.'; }
    catch (e) { error.value = e; } finally { busy.value = false; }
}
async function sync() {
    if (busy.value) return; busy.value = true; error.value = null; notice.value = '';
    try { const result = await api.post('/v1/platform/whatsapp/templates/sync'); await loadTemplates(); notice.value = `${result.data.count} platform template(s) synchronized.`; }
    catch (e) { error.value = e; } finally { busy.value = false; }
}
async function savePurpose(item) {
    error.value = null;
    try { const result = await api.patch(`/v1/platform/whatsapp/templates/${item.id}/purpose`, { purpose: item.purpose }); Object.assign(item, result.data.data); }
    catch (e) { error.value = e; await loadTemplates(); }
}
function chooseTemplate() {
    sendForm.parameters = { header: Array(selected.value?.parameter_schema?.header || 0).fill(''), body: Array(selected.value?.parameter_schema?.body || 0).fill('') };
}
async function send() {
    if (busy.value) return; busy.value = true; error.value = null; notice.value = '';
    try { await api.post('/v1/platform/whatsapp/messages', { ...sendForm }); composing.value = false; sendForm.recipient = ''; notice.value = 'Platform message queued.'; await loadMessages(); }
    catch (e) { error.value = e; } finally { busy.value = false; }
}
</script>

<template>
    <div>
        <header class="page-header"><div><p class="eyebrow">Platform administration</p><h1>WhatsApp</h1><p>Manage Afriso platform communications.</p></div></header>
        <nav class="mb-6 flex flex-wrap gap-2 border-b border-slate-200 pb-4" aria-label="Platform WhatsApp sections">
            <RouterLink v-for="item in [['messages','Messages'],['templates','Templates'],['settings','Settings']]" :key="item[0]" :to="{ name: 'admin.whatsapp', query: { tab: item[0] } }" class="rounded-lg px-4 py-2 text-sm font-semibold" :class="tab === item[0] ? 'bg-teal-700 text-white' : 'border border-slate-200 text-slate-700'">{{ item[1] }}</RouterLink>
        </nav>
        <FormErrors :error="error"/><p v-if="notice" class="mb-4 rounded-xl bg-emerald-50 p-4 text-emerald-800" role="status">{{ notice }}</p>
        <div v-if="!connection" class="settings-card">Loading platform WhatsApp...</div>
        <template v-else>
            <div v-if="!configured && tab !== 'settings'" class="settings-card"><p>Platform WhatsApp is not configured.</p><RouterLink class="btn mt-4 inline-block" :to="{ name: 'admin.whatsapp', query: { tab: 'settings' } }">Configure WhatsApp</RouterLink></div>
            <section v-if="tab === 'messages' && configured" class="settings-card">
                <header class="flex flex-wrap items-center justify-between gap-3"><div><h2>WhatsApp Messages</h2><p>Manage WhatsApp communications sent by the Afriso platform.</p></div><button v-if="can('platform_whatsapp.send')" class="btn" type="button" :disabled="!connection.enabled" @click="composing = !composing">{{ composing ? 'Cancel' : 'Send Message' }}</button></header>
                <p v-if="!connection.enabled" class="mb-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">Enable WhatsApp in System Settings → Notifications before sending.</p>
                <form v-if="composing" class="mb-6 rounded-xl border border-slate-200 p-4" @submit.prevent="send"><h3 class="font-semibold">Send approved template</h3><div class="form-grid mt-4"><label class="field">Recipient<input v-model.trim="sendForm.recipient" type="tel" required placeholder="+252611234567"><small>Enter one Afriso user or business contact in international format.</small></label><label class="field">Template<select v-model.number="sendForm.template_id" required @change="chooseTemplate"><option :value="null" disabled>Select approved template</option><option v-for="item in templates.filter(t => t.sendable)" :key="item.id" :value="item.id">{{ item.name }} ({{ item.language }})</option></select></label><template v-if="selected"><label v-for="n in selected.parameter_schema.header" :key="`h-${n}`" class="field">Header parameter {{ n }}<input v-model="sendForm.parameters.header[n-1]" required maxlength="1000"></label><label v-for="n in selected.parameter_schema.body" :key="`b-${n}`" class="field">Body parameter {{ n }}<input v-model="sendForm.parameters.body[n-1]" required maxlength="1000"></label></template></div><button class="btn mt-4" :disabled="busy || !selected">{{ busy ? 'Queuing...' : 'Send Message' }}</button></form>
                <form class="mb-4 grid gap-3 sm:grid-cols-4" @submit.prevent="loadMessages(1)"><label class="field">Recipient<input v-model.trim="filters.recipient" placeholder="Search number"></label><label class="field">Status<select v-model="filters.status"><option value="">All statuses</option><option v-for="value in ['queued','sent','delivered','read','failed']" :key="value">{{ value }}</option></select></label><label class="field">From<input v-model="filters.date_from" type="date"></label><label class="field">To<input v-model="filters.date_to" type="date"></label><button class="btn sm:col-span-4">Apply filters</button></form>
                <p v-if="history && !history.data.length">No platform WhatsApp messages yet.</p><div v-else-if="history" class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr><th class="p-2">Recipient</th><th class="p-2">Template</th><th class="p-2">Purpose</th><th class="p-2">Status</th><th class="p-2">Sent</th><th class="p-2">Delivered</th><th class="p-2">Read</th></tr></thead><tbody><tr v-for="item in history.data" :key="item.id" class="border-t border-slate-200"><td class="p-2">{{ item.recipient }}</td><td class="p-2">{{ item.template_name }}</td><td class="p-2 capitalize">{{ item.purpose }}</td><td class="p-2 capitalize">{{ item.status }}</td><td class="p-2">{{ date(item.sent_at) }}</td><td class="p-2">{{ date(item.delivered_at) }}</td><td class="p-2">{{ date(item.read_at) }}</td></tr></tbody></table></div>
                <div v-if="history?.last_page > 1" class="mt-4 flex items-center gap-3"><button type="button" class="btn-secondary" :disabled="history.current_page <= 1" @click="loadMessages(history.current_page-1)">Previous</button><span>{{ history.current_page }} / {{ history.last_page }}</span><button type="button" class="btn-secondary" :disabled="history.current_page >= history.last_page" @click="loadMessages(history.current_page+1)">Next</button></div>
            </section>
            <section v-if="tab === 'templates' && configured" class="settings-card"><header class="flex flex-wrap items-center justify-between gap-3"><div><h2>Platform Templates</h2><p>Meta controls approval. Afriso purpose is an internal classification.</p></div><button v-if="can('platform_whatsapp.manage')" class="btn" :disabled="busy || !connection.enabled" @click="sync">{{ busy ? 'Synchronizing...' : 'Sync Templates' }}</button></header><p v-if="!connection.enabled" class="mb-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">Enable WhatsApp in System Settings → Notifications before synchronizing.</p><p v-if="!templates.length">No platform WhatsApp templates have been synchronized.</p><div v-else class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr><th class="p-2">Template</th><th class="p-2">Language</th><th class="p-2">Afriso Purpose</th><th class="p-2">Meta Category</th><th class="p-2">Meta Status</th><th class="p-2">Last Synced</th></tr></thead><tbody><tr v-for="item in templates" :key="item.id" class="border-t border-slate-200"><td class="p-2">{{ item.name }}</td><td class="p-2">{{ item.language }}</td><td class="p-2"><select v-model="item.purpose" :disabled="!can('platform_whatsapp.manage')" @change="savePurpose(item)"><option v-for="purpose in purposes" :key="purpose" :value="purpose">{{ purpose }}</option></select></td><td class="p-2">{{ item.category || '—' }}</td><td class="p-2">{{ item.is_available ? item.status : 'Unavailable' }}</td><td class="p-2">{{ date(item.last_synced_at) }}</td></tr></tbody></table></div></section>
            <section v-if="tab === 'settings'" class="settings-card"><header><h2>Platform WhatsApp Settings</h2><p>This connection belongs to Afriso. Business connections are separate.</p></header><p><strong>Connection status:</strong> {{ configured ? 'Configured' : 'Not configured' }}</p><form v-if="can('platform_whatsapp.manage')" class="mt-4" @submit.prevent="saveConnection"><div class="form-grid"><label class="field">WhatsApp Business Account ID<input v-model.trim="form.business_account_id" required></label><label class="field">Phone Number ID<input v-model.trim="form.phone_number_id" required></label><label class="field">Display Number<input v-model.trim="form.display_phone_number"></label><label class="field">Access Token<input v-model="form.access_token" type="password" autocomplete="new-password" :required="!connection.has_access_token" :placeholder="connection.has_access_token ? 'Enter only to replace stored token' : 'Enter Meta access token'"><small>Stored encrypted; never returned to the browser.</small></label></div><button class="btn mt-4" :disabled="busy">{{ busy ? 'Saving...' : 'Save Connection' }}</button></form><p v-else class="mt-4">Business Account ID: {{ connection.business_account_id || '—' }} · Phone Number ID: {{ connection.phone_number_id || '—' }}</p><div class="mt-6 rounded-xl border border-slate-200 p-4"><h3 class="font-semibold">Meta webhook and availability</h3><p class="mt-2 text-sm">Availability: {{ connection.enabled ? 'enabled' : 'disabled' }} · Webhook verification: {{ connection.webhook_configured ? 'configured' : 'missing' }} · Signature secret: {{ connection.signature_configured ? 'configured' : 'missing' }}.</p><p class="mt-2 text-sm">Enable WhatsApp, Meta App ID, Meta App Secret, and Webhook Verify Token use the existing System Settings → Notifications values. Webhook URL: <code>/api/v1/public/whatsapp/webhook</code>.</p><RouterLink v-if="can('settings.view')" class="btn-secondary mt-4 inline-block" :to="{ name: 'admin.settings', query: { tab: 'notifications' } }">Open System Settings</RouterLink></div></section>
        </template>
    </div>
</template>
