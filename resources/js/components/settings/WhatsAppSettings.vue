<script setup>
import { onMounted, reactive, ref } from 'vue';
import api from '../../services/api';
import { useClinicContextStore } from '../../stores/clinicContext';
import FormErrors from '../ui/FormErrors.vue';

const context = useClinicContextStore();
const state = ref(null), error = ref(null), busy = ref(false), notice = ref('');
const form = reactive({ business_account_id: '', phone_number_id: '', display_phone_number: '', display_name: '', access_token: '' });
const endpoint = '/v1/whatsapp/connection';
const options = () => ({ headers: context.headers() });
async function load() {
    if (!context.can('whatsapp.view')) return;
    error.value = null;
    try {
        state.value = (await api.get(endpoint, options())).data.data;
        for (const key of ['business_account_id', 'phone_number_id', 'display_phone_number', 'display_name']) form[key] = state.value[key] || '';
        form.access_token = '';
    } catch (e) { error.value = e; }
}
async function save() {
    if (busy.value) return;
    busy.value = true; error.value = null; notice.value = '';
    try {
        await api.put(endpoint, { ...form }, options());
        await load();
        notice.value = 'WhatsApp configuration saved. Connection verification is not available yet.';
    } catch (e) { error.value = e; }
    finally { busy.value = false; }
}
async function disable() {
    if (busy.value || !confirm('Disable this WhatsApp connection?')) return;
    busy.value = true; error.value = null; notice.value = '';
    try { await api.post(`${endpoint}/disable`, {}, options()); await load(); notice.value = 'WhatsApp connection disabled.'; }
    catch (e) { error.value = e; }
    finally { busy.value = false; }
}
onMounted(load);
</script>

<template>
    <div>
        <h3>WhatsApp Business</h3>
        <p class="cs-hint">Configure the business number used for Meta WhatsApp Cloud API. Sending is not available yet.</p>
        <p v-if="!context.can('whatsapp.view')" class="cs-notice">You do not have permission to view WhatsApp settings.</p>
        <template v-else>
            <FormErrors :error="error" />
            <p v-if="notice" role="status" class="cs-success">{{ notice }}</p>
            <p v-if="!state && !error">Loading WhatsApp configuration...</p>
            <template v-if="state">
                <p><strong>Status:</strong> {{ state.status === 'configured' ? 'Configured (unverified)' : state.status === 'not_configured' ? 'Not connected' : state.status }}</p>
                <p><strong>Notifications preference:</strong> {{ state.notifications_enabled ? 'Enabled' : 'Disabled' }}. This is separate from connection status.</p>
                <form v-if="state.can_manage" class="mt-4" @submit.prevent="save">
                    <div class="form-grid">
                        <label class="field">WhatsApp Business Account ID<input v-model.trim="form.business_account_id" required inputmode="numeric"></label>
                        <label class="field">Phone Number ID<input v-model.trim="form.phone_number_id" required inputmode="numeric"></label>
                        <label class="field">Display Phone Number<input v-model.trim="form.display_phone_number" placeholder="+252 61 123 4567"></label>
                        <label class="field">Display Name<input v-model.trim="form.display_name"></label>
                        <label class="field">Access Token<input v-model="form.access_token" type="password" autocomplete="new-password" :required="!state.has_access_token" :placeholder="state.has_access_token ? 'Stored securely — enter a new token to replace' : 'Enter Meta access token'"><small v-if="state.has_access_token">Stored securely. The saved token cannot be viewed.</small></label>
                    </div>
                    <div class="cs-save"><button class="btn" :disabled="busy">{{ busy ? 'Saving...' : 'Save configuration' }}</button><button v-if="state.status !== 'not_configured' && state.status !== 'disabled'" type="button" class="btn-secondary" :disabled="busy" @click="disable">Disable</button></div>
                </form>
                <dl v-else><dt>Business Account ID</dt><dd>{{ state.business_account_id || '—' }}</dd><dt>Phone Number ID</dt><dd>{{ state.phone_number_id || '—' }}</dd><dt>Display Phone Number</dt><dd>{{ state.display_phone_number || '—' }}</dd></dl>
            </template>
        </template>
    </div>
</template>
