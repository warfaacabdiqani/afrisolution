<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import api from '../../services/api';
import { useClinicContextStore } from '../../stores/clinicContext';
import FormErrors from '../ui/FormErrors.vue';

const context = useClinicContextStore();
const section = ref('connection');
const state = ref(null), templates = ref([]), history = ref(null), detail = ref(null);
const error = ref(null), busy = ref(false), notice = ref('');
const form = reactive({ business_account_id: '', phone_number_id: '', display_phone_number: '', display_name: '', access_token: '' });
const sendForm = reactive({ recipient: '', template_id: null, parameters: { header: [], body: [] } });
const filters = reactive({ status: '', recipient: '', date_from: '', date_to: '' });
const selectedTemplate = computed(() => templates.value.find(item => item.id === sendForm.template_id));
const connected = computed(() => ['configured', 'connected'].includes(state.value?.status));
const options = () => ({ headers: context.headers() });

async function loadConnection() {
    if (!context.can('whatsapp.view')) return;
    error.value = null;
    try {
        state.value = (await api.get('/v1/whatsapp/connection', options())).data.data;
        for (const key of ['business_account_id', 'phone_number_id', 'display_phone_number', 'display_name']) form[key] = state.value[key] || '';
        form.access_token = '';
    } catch (e) { error.value = e; }
}
async function loadTemplates() {
    try { templates.value = (await api.get('/v1/whatsapp/templates', options())).data.data; }
    catch (e) { error.value = e; }
}
async function loadHistory(page = 1) {
    try { history.value = (await api.get('/v1/whatsapp/messages', { ...options(), params: { ...filters, page } })).data.data; }
    catch (e) { error.value = e; }
}
async function selectSection(next) {
    section.value = next; error.value = null; notice.value = ''; detail.value = null;
    if (next === 'templates' || next === 'send') await loadTemplates();
    if (next === 'history') await loadHistory();
}
async function save() {
    if (busy.value) return;
    busy.value = true; error.value = null; notice.value = '';
    try { await api.put('/v1/whatsapp/connection', { ...form }, options()); await loadConnection(); notice.value = 'WhatsApp configuration saved. Connection verification is not available yet.'; }
    catch (e) { error.value = e; }
    finally { busy.value = false; }
}
async function disable() {
    if (busy.value || !confirm('Disable this WhatsApp connection?')) return;
    busy.value = true; error.value = null; notice.value = '';
    try { await api.post('/v1/whatsapp/connection/disable', {}, options()); await loadConnection(); notice.value = 'WhatsApp connection disabled.'; }
    catch (e) { error.value = e; }
    finally { busy.value = false; }
}
async function syncTemplates() {
    if (busy.value) return;
    busy.value = true; error.value = null; notice.value = '';
    try { const result = await api.post('/v1/whatsapp/templates/sync', {}, options()); await loadTemplates(); notice.value = `${result.data.count} template(s) synchronized from Meta.`; }
    catch (e) { error.value = e; }
    finally { busy.value = false; }
}
function chooseTemplate() {
    const schema = selectedTemplate.value?.parameter_schema;
    sendForm.parameters = { header: Array(schema?.header || 0).fill(''), body: Array(schema?.body || 0).fill('') };
}
async function send() {
    if (busy.value) return;
    busy.value = true; error.value = null; notice.value = '';
    try {
        await api.post('/v1/whatsapp/messages', { recipient: sendForm.recipient, template_id: sendForm.template_id, parameters: sendForm.parameters }, options());
        sendForm.recipient = ''; notice.value = 'Template message queued. Delivery updates will appear in Message History.';
    } catch (e) { error.value = e; }
    finally { busy.value = false; }
}
async function showMessage(id) {
    error.value = null;
    try { detail.value = (await api.get(`/v1/whatsapp/messages/${id}`, options())).data.data; }
    catch (e) { error.value = e; }
}
onMounted(loadConnection);
</script>

<template>
    <div>
        <nav class="flex flex-wrap gap-2 border-b border-slate-200 pb-4" aria-label="WhatsApp integration sections">
            <button v-for="item in [['connection','Connection'],['templates','Templates'],['send','Send Test Message'],['history','Message History']]" :key="item[0]" type="button" class="rounded-lg px-3 py-2 text-sm font-semibold" :class="section === item[0] ? 'bg-teal-700 text-white' : 'border border-slate-200 text-slate-700'" @click="selectSection(item[0])">{{ item[1] }}</button>
        </nav>
        <FormErrors :error="error" />
        <p v-if="notice" role="status" class="cs-success mt-4">{{ notice }}</p>
        <p v-if="!context.can('whatsapp.view')" class="cs-notice mt-4">You do not have permission to view WhatsApp settings.</p>
        <p v-else-if="!state && !error" class="mt-4">Loading WhatsApp configuration...</p>
        <template v-else-if="state">
            <section v-if="section === 'connection'" class="mt-5 space-y-4">
                <div><h3 class="text-lg font-semibold">WhatsApp Business</h3><p class="cs-hint">Configure the business number used for Meta WhatsApp Cloud API.</p></div>
                <p><strong>Status:</strong> {{ state.status === 'configured' ? 'Configured (unverified)' : state.status === 'not_configured' ? 'Not connected' : state.status }}</p>
                <p><strong>Notifications preference:</strong> {{ state.notifications_enabled ? 'Enabled' : 'Disabled' }}. This is separate from connection status.</p>
                <form v-if="state.can_manage" @submit.prevent="save">
                    <div class="form-grid">
                        <label class="field">WhatsApp Business Account ID<input v-model.trim="form.business_account_id" required inputmode="numeric"></label>
                        <label class="field">Phone Number ID<input v-model.trim="form.phone_number_id" required inputmode="numeric"></label>
                        <label class="field">Display Phone Number<input v-model.trim="form.display_phone_number" placeholder="+252 61 123 4567"></label>
                        <label class="field">Display Name<input v-model.trim="form.display_name"></label>
                        <label class="field">Access Token<input v-model="form.access_token" type="password" autocomplete="new-password" :required="!state.has_access_token" :placeholder="state.has_access_token ? 'Stored securely — enter a new token to replace' : 'Enter Meta access token'"><small v-if="state.has_access_token">Stored securely. The saved token cannot be viewed.</small></label>
                    </div>
                    <div class="cs-save"><button class="btn" :disabled="busy">{{ busy ? 'Saving...' : 'Save configuration' }}</button><button v-if="connected" type="button" class="btn-secondary" :disabled="busy" @click="disable">Disable</button></div>
                </form>
                <dl v-else><dt>Business Account ID</dt><dd>{{ state.business_account_id || '—' }}</dd><dt>Phone Number ID</dt><dd>{{ state.phone_number_id || '—' }}</dd><dt>Display Phone Number</dt><dd>{{ state.display_phone_number || '—' }}</dd></dl>
            </section>
            <section v-else-if="section === 'templates'" class="mt-5">
                <div class="flex flex-wrap items-center justify-between gap-3"><div><h3 class="text-lg font-semibold">WhatsApp Templates</h3><p class="cs-hint">Meta controls template approval.</p></div><button v-if="context.can('whatsapp.manage') && connected" type="button" class="btn" :disabled="busy" @click="syncTemplates">{{ busy ? 'Synchronizing...' : 'Sync Templates' }}</button></div>
                <p v-if="!connected" class="cs-notice mt-4">Connect WhatsApp before synchronizing templates or sending messages.</p>
                <p v-else-if="!templates.length" class="cs-notice mt-4">No WhatsApp templates have been synchronized yet.</p>
                <div v-else class="mt-4 overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr><th class="p-2">Name</th><th class="p-2">Language</th><th class="p-2">Category</th><th class="p-2">Status</th></tr></thead><tbody><tr v-for="item in templates" :key="item.id" class="border-t border-slate-200"><td class="p-2">{{ item.name }}</td><td class="p-2">{{ item.language }}</td><td class="p-2">{{ item.category || '—' }}</td><td class="p-2">{{ item.is_available ? item.status : 'Unavailable' }}</td></tr></tbody></table></div>
            </section>
            <section v-else-if="section === 'send'" class="mt-5">
                <h3 class="text-lg font-semibold">Send Test Message</h3><p class="cs-hint">Send one approved Meta template to an international number. This is not a chat.</p>
                <p v-if="!connected" class="cs-notice mt-4">Connect WhatsApp before synchronizing templates or sending messages.</p>
                <p v-else-if="!context.can('whatsapp.send')" class="cs-notice mt-4">You do not have permission to send WhatsApp messages.</p>
                <p v-else-if="!templates.some(item => item.sendable)" class="cs-notice mt-4">Sync an approved, supported template before sending.</p>
                <form v-else class="mt-4 space-y-4" @submit.prevent="send">
                    <label class="field">Recipient<input v-model.trim="sendForm.recipient" type="tel" required placeholder="+252611234567"><small>Include + and the country code. Afriso will not guess one.</small></label>
                    <label class="field">Template<select v-model.number="sendForm.template_id" required @change="chooseTemplate"><option :value="null" disabled>Select an approved template</option><option v-for="item in templates.filter(t => t.sendable)" :key="item.id" :value="item.id">{{ item.name }} ({{ item.language }})</option></select></label>
                    <template v-if="selectedTemplate"><label v-for="n in selectedTemplate.parameter_schema.header" :key="`header-${n}`" class="field">Header parameter {{ n }}<input v-model="sendForm.parameters.header[n-1]" required maxlength="1000"></label><label v-for="n in selectedTemplate.parameter_schema.body" :key="`body-${n}`" class="field">Body parameter {{ n }}<input v-model="sendForm.parameters.body[n-1]" required maxlength="1000"></label></template>
                    <button class="btn" :disabled="busy || !selectedTemplate">{{ busy ? 'Queuing...' : 'Send Message' }}</button>
                </form>
            </section>
            <section v-else class="mt-5">
                <h3 class="text-lg font-semibold">Message History</h3>
                <form class="mt-4 grid gap-3 sm:grid-cols-2" @submit.prevent="loadHistory(1)"><label class="field">Status<select v-model="filters.status"><option value="">All statuses</option><option v-for="value in ['queued','sent','delivered','read','failed']" :key="value" :value="value">{{ value }}</option></select></label><label class="field">Recipient<input v-model.trim="filters.recipient" placeholder="Search number"></label><label class="field">From<input v-model="filters.date_from" type="date"></label><label class="field">To<input v-model="filters.date_to" type="date"></label><button class="btn sm:col-span-2">Apply filters</button></form>
                <p v-if="history && !history.data.length" class="cs-notice mt-4">No WhatsApp messages match these filters.</p>
                <div v-else-if="history" class="mt-4 overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr><th class="p-2">Recipient</th><th class="p-2">Template</th><th class="p-2">Status</th><th class="p-2">Requested</th><th class="p-2"></th></tr></thead><tbody><tr v-for="item in history.data" :key="item.id" class="border-t border-slate-200"><td class="p-2">{{ item.recipient }}</td><td class="p-2">{{ item.template_name }}</td><td class="p-2">{{ item.status }}</td><td class="p-2">{{ item.requested_at ? new Date(item.requested_at).toLocaleString() : '—' }}</td><td class="p-2"><button class="text-teal-700 underline" type="button" @click="showMessage(item.id)">Details</button></td></tr></tbody></table></div>
                <div v-if="history?.last_page > 1" class="mt-3 flex items-center gap-3"><button class="btn-secondary" type="button" :disabled="history.current_page <= 1" @click="loadHistory(history.current_page-1)">Previous</button><span>Page {{ history.current_page }} of {{ history.last_page }}</span><button class="btn-secondary" type="button" :disabled="history.current_page >= history.last_page" @click="loadHistory(history.current_page+1)">Next</button></div>
                <div v-if="detail" class="mt-5 rounded-xl border border-slate-200 p-4"><div class="flex justify-between"><h4 class="font-semibold">Message details</h4><button type="button" class="text-teal-700" @click="detail = null">Close</button></div><dl class="mt-3 grid gap-2 sm:grid-cols-2"><div v-for="field in [['Recipient','recipient'],['Template','template_name'],['Language','template_language'],['Status','status'],['Requested','requested_at'],['Sent','sent_at'],['Delivered','delivered_at'],['Read','read_at'],['Failed','failed_at'],['Failure code','failure_code']]" :key="field[1]"><dt class="text-sm text-slate-500">{{ field[0] }}</dt><dd>{{ detail[field[1]] || '—' }}</dd></div></dl></div>
            </section>
        </template>
    </div>
</template>
